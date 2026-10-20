<?php
namespace App\Models;
use App\Core\{DB,Auth,Upload};
class Admin {
 public static function editorOptions(int $id,string $module): array {
  return ['editorOptions'=>['categories'=>DB::all('SELECT id,name FROM categories ORDER BY name'),'products'=>DB::all('SELECT id,name FROM products ORDER BY name')],'selectedProducts'=>$id && $module==='bundles'?array_column(DB::all('SELECT product_id FROM bundle_items WHERE bundle_id=?',[$id]),'product_id'):[]];
 }
 public static function modules(): array {
  return [
   'products'=>['title'=>'Products','table'=>'products','fields'=>['name'=>'text','slug'=>'text','category_id'=>'category','short_description'=>'text','description'=>'textarea','ingredients'=>'textarea','allergens'=>'textarea','storage'=>'textarea','shelf_life'=>'text','image'=>'image','featured'=>'bool','active'=>'bool','seo_title'=>'text','seo_description'=>'text'],'columns'=>['id','name','category_id','active','featured']],
   'variants'=>['title'=>'Variants & inventory','table'=>'product_variants','fields'=>['product_id'=>'product','sku'=>'text','label'=>'text','price'=>'money','compare_price'=>'money','stock'=>'number','active'=>'bool'],'columns'=>['id','product_id','sku','label','price','stock','active']],
   'categories'=>['title'=>'Categories','table'=>'categories','fields'=>['name'=>'text','slug'=>'text','description'=>'textarea','image'=>'image','sort_order'=>'number','active'=>'bool'],'columns'=>['id','name','slug','sort_order','active']],
   'coupons'=>['title'=>'Discounts & coupons','table'=>'coupons','fields'=>['code'=>'text','type'=>'select:percent,fixed','value'=>'number','min_total'=>'money','max_discount'=>'money','usage_limit'=>'number','per_customer_limit'=>'number','product_id'=>'optional_product','category_id'=>'optional_category','starts_at'=>'datetime','ends_at'=>'datetime','active'=>'bool'],'columns'=>['id','code','type','value','usage_limit','ends_at','active']],
   'bundles'=>['title'=>'Bundle offers','table'=>'bundles','fields'=>['name'=>'text','description'=>'textarea','min_items'=>'number','discount_percent'=>'number','fixed_price'=>'money','starts_at'=>'datetime','ends_at'=>'datetime','active'=>'bool'],'columns'=>['id','name','min_items','discount_percent','fixed_price','active']],
   'reviews'=>['title'=>'Customer reviews','table'=>'reviews','fields'=>['status'=>'select:pending,approved,hidden','response'=>'textarea'],'columns'=>['id','user_id','product_id','rating','body','status']],
   'customers'=>['title'=>'Customers','table'=>'users','fields'=>['active'=>'bool'],'columns'=>['id','name','email','mobile','email_verified_at','active','created_at']],
   'media'=>['title'=>'Product gallery','table'=>'product_images','fields'=>['product_id'=>'product','path'=>'image'],'columns'=>['id','product_id','path']],
   'admins'=>['title'=>'Admin accounts','table'=>'admin_users','fields'=>['name'=>'text','email'=>'email','password'=>'password','role'=>'select:owner,manager','active'=>'bool'],'columns'=>['id','name','email','role','active']],
   'inventory-log'=>['title'=>'Stock movement history','table'=>'inventory_transactions','fields'=>[],'columns'=>['id','variant_id','quantity','reason','created_at']],
   'audit'=>['title'=>'Audit log','table'=>'audit_logs','fields'=>[],'columns'=>['id','admin_id','action','details','created_at']],
   'messages'=>['title'=>'Contact messages','table'=>'contact_messages','fields'=>[],'columns'=>['id','name','email','message','created_at']],
   'newsletter'=>['title'=>'Newsletter subscribers','table'=>'newsletter_subscribers','fields'=>[],'columns'=>['id','email','created_at']],
   'coupon-usage'=>['title'=>'Coupon usage','table'=>'coupon_usage','fields'=>[],'columns'=>['id','coupon_id','user_id','order_id']],
  ];
 }
 public static function module(string $name): array { $m=self::modules()[$name]??null;if (!$m) throw new \RuntimeException('Management module not found.');if (in_array($name,['admins','audit','newsletter'],true)) Auth::protect('owner');return $m; }
 public static function audit(string $action,string $details): void {DB::insert('audit_logs',['admin_id'=>Auth::admin()['id']??null,'action'=>$action,'details'=>substr($details,0,255)]);}
 public static function save(string $module,int $id): int {
  $m=self::module($module);if (!$m['fields']) throw new \RuntimeException('This is a read-only report.');
  if (!$id && in_array($module,['customers','reviews'],true)) throw new \RuntimeException('These records are created by customers.');
  $old=$id?DB::one('SELECT * FROM `'.$m['table'].'` WHERE id=?',[$id]):null;if ($id && !$old) throw new \RuntimeException('Record not found.');$data=[];
  foreach($m['fields'] as $field=>$type) {
   $v=\post($field);
   if ($type==='password') { if (!$v && $id) continue;if (strlen($v)<10) throw new \RuntimeException('Password must contain at least 10 characters.');$v=password_hash($v,PASSWORD_DEFAULT); }
   elseif ($type==='image') { $v=Upload::image($field)??($old[$field]??'');if (!$v && $module==='products') $v='assets/images/hero.png'; }
   elseif ($type==='bool') $v=isset($_POST[$field])?1:0;
   elseif ($type==='money') {if (!is_numeric($v) || (float)$v<0 || (float)$v>10000000) throw new \RuntimeException('Enter a valid non-negative price.');$v=(int)round((float)$v*100);}
   elseif ($type==='number') {if (!preg_match('/^\d+$/',$v)) throw new \RuntimeException('Enter a non-negative number for '.$field);$v=(int)$v;}
   elseif ($type==='datetime') {if ($v && !strtotime($v)) throw new \RuntimeException('Invalid date.');$v=$v?date('Y-m-d H:i:s',strtotime($v)):null;}
   elseif (str_starts_with($type,'optional_')) $v=$v?(int)$v:null;
   elseif (str_starts_with($type,'select:')) {if (!in_array($v,explode(',',substr($type,7)),true)) throw new \RuntimeException('Invalid '.$field.'.');}
   elseif ($type==='email' && !filter_var($v,FILTER_VALIDATE_EMAIL)) throw new \RuntimeException('Invalid email.');
   if (in_array($field,['name','slug','sku','label','code'],true) && $v==='') throw new \RuntimeException(ucfirst($field).' is required.');
   $data[$field]=$v;
  }
  if (isset($data['slug']) && !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/',$data['slug'])) throw new \RuntimeException('Use lowercase letters, numbers and hyphens in the slug.');
  if (isset($data['code'])) $data['code']=strtoupper($data['code']);
  if (isset($data['discount_percent']) && $data['discount_percent']>100) throw new \RuntimeException('Discount cannot exceed 100%.');
  if ($module==='coupons' && $data['type']==='percent' && $data['value']>100) throw new \RuntimeException('Percentage cannot exceed 100.');
  if (isset($data['starts_at'],$data['ends_at']) && $data['starts_at']>$data['ends_at']) throw new \RuntimeException('End date must follow the start date.');
  if ($module==='bundles' && $data['min_items']<1) throw new \RuntimeException('Minimum quantity must be at least one.');
  if ($module==='admins' && $id==Auth::admin()['id'] && (!$data['active'] || $data['role']!=='owner')) throw new \RuntimeException('You cannot deactivate or demote your own owner account.');
  return DB::transaction(function() use($id,$data,$m,$module) {
   $old=$id?DB::one('SELECT * FROM `'.$m['table'].'` WHERE id=? FOR UPDATE',[$id]):null;
   if ($id) DB::update($m['table'],$data,$id);else $id=DB::insert($m['table'],$data);
   if ($module==='variants') { $delta=(int)$data['stock']-(int)($old['stock']??0);if ($delta) DB::insert('inventory_transactions',['variant_id'=>$id,'quantity'=>$delta,'reason'=>'Admin adjustment']); }
   if ($module==='bundles') { DB::query('DELETE FROM bundle_items WHERE bundle_id=?',[$id]);foreach(array_unique(array_map('intval',$_POST['eligible_products']??[])) as $pid) DB::insert('bundle_items',['bundle_id'=>$id,'product_id'=>$pid]); }
   self::audit('save:'.$module,'Record #'.$id);return $id;
  });
 }
 public static function settingsFields(): array {return [
  'store'=>['brand_name','announcement','email','phone','whatsapp','address','instagram','facebook','logo','fssai_image','fssai_number'],
  'shipping'=>['shipping','free_shipping','tax_percent','cod_enabled','low_stock'],
  'homepage'=>['hero_eyebrow','hero_heading','hero_description','hero_image','hero_button','hero_button_secondary','story_heading','story_text','story_image'],
  'policies'=>['page_about','page_faq','page_shipping','page_returns','page_privacy','page_terms'],
  'razorpay'=>['razorpay_enabled','razorpay_mode','razorpay_key_id','razorpay_key_secret','razorpay_webhook_secret'],
 ];}
 public static function metrics(string $from,string $to): array {
  $range=[$from.' 00:00:00',$to.' 23:59:59'];
  return [
   'sales'=>DB::one("SELECT COALESCE(SUM(total),0) total FROM orders WHERE payment_status='paid' AND created_at BETWEEN ? AND ?",$range)['total'],
   'today'=>DB::one("SELECT COALESCE(SUM(total),0) total FROM orders WHERE payment_status='paid' AND DATE(created_at)=CURDATE()")['total'],
   'orders'=>DB::one('SELECT COUNT(*) total FROM orders WHERE created_at BETWEEN ? AND ?',$range)['total'],
   'customers'=>DB::one('SELECT COUNT(*) total FROM users')['total'],'products'=>DB::one('SELECT COUNT(*) total FROM products WHERE active=1')['total'],
   'low'=>DB::one('SELECT COUNT(*) total FROM product_variants WHERE stock<=? AND active=1',[(int)Setting::get('low_stock','10')])['total'],
   'daily'=>DB::all("SELECT DATE(created_at) label,SUM(total) value FROM orders WHERE payment_status='paid' AND created_at BETWEEN ? AND ? GROUP BY DATE(created_at)",$range),
   'monthly'=>DB::all("SELECT DATE_FORMAT(created_at,'%Y-%m') label,SUM(total) value FROM orders WHERE payment_status='paid' AND created_at BETWEEN ? AND ? GROUP BY label",$range),
   'statuses'=>DB::all('SELECT status label,COUNT(*) value FROM orders WHERE created_at BETWEEN ? AND ? GROUP BY status',$range),
   'best'=>DB::all("SELECT oi.name label,SUM(oi.quantity) value FROM order_items oi JOIN orders o ON o.id=oi.order_id WHERE o.payment_status='paid' AND o.created_at BETWEEN ? AND ? GROUP BY oi.product_id,oi.name ORDER BY value DESC LIMIT 5",$range),
   'category'=>DB::all("SELECT c.name label,SUM(oi.price*oi.quantity) value FROM order_items oi JOIN orders o ON o.id=oi.order_id JOIN products p ON p.id=oi.product_id JOIN categories c ON c.id=p.category_id WHERE o.payment_status='paid' AND o.created_at BETWEEN ? AND ? GROUP BY c.id,c.name",$range)
  ];
 }
}
