<?php
namespace App\Models;
use App\Core\DB;
use App\Core\Mailer;
class Order {
 public const STATUSES=['Pending','Confirmed','Processing','Packed','Shipped','Out for delivery','Delivered','Cancelled'];
 public static function create(int $uid,array $address,string $method,string $key): array {
  if (!preg_match('/^[a-f0-9]{32,64}$/',$key)) throw new \RuntimeException('Invalid checkout request.');
  if (!in_array($method,['cod','razorpay'],true)) throw new \RuntimeException('Choose a payment method.');
  if ($method==='cod' && Setting::get('cod_enabled')!=='1') throw new \RuntimeException('Cash on delivery is unavailable.');
  if ($method==='razorpay' && !Payment::configured()) throw new \RuntimeException('Online payments have not been configured.');
  return DB::transaction(function() use($uid,$address,$method,$key) {
   $cid=Cart::id($uid); DB::one('SELECT id FROM carts WHERE id=? FOR UPDATE',[$cid]);
   $existing=DB::one('SELECT * FROM orders WHERE idempotency_key=?',[$key]);if ($existing) {if ($existing['user_id']!=$uid) throw new \RuntimeException('Invalid checkout.');return $existing;}
   $items=Cart::items($uid);if (!$items) throw new \RuntimeException('Your cart is empty.');
   $needed=[]; foreach($items as $i) {$needed[$i['variant_id']]=($needed[$i['variant_id']]??0)+$i['quantity'];} ksort($needed);
   foreach($needed as $vid=>$qty) { $v=DB::one('SELECT v.*,p.active product_active,c.active category_active FROM product_variants v JOIN products p ON p.id=v.product_id JOIN categories c ON c.id=p.category_id WHERE v.id=? FOR UPDATE',[$vid]);if (!$v || !$v['active'] || !$v['product_active'] || !$v['category_active'] || $v['stock']<$qty) throw new \RuntimeException('One of your items is unavailable or has insufficient stock. Please update your cart.'); }
   $t=Cart::totals($uid,true);if ($t['error']) throw new \RuntimeException($t['error']);
   $id=DB::insert('orders',['user_id'=>$uid,'number'=>'PF-'.date('ymd').'-'.strtoupper(bin2hex(random_bytes(4))),'idempotency_key'=>$key,'address_json'=>json_encode($address),'subtotal'=>$t['subtotal'],'discount'=>$t['discount'],'shipping'=>$t['shipping'],'tax'=>$t['tax'],'total'=>$t['total'],'coupon'=>$t['code'],'payment_method'=>$method,'status'=>$method==='cod'?'Confirmed':'Pending']);
   foreach($t['items'] as $i) DB::insert('order_items',['order_id'=>$id,'variant_id'=>$i['variant_id'],'product_id'=>$i['product_id'],'name'=>$i['name'],'variant'=>$i['label'],'price'=>$i['price'],'quantity'=>$i['quantity']]);
   foreach($needed as $vid=>$qty) { DB::query('UPDATE product_variants SET stock=stock-? WHERE id=?',[$qty,$vid]);DB::insert('inventory_transactions',['variant_id'=>$vid,'quantity'=>-$qty,'reason'=>'Order #'.$id.' reserved']); }
   if ($t['coupon'] && !$t['error']) DB::insert('coupon_usage',['coupon_id'=>$t['coupon']['id'],'user_id'=>$uid,'order_id'=>$id]);
   Address::remember($uid,$address);
   DB::query('DELETE FROM cart_items WHERE cart_id=?',[$cid]);DB::query("UPDATE carts SET coupon='' WHERE id=?",[$cid]);
   $order=DB::one('SELECT * FROM orders WHERE id=?',[$id]);
   OrderNotice::record($order,'placed','Order placed','Thank you. Your order has been received.',true);
   return $order;
  });
 }
 public static function own(int $id,int $uid): array { $o=DB::one('SELECT * FROM orders WHERE id=? AND user_id=?',[$id,$uid]);if (!$o) throw new \RuntimeException('Order not found.');return $o; }
 public static function items(int $id): array { return DB::all('SELECT * FROM order_items WHERE order_id=?',[$id]); }
 public static function status(int $id,string $status,string $tracking,string $notes,string $courier='',string $trackingUrl='',string $eta=''): void {
  if (!in_array($status,self::STATUSES,true)) throw new \RuntimeException('Invalid order status.');
  if ($trackingUrl && (!filter_var($trackingUrl,FILTER_VALIDATE_URL) || !str_starts_with($trackingUrl,'https://') || strlen($trackingUrl)>500)) throw new \RuntimeException('Enter a valid HTTPS tracking link.');
  if ($eta && (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$eta) || date('Y-m-d',strtotime($eta))!==$eta)) throw new \RuntimeException('Enter a valid delivery date.');
  DB::transaction(function() use($id,$status,$tracking,$notes,$courier,$trackingUrl,$eta) {
   $o=DB::one('SELECT * FROM orders WHERE id=? FOR UPDATE',[$id]);if (!$o) throw new \RuntimeException('Order not found.');
   $transitions=['Pending'=>['Pending','Confirmed','Cancelled'],'Confirmed'=>['Confirmed','Processing','Packed','Cancelled'],'Processing'=>['Processing','Packed','Shipped','Cancelled'],'Packed'=>['Packed','Shipped','Cancelled'],'Shipped'=>['Shipped','Out for delivery','Delivered'],'Out for delivery'=>['Out for delivery','Delivered'],'Delivered'=>['Delivered'],'Cancelled'=>['Cancelled']];
   if (!in_array($status,$transitions[$o['status']],true)) throw new \RuntimeException('This order status transition is not allowed.');
   if ($o['payment_method']==='razorpay' && $o['payment_status']!=='paid' && $status!=='Pending' && $status!=='Cancelled') throw new \RuntimeException('Payment must be verified before fulfilment.');
   if ($status==='Cancelled' && $o['status']!=='Cancelled') {
    if ($o['payment_method']==='razorpay' && $o['razorpay_order_id'] && $o['payment_status']!=='refunded') throw new \RuntimeException('Reconcile this online payment and confirm a full provider refund before cancellation.');
    if (!$o['stock_restored']) {foreach(self::items($id) as $i) {DB::query('UPDATE product_variants SET stock=stock+? WHERE id=?',[$i['quantity'],$i['variant_id']]);DB::insert('inventory_transactions',['variant_id'=>$i['variant_id'],'quantity'=>$i['quantity'],'reason'=>'Cancellation #'.$id]);} DB::update('orders',['stock_restored'=>1],$id);DB::query('DELETE FROM coupon_usage WHERE order_id=?',[$id]);}
   }
   $data=['status'=>$status,'tracking'=>substr($tracking,0,200),'notes'=>$notes,'courier'=>substr($courier,0,100),'tracking_url'=>$trackingUrl,'expected_delivery'=>$eta?:null];
   DB::update('orders',$data,$id);$updated=array_replace($o,$data);
   if($status!==$o['status']) OrderNotice::record($updated,'status:'.$status,'Order '.$status,'Your order is now '.strtolower($status).'.');
   elseif($data['tracking']!==$o['tracking'] || $courier!==($o['courier']??'') || $trackingUrl!==($o['tracking_url']??'') || ($eta?:null)!==($o['expected_delivery']??null)) OrderNotice::record($updated,'tracking:'.bin2hex(random_bytes(8)),'Delivery details updated','Your delivery and tracking details have been updated.');
  });
  OrderNotice::dispatch($id);
 }
 public static function notify(array $order): void {
  OrderNotice::dispatch((int)$order['id']);
 }
}
