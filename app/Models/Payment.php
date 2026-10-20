<?php
namespace App\Models;
use App\Core\DB;
class Payment {
 public static function configured(): bool { return Setting::get('razorpay_enabled')==='1' && Setting::get('razorpay_key_id')!=='' && Setting::secret('razorpay_key_secret')!=='' && Setting::secret('razorpay_webhook_secret')!==''; }
 public static function request(string $path,?array $body=null): array {
  $ch=curl_init('https://api.razorpay.com/v1/'.$path);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_USERPWD=>Setting::get('razorpay_key_id').':'.Setting::secret('razorpay_key_secret'),CURLOPT_TIMEOUT=>25,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_HTTPHEADER=>['Content-Type: application/json']]);if ($body!==null) {curl_setopt($ch,CURLOPT_POST,true);curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($body));}
  $raw=curl_exec($ch);$status=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);$data=json_decode($raw?:'',true);
  if ($status<200 || $status>=300 || !is_array($data)) throw new \RuntimeException('Payment provider is unavailable. Your order is saved; please try again.');return $data;
 }
 public static function start(int $id,int $uid): array {
  if (!self::configured()) throw new \RuntimeException('Online payments are not configured.');
  return DB::transaction(function() use($id,$uid) {
   $o=DB::one('SELECT * FROM orders WHERE id=? AND user_id=? FOR UPDATE',[$id,$uid]);
   if (!$o || $o['payment_method']!=='razorpay' || $o['status']==='Cancelled' || !in_array($o['payment_status'],['pending','failed'],true)) throw new \RuntimeException('This order is not payable.');
   if (!$o['razorpay_order_id']) { $r=self::request('orders',['amount'=>(int)$o['total'],'currency'=>'INR','receipt'=>$o['number'],'partial_payment'=>false]); DB::update('orders',['razorpay_order_id'=>$r['id']],$id);$o['razorpay_order_id']=$r['id']; }
   return ['key'=>Setting::get('razorpay_key_id'),'amount'=>(int)$o['total'],'currency'=>'INR','order_id'=>$o['razorpay_order_id'],'name'=>'Prakshi Foods','description'=>$o['number']];
  });
 }
 public static function verify(int $id,int $uid,string $payment,string $signature): void {
  $o=Order::own($id,$uid);if (!$o['razorpay_order_id'] || !hash_equals(hash_hmac('sha256',$o['razorpay_order_id'].'|'.$payment,Setting::secret('razorpay_key_secret')),$signature)) throw new \RuntimeException('Payment signature verification failed.');
  self::capture(self::request('payments/'.rawurlencode($payment)));
 }
 public static function capture(array $p): void {
  $id=DB::transaction(function() use($p) {
   $o=DB::one('SELECT * FROM orders WHERE razorpay_order_id=? FOR UPDATE',[$p['order_id']??'']);
   if (!$o || ($p['status']??'')!=='captured' || ($p['currency']??'')!=='INR' || (int)($p['amount']??0)!==(int)$o['total'] || $o['status']==='Cancelled') throw new \RuntimeException('Payment is not confirmed. Please check your order again shortly.');
   if ($o['payment_status']==='paid' || $o['payment_status']==='refunded') return (int)$o['id'];
   DB::insert('payments',['order_id'=>$o['id'],'provider_id'=>$p['id'],'amount'=>$p['amount'],'status'=>'captured']);DB::update('orders',['payment_status'=>'paid','status'=>'Confirmed'],(int)$o['id']);
   OrderNotice::record(array_replace($o,['payment_status'=>'paid','status'=>'Confirmed']),'payment:paid','Payment confirmed','Your online payment has been verified.',true);
   return (int)$o['id'];
  });
  OrderNotice::dispatch($id);
 }
 public static function sync(int $id): void {
  $o=DB::one('SELECT * FROM orders WHERE id=?',[$id]);if (!$o || !$o['razorpay_order_id']) throw new \RuntimeException('No provider order to reconcile.');
  $r=self::request('orders/'.rawurlencode($o['razorpay_order_id']).'/payments');
  foreach($r['items']??[] as $p) {if ($p['status']==='captured') self::capture($p); if ($p['status']==='refunded' && (int)($p['amount_refunded']??0)===(int)$o['total'] && (int)$p['amount']===(int)$o['total'] && $p['currency']==='INR') {
   DB::transaction(function()use($id){$row=DB::one('SELECT * FROM orders WHERE id=? FOR UPDATE',[$id]);DB::update('orders',['payment_status'=>'refunded'],$id);OrderNotice::record(array_replace($row,['payment_status'=>'refunded']),'payment:refunded','Refund confirmed','Your full refund has been verified with the payment provider.');});OrderNotice::dispatch($id);
  }}
 }
 public static function webhook(string $raw,string $signature,string $eventId): void {
  $secret=Setting::secret('razorpay_webhook_secret');if (!$secret || !hash_equals(hash_hmac('sha256',$raw,$secret),$signature)) throw new \RuntimeException('Invalid webhook signature.');
  $event=json_decode($raw,true);if (!$event || !$eventId || strlen($eventId)>100) throw new \RuntimeException('Invalid webhook.');
  if (DB::one('SELECT id FROM payment_webhook_events WHERE id=?',[$eventId])) return;
  if (($event['event']??'')==='payment.captured') { $p=$event['payload']['payment']['entity'];self::capture(self::request('payments/'.rawurlencode($p['id']))); }
  if (($event['event']??'')==='payment.failed') { $p=$event['payload']['payment']['entity'];$id=DB::transaction(function()use($p){$o=DB::one('SELECT * FROM orders WHERE razorpay_order_id=? FOR UPDATE',[$p['order_id']??'']);if(!$o || !in_array($o['payment_status'],['pending','failed'],true))return null;DB::update('orders',['payment_status'=>'failed'],(int)$o['id']);OrderNotice::record(array_replace($o,['payment_status'=>'failed']),'payment:failed','Payment unsuccessful','Your order is saved. You can retry payment from your account.');return (int)$o['id'];});if($id)OrderNotice::dispatch($id); }
  DB::query('INSERT IGNORE INTO payment_webhook_events (id,event) VALUES (?,?)',[$eventId,substr($event['event']??'unknown',0,100)]);
 }
}
