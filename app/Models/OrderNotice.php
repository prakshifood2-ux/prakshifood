<?php
namespace App\Models;
use App\Core\{DB,Mailer};
class OrderNotice {
 public static function record(array $o,string $key,string $title,string $detail='',bool $attach=false): void {
  DB::query('INSERT IGNORE INTO order_events (order_id,event_key,title,detail) VALUES (?,?,?,?)',[$o['id'],$key,$title,$detail]);
  $a=json_decode($o['address_json'],true);$invoice=$attach?Invoice::ensure($o):null;
  $body="Hello ".$a['name'].",\n\n$title\nOrder: ".$o['number']."\n$detail\n\nOrder status: ".$o['status']."\nPayment status: ".$o['payment_status']."\nTotal: INR ".number_format($o['total']/100,2)."\n";
  if($o['payment_method']==='cod' && $o['payment_status']==='pending')$body.="Payment is due on delivery.\n";
  foreach(['courier'=>'Courier','tracking'=>'Tracking reference','tracking_url'=>'Carrier tracking','expected_delivery'=>'Estimated delivery'] as $f=>$label)if(!empty($o[$f]))$body.="$label: ".$o[$f]."\n";
  $body.="\nTrack your order: ".\url('order',['id'=>$o['id']])."\n".($attach?"Your sales invoice is attached as a PDF.\n":'')."\nNeed help? ".\config('support_email')."\nPrakshi Foods";
  DB::query('INSERT IGNORE INTO order_emails (order_id,event_key,recipient,subject,body,invoice_id) VALUES (?,?,?,?,?,?)',[$o['id'],$key,$a['email'],'Prakshi '.$o['number'].' - '.$title,$body,$invoice['id']??null]);
 }
 public static function history(int $id): array {return DB::all('SELECT * FROM order_events WHERE order_id=? ORDER BY id',[$id]);}
 public static function emails(int $id): array {return DB::all('SELECT id,subject,state,attempts,sent_at,last_error FROM order_emails WHERE order_id=? ORDER BY id DESC',[$id]);}
 public static function dispatch(int $id,bool $retry=false): void {
  $rows=DB::all("SELECT id FROM order_emails WHERE order_id=? AND state IN ('pending'".($retry?",'failed'":'').') ORDER BY id',[$id]);
  foreach($rows as $row){$mail=DB::transaction(function()use($row,$retry){$m=DB::one('SELECT * FROM order_emails WHERE id=? FOR UPDATE',[$row['id']]);if(!in_array($m['state'],$retry?['pending','failed']:['pending'],true))return null;DB::update('order_emails',['state'=>'sending','attempts'=>$m['attempts']+1,'attempted_at'=>date('Y-m-d H:i:s')],(int)$m['id']);return $m;});if(!$mail)continue;
   try{$attachments=[];if($mail['invoice_id']){$invoice=DB::one('SELECT * FROM invoices WHERE id=?',[$mail['invoice_id']]);$attachments[]=Invoice::attachment($invoice);}Mailer::send($mail['recipient'],$mail['subject'],$mail['body'],$attachments);DB::update('order_emails',['state'=>'sent','sent_at'=>date('Y-m-d H:i:s'),'last_error'=>''],(int)$mail['id']);}
   catch(\Throwable $e){DB::update('order_emails',['state'=>'failed','last_error'=>'Email delivery failed. Check the private mail log and retry.'],(int)$mail['id']);}
  }
 }
 public static function resendInvoice(int $id): void {DB::transaction(function()use($id){$o=DB::one('SELECT * FROM orders WHERE id=? FOR UPDATE',[$id]);if(!$o)throw new \RuntimeException('Order not found.');self::record($o,'invoice:'.bin2hex(random_bytes(8)),'Your invoice','A copy of your original sales invoice.',true);});self::dispatch($id);}
}
