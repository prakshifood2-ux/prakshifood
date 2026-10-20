<?php
namespace App\Models;
use App\Core\DB;
class Cart {
 public static function id(int $uid): int { DB::query('INSERT IGNORE INTO carts (user_id) VALUES (?)',[$uid]);return (int)DB::one('SELECT id FROM carts WHERE user_id=?',[$uid])['id']; }
 public static function items(int $uid): array { return DB::all('SELECT ci.*,v.label,v.price,v.compare_price,v.stock,v.active variant_active,p.active product_active,p.name,p.image,p.slug,p.category_id,p.id product_id,c.active category_active FROM cart_items ci JOIN carts cart ON cart.id=ci.cart_id JOIN product_variants v ON v.id=ci.variant_id JOIN products p ON p.id=v.product_id JOIN categories c ON c.id=p.category_id WHERE cart.user_id=? ORDER BY ci.id',[$uid]); }
 public static function add(int $uid,array $selections,?int $bundleId=null): void {
  DB::transaction(function() use($uid,$selections,$bundleId) {
   $cart=self::id($uid); DB::one('SELECT id FROM carts WHERE id=? FOR UPDATE',[$cart]);
   if (count($selections)>30 || !$selections) throw new \RuntimeException('Choose at least one item.');
   $group=$bundleId?bin2hex(random_bytes(12)):''; $sum=0;
   foreach($selections as $item) {
    $vid=(int)($item['variant_id']??0);$qty=(int)($item['quantity']??1); if ($qty<1 || $qty>50) throw new \RuntimeException('Choose a quantity between 1 and 50.');
    $v=DB::one('SELECT v.*,p.active product_active,c.active category_active FROM product_variants v JOIN products p ON p.id=v.product_id JOIN categories c ON c.id=p.category_id WHERE v.id=?',[$vid]);
    if (!$v || !$v['active'] || !$v['product_active'] || !$v['category_active']) throw new \RuntimeException('This product is no longer available.');
    $inCart=(int)DB::one('SELECT COALESCE(SUM(quantity),0) n FROM cart_items WHERE cart_id=? AND variant_id=?',[$cart,$vid])['n'];
    if ($inCart+$qty>$v['stock']) throw new \RuntimeException('There is not enough stock of '.$v['label'].'.');
    if ($bundleId && !DB::one('SELECT * FROM bundle_items WHERE bundle_id=? AND product_id=?',[$bundleId,$v['product_id']])) throw new \RuntimeException('This product is not eligible for the combo.');
    $existing=$bundleId?null:DB::one("SELECT id,quantity FROM cart_items WHERE cart_id=? AND variant_id=? AND bundle_group=''",[$cart,$vid]);
    if ($existing) DB::update('cart_items',['quantity'=>$existing['quantity']+$qty],(int)$existing['id']);
    else DB::insert('cart_items',['cart_id'=>$cart,'variant_id'=>$vid,'quantity'=>$qty,'bundle_id'=>$bundleId,'bundle_group'=>$group]);
    $sum+=$qty;
   }
   if ($bundleId) { $b=self::bundle($bundleId);if (!$b || $sum<$b['min_items']) throw new \RuntimeException('Choose the minimum number of jars for this combo.'); }
  });
 }
 public static function bundle(int $id): ?array { return DB::one('SELECT * FROM bundles WHERE id=? AND active=1 AND (starts_at IS NULL OR starts_at<=NOW()) AND (ends_at IS NULL OR ends_at>=NOW())',[$id]); }
 public static function totals(int $uid,bool $lock=false): array {
  $items=self::items($uid);$subtotal=0;$bundleDiscount=0;$groups=[];
  foreach($items as $i) { $line=$i['price']*$i['quantity'];$subtotal+=$line;if ($i['bundle_group']) $groups[$i['bundle_group']][]=$i; }
  foreach($groups as $group) { $b=self::bundle((int)$group[0]['bundle_id']);if (!$b) continue;$n=0;$sum=0;$valid=true;foreach($group as $i){$n+=$i['quantity'];$sum+=$i['price']*$i['quantity'];if (!DB::one('SELECT * FROM bundle_items WHERE bundle_id=? AND product_id=?',[$b['id'],$i['product_id']])) $valid=false;} if ($valid && $n>=$b['min_items']) $bundleDiscount += $b['fixed_price']?max(0,$sum-$b['fixed_price']):(int)round($sum*min(100,$b['discount_percent'])/100); }
  $cart=DB::one('SELECT * FROM carts WHERE user_id=?',[$uid]);$code=$cart['coupon']??'';$coupon=null;$couponDiscount=0;$error='';
  if ($code) {
   $coupon=DB::one('SELECT * FROM coupons WHERE code=?'.($lock?' FOR UPDATE':''),[$code]);
   if (!$coupon || !$coupon['active'] || ($coupon['starts_at'] && strtotime($coupon['starts_at'])>time()) || ($coupon['ends_at'] && strtotime($coupon['ends_at'])<time())) $error='This coupon is unavailable or expired.';
   elseif ($subtotal-$bundleDiscount<$coupon['min_total']) $error='The coupon minimum spend has not been reached.';
   else {
    $usage=DB::one('SELECT COUNT(*) total,COALESCE(SUM(user_id=?),0) personal FROM coupon_usage WHERE coupon_id=?',[$uid,$coupon['id']]);
    if ($usage['total']>=$coupon['usage_limit'] || $usage['personal']>=$coupon['per_customer_limit']) $error='Coupon usage limit reached.';
    else { $eligible=0; foreach($items as $i) if (!$i['bundle_group'] && (!$coupon['product_id'] || $coupon['product_id']==$i['product_id']) && (!$coupon['category_id'] || $coupon['category_id']==$i['category_id'])) $eligible+=$i['price']*$i['quantity'];
     $couponDiscount=$coupon['type']==='percent'?(int)round($eligible*min(100,$coupon['value'])/100):min($eligible,$coupon['value']); if ($coupon['max_discount']) $couponDiscount=min($couponDiscount,$coupon['max_discount']); if (!$eligible) $error='No eligible items for this coupon. Combo discounts cannot be combined with coupons.';
    }
   }
  }
  $discount=min($subtotal,$bundleDiscount+$couponDiscount);$net=$subtotal-$discount;
  $shipping=$items && $net<(int)Setting::get('free_shipping','79900')?(int)Setting::get('shipping','6900'):0;
  $tax=(int)round($net*(float)Setting::get('tax_percent','0')/100);
  return compact('items','subtotal','discount','shipping','tax','coupon','code','error')+['total'=>$net+$shipping+$tax];
 }
}
