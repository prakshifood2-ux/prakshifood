<?php
namespace App\Models;
use App\Core\{DB,Pdf};
class Invoice {
 /** Call within the owning order transaction or use forOrder(). */
 public static function ensure(array $order): array {
  $existing=DB::one('SELECT * FROM invoices WHERE order_id=?',[$order['id']]);if($existing)return $existing;
  $snapshot=['order'=>$order,'items'=>Order::items((int)$order['id']),'seller'=>['name'=>Setting::get('brand_name','Prakshi Foods'),'email'=>Setting::get('email'),'address'=>Setting::get('address'),'phone'=>Setting::get('phone'),'fssai'=>Setting::get('fssai_number')]];
  $id=DB::insert('invoices',['order_id'=>$order['id'],'number'=>'INV-'.$order['number'],'snapshot'=>json_encode($snapshot,JSON_UNESCAPED_UNICODE)]);
  return DB::one('SELECT * FROM invoices WHERE id=?',[$id]);
 }
 public static function forOrder(int $id): array {return DB::transaction(function()use($id){$o=DB::one('SELECT * FROM orders WHERE id=? FOR UPDATE',[$id]);if(!$o)throw new \RuntimeException('Order not found.');return self::ensure($o);});}
 public static function pdf(array $invoice): string {
  $s=json_decode($invoice['snapshot'],true);$o=$s['order'];$a=json_decode($o['address_json'],true);$seller=$s['seller'];$money=fn($v)=>'INR '.number_format($v/100,2,'.',',');
  $lines=[$seller['name'],'SALES INVOICE',$invoice['number'],'Issued: '.$invoice['created_at'],'Order: '.$o['number'],''];
  foreach(['address','email','phone'] as $f)if($seller[$f])$lines[]=$seller[$f];if($seller['fssai'])$lines[]='FSSAI licence: '.$seller['fssai'];
  $lines=array_merge($lines,['','BILL / DELIVER TO',$a['name'],$a['address'],$a['city'].', '.$a['state'].' '.$a['pincode'],$a['email'].' | '.$a['mobile'],'','ITEMS']);
  foreach($s['items'] as $i){$lines[]=$i['name'].' - '.$i['variant'];$lines[]='  '.$i['quantity'].' x '.$money($i['price']).' = '.$money($i['price']*$i['quantity']);}
  $lines=array_merge($lines,['','Subtotal: '.$money($o['subtotal']),'Discount: -'.$money($o['discount']),'Delivery: '.$money($o['shipping']),'Tax recorded on order: '.$money($o['tax']),'TOTAL: '.$money($o['total']),'','Payment method: '.strtoupper($o['payment_method']),'Payment status at invoice issue: '.$o['payment_status'],'An invoice does not confirm receipt of payment.','Please check your account for current payment and delivery status.','','Commercial sales invoice. Not a GST tax invoice.','Thank you for choosing '.$seller['name'].'.']);
  $wrapped=[];foreach($lines as $line)foreach(explode("\n",wordwrap(str_replace(["\r","\n"],' ',$line),83,"\n",true)) as $part)$wrapped[]=$part;
  return Pdf::document($wrapped);
 }
 public static function attachment(array $invoice): array {return ['name'=>$invoice['number'].'.pdf','type'=>'application/pdf','content'=>self::pdf($invoice)];}
}
