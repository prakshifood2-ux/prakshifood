<?php
namespace App\Controllers;
use App\Core\{Auth,DB,Upload};
use App\Models\{Admin,Setting,Order,Payment};
class AdminController {
 public function login(): void {if (Auth::admin()) \redirect('admin');\render('admin/login',['title'=>'Admin sign in'],'bare');}
 public function authenticate(): void {Auth::throttle('admin',8);$a=DB::one('SELECT * FROM admin_users WHERE email=? AND active=1',[strtolower(\post('email'))]);if (!$a || !password_verify(\post('password'),$a['password'])) throw new \RuntimeException('Email or password is incorrect.');VerificationController::begin('admin',(int)$a['id']);}
 public function logout(): void {Auth::protect();unset($_SESSION['admin_id'],$_SESSION['admin_otp_verified'],$_SESSION['admin_credential'],$_SESSION['admin_challenge']);session_regenerate_id(true);\redirect('admin/login');}
 private function dates(): array {$from=(string)($_GET['from']??date('Y-m-01'));$to=(string)($_GET['to']??date('Y-m-d'));foreach([$from,$to] as $d) if (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$d) || !strtotime($d)) throw new \RuntimeException('Invalid report date.');if ($from>$to) throw new \RuntimeException('Invalid date range.');return [$from,$to];}
 public function dashboard(): void {Auth::protect();[$from,$to]=$this->dates();\render('admin/dashboard',['title'=>'Store overview','metrics'=>Admin::metrics($from,$to),'from'=>$from,'to'=>$to,'recent'=>DB::all('SELECT o.*,u.name customer FROM orders o JOIN users u ON u.id=o.user_id ORDER BY o.id DESC LIMIT 6')],'admin');}
 public function listing(): void {
  Auth::protect();$module=(string)($_GET['module']??'products');$m=Admin::module($module);$page=max(1,(int)($_GET['page']??1));$offset=($page-1)*30;$q=trim((string)($_GET['q']??''));$where='';$args=[];
  if ($q) {$where=' WHERE '.implode(' OR ',array_map(fn($f)=>'CAST(`'.$f.'` AS CHAR) LIKE ?',$m['columns']));$args=array_fill(0,count($m['columns']),'%'.$q.'%');}
  $rows=DB::all('SELECT '.implode(',',$m['columns']).' FROM `'.$m['table'].'`'.$where.' ORDER BY id DESC LIMIT 30 OFFSET '.$offset,$args);
  \render('admin/list',['title'=>$m['title'],'module'=>$module,'m'=>$m,'rows'=>$rows,'page'=>$page,'q'=>$q],'admin');
 }
 public function edit(): void {Auth::protect();$module=(string)($_GET['module']??'products');$m=Admin::module($module);$id=(int)($_GET['id']??0);$row=$id?DB::one('SELECT * FROM `'.$m['table'].'` WHERE id=?',[$id]):[];if ($id && !$row) throw new \RuntimeException('Record not found.');\render('admin/edit',['title'=>($id?'Edit ':'Add ').$m['title'],'module'=>$module,'m'=>$m,'row'=>$row,'id'=>$id],'admin');}
 public function save(): void {Auth::protect();$module=\post('module');Admin::save($module,(int)\post('id'));\flash('Changes saved.');\redirect('admin/list',['module'=>$module]);}
 public function archive(): void {Auth::protect();$module=\post('module');$m=Admin::module($module);$id=(int)\post('id');if (!isset($m['fields']['active']) || $module==='admins') throw new \RuntimeException('This record cannot be archived here.');DB::update($m['table'],['active'=>0],$id);Admin::audit('archive:'.$module,'Record #'.$id);\flash('Record archived. Historical orders are preserved.');\redirect('admin/list',['module'=>$module]);}
 public function settings(): void {Auth::protect('owner');$section=(string)($_GET['section']??'store');$fields=Admin::settingsFields()[$section]??null;if (!$fields) throw new \RuntimeException('Settings section not found.');\render('admin/settings',['title'=>ucfirst($section).' settings','section'=>$section,'fields'=>$fields],'admin');}
 public function saveSettings(): void {
  Auth::protect('owner');$section=\post('section');$fields=Admin::settingsFields()[$section]??null;if (!$fields) throw new \RuntimeException('Settings not found.');
  foreach($fields as $f) {
   if (str_contains($f,'secret')) {if (\post($f)) Setting::saveSecret($f,\post($f));continue;}
   if (in_array($f,['hero_image','story_image','logo','fssai_image'],true)) {$v=Upload::image($f);if ($v) Setting::set($f,$v);continue;}
   $v=\post($f);
   if (in_array($f,['shipping','free_shipping'],true)) {if (!is_numeric($v) || (float)$v<0) throw new \RuntimeException('Enter a valid shipping value.');$v=(string)round((float)$v*100);}
   if (in_array($f,['tax_percent','low_stock'],true) && (!is_numeric($v) || (float)$v<0 || ($f==='tax_percent' && (float)$v>100))) throw new \RuntimeException('Invalid numeric setting.');
   if (in_array($f,['cod_enabled','razorpay_enabled'],true)) $v=isset($_POST[$f])?'1':'0';
   if ($f==='razorpay_mode' && !in_array($v,['test','live'],true)) throw new \RuntimeException('Choose test or live mode.');
   if ($f==='razorpay_key_id' && $v && !preg_match('/^rzp_(test|live)_[a-zA-Z0-9]+$/',$v)) throw new \RuntimeException('Invalid Razorpay key ID.');
   if (in_array($f,['instagram','facebook'],true) && $v && (!filter_var($v,FILTER_VALIDATE_URL) || !str_starts_with($v,'https://'))) throw new \RuntimeException('Social links must be HTTPS URLs.');
   Setting::set($f,$v);
  }
  if ($section==='razorpay' && Setting::get('razorpay_key_id') && !str_starts_with(Setting::get('razorpay_key_id'),'rzp_'.Setting::get('razorpay_mode').'_')) {Setting::set('razorpay_enabled','0');throw new \RuntimeException('Key and payment mode do not match. Payments have been disabled.');}
  Admin::audit('settings',$section.' settings updated');\flash('Settings saved.');\redirect('admin/settings',['section'=>$section]);
 }
 private function orderRows(bool $export=false): array {
  $where='1=1';$args=[];$q=trim((string)($_GET['q']??''));if ($q) {$where.=' AND (o.number LIKE ? OR u.name LIKE ? OR o.address_json LIKE ?)';$args=array_fill(0,3,'%'.$q.'%');}
  foreach(['status','payment_status'] as $f) if (!empty($_GET[$f])) {$where.=' AND o.'.$f.'=?';$args[]=$_GET[$f];}
  if (!empty($_GET['from']) || !empty($_GET['to'])) {[$from,$to]=$this->dates();$where.=' AND o.created_at BETWEEN ? AND ?';$args[]=$from.' 00:00:00';$args[]=$to.' 23:59:59';}
  $offset=(max(1,(int)($_GET['page']??1))-1)*30;
  return DB::all('SELECT o.*,u.name customer,u.email customer_email FROM orders o JOIN users u ON u.id=o.user_id WHERE '.$where.' ORDER BY o.id DESC'.($export?'':' LIMIT 30 OFFSET '.$offset),$args);
 }
 public function orders(): void {Auth::protect();\render('admin/orders',['title'=>'Orders','orders'=>$this->orderRows()],'admin');}
 public function order(): void {Auth::protect();$id=(int)($_GET['id']??0);$o=DB::one('SELECT * FROM orders WHERE id=?',[$id]);if (!$o) throw new \RuntimeException('Order not found.');\render('admin/order',['title'=>$o['number'],'order'=>$o,'items'=>Order::items($id),'payments'=>DB::all('SELECT * FROM payments WHERE order_id=?',[$id])],'admin');}
 public function updateOrder(): void {
  Auth::protect();$id=(int)\post('id');$action=\post('action');
  if($action==='invoice-email') \App\Models\OrderNotice::resendInvoice($id);
  elseif($action==='retry-emails') \App\Models\OrderNotice::dispatch($id,true);
  elseif($action==='sync') Payment::sync($id);
  elseif($action==='cod-paid') {
   DB::transaction(function()use($id){$o=DB::one('SELECT * FROM orders WHERE id=? FOR UPDATE',[$id]);if(!$o || $o['payment_method']!=='cod' || $o['status']!=='Delivered' || !\post('receipt'))throw new \RuntimeException('COD collection requires a delivered order and collection receipt.');if($o['payment_status']==='paid')return;
    DB::insert('payments',['order_id'=>$id,'provider_id'=>'cod-'.$id,'amount'=>$o['total'],'status'=>'cod-collected']);
    DB::update('orders',['payment_status'=>'paid','notes'=>($o['notes']??'')."\nCOD receipt: ".\post('receipt')],$id);
    \App\Models\OrderNotice::record(array_replace($o,['payment_status'=>'paid']),'payment:paid','Payment confirmed','Your cash-on-delivery payment has been collected.',true);
   });\App\Models\OrderNotice::dispatch($id);
  } else Order::status($id,\post('status'),\post('tracking'),\post('notes'),\post('courier'),\post('tracking_url'),\post('expected_delivery'));
  Admin::audit('order:update','Order #'.$id);\flash('Order updated. Check the customer email log below for delivery results.');\redirect('admin/order',['id'=>$id]);
 }
 public function customer(): void {Auth::protect();$id=(int)($_GET['id']??0);$customer=DB::one('SELECT id,name,email,mobile,active,created_at FROM users WHERE id=?',[$id]);if (!$customer) throw new \RuntimeException('Customer not found.');\render('admin/customer',['title'=>$customer['name'],'customer'=>$customer,'orders'=>DB::all('SELECT * FROM orders WHERE user_id=? ORDER BY id DESC',[$id])],'admin');}
 public function exports(): void {Auth::protect();\render('admin/exports',['title'=>'Export centre'],'admin');}
 public function export(): void {
  Auth::protect();$module=(string)($_GET['module']??'orders');if (in_array($module,['orders','sales'],true)) {$rows=$this->orderRows(true);if ($module==='sales') $rows=array_values(array_filter($rows,fn($r)=>$r['payment_status']==='paid'));$cols=['number','created_at','customer','total','payment_method','payment_status','status'];}
  else {$m=Admin::module($module);$cols=$m['columns'];$rows=DB::all('SELECT '.implode(',',$cols).' FROM `'.$m['table'].'` ORDER BY id');}
  Admin::audit('export',$module);header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="prakshi-'.$module.'-'.date('Y-m-d').'.csv"');$out=fopen('php://output','w');fwrite($out,"\xEF\xBB\xBF");fputcsv($out,array_map(fn($c)=>ucwords(str_replace('_',' ',$c)).(in_array($c,['price','total','fixed_price'],true)?' (INR)':''),$cols));foreach($rows as $r){$line=[];foreach($cols as $c){$v=(string)($r[$c]??'');if(in_array($c,['price','total','fixed_price'],true)) $v=number_format((int)$v/100,2,'.','');if(preg_match('/^[\s]*[=+@\-]/u',$v))$v="'".$v;$line[]=$v;}fputcsv($out,$line);}fclose($out);
 }
 public function password(): void { $a=Auth::protect();$row=DB::one('SELECT password FROM admin_users WHERE id=?',[$a['id']]);if (!password_verify(\post('current_password'),$row['password']) || strlen(\post('new_password'))<10) throw new \RuntimeException('Check your current password and use at least 10 characters.');DB::update('admin_users',['password'=>password_hash(\post('new_password'),PASSWORD_DEFAULT)],(int)$a['id']);Admin::audit('password','Admin password changed');\flash('Password changed.');\redirect('admin'); }
}
