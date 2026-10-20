<?php
namespace App\Controllers;
use App\Core\{Auth,DB,Upload};
use App\Models\{Catalog,Cart,Order,Payment,Address};
class StoreController {
 public function home(): void { \render('store/home',['title'=>'Handcrafted flavours. Heartfelt memories.','products'=>array_slice(Catalog::products(['featured'=>1]),0,4),'categories'=>Catalog::categories(),'reviews'=>Catalog::reviews()]); }
 public function shop(): void { \render('store/shop',['title'=>'The pickle pantry','products'=>Catalog::products($_GET),'categories'=>Catalog::categories()]); }
 public function product(): void { $p=Catalog::product((string)($_GET['slug']??''));if (!$p) {$this->missing();return;}\render('store/product',['title'=>$p['seo_title']?:$p['name'],'description'=>$p['seo_description']?:$p['short_description'],'product'=>$p,'variants'=>Catalog::variants((int)$p['id']),'images'=>DB::all('SELECT * FROM product_images WHERE product_id=?',[$p['id']]),'reviews'=>Catalog::reviews((int)$p['id']),'related'=>array_slice(Catalog::products(),0,4)]); }
 public function bundle(): void { \render('store/bundle',['title'=>'A little of everything','bundles'=>Catalog::bundles()]); }
 public function add(): void {
  $items=isset($_POST['items'])?json_decode(\post('items'),true):[['variant_id'=>(int)\post('variant_id'),'quantity'=>(int)\post('quantity','1')]];
  if (!is_array($items)) throw new \RuntimeException('Invalid selection.');$bid=(int)\post('bundle_id')?:null;
  if (!Auth::user()) { $_SESSION['pending_cart']=['items'=>$items,'bundle_id'=>$bid];header('Content-Type: application/json');echo json_encode(['auth'=>true]);return; }
  Cart::add((int)Auth::user()['id'],$items,$bid);header('Content-Type: application/json');echo json_encode(['success'=>true,'redirect'=>\url('cart')]);
 }
 public function cart(): void { $u=Auth::customer();\render('store/cart',['title'=>'Your basket','totals'=>Cart::totals((int)$u['id'])]); }
 public function updateCart(): void {
  $u=Auth::customer();$cid=Cart::id((int)$u['id']);$id=(int)\post('id');
  DB::transaction(function() use($cid,$id) { DB::one('SELECT id FROM carts WHERE id=? FOR UPDATE',[$cid]);$item=DB::one('SELECT ci.*,v.stock FROM cart_items ci JOIN product_variants v ON v.id=ci.variant_id WHERE ci.id=? AND cart_id=?',[$id,$cid]);if (!$item) throw new \RuntimeException('Cart item not found.');
   if (\post('action')==='remove') {if ($item['bundle_group']) DB::query('DELETE FROM cart_items WHERE cart_id=? AND bundle_group=?',[$cid,$item['bundle_group']]);else DB::query('DELETE FROM cart_items WHERE id=?',[$id]);}
   else {if ($item['bundle_group']) throw new \RuntimeException('Remove this combo and rebuild it to change its contents.');$q=(int)\post('quantity');$other=(int)DB::one('SELECT COALESCE(SUM(quantity),0) n FROM cart_items WHERE cart_id=? AND variant_id=? AND id<>?',[$cid,$item['variant_id'],$id])['n'];if ($q<1 || $q>50 || $q+$other>$item['stock']) throw new \RuntimeException('Requested quantity is unavailable.');DB::update('cart_items',['quantity'=>$q],$id);}
  });\redirect('cart');
 }
 public function coupon(): void { $u=Auth::customer();$cid=Cart::id((int)$u['id']);DB::update('carts',['coupon'=>strtoupper(substr(\post('coupon'),0,60))],$cid);$t=Cart::totals((int)$u['id']);if ($t['error']) \flash($t['error'],'warning');\redirect('cart'); }
 public function checkout(): void {
  $u=Auth::customer();$t=Cart::totals((int)$u['id']);if (!$t['items']) \redirect('cart');
  if (!isset($_SESSION['checkout_key'])) $_SESSION['checkout_key']=bin2hex(random_bytes(24));
  $saved=Address::defaultFor((int)$u['id']);
  \render('store/checkout',['title'=>'Checkout','totals'=>$t,'savedAddress'=>$saved,'editAddress'=>!$saved || ($_GET['edit_address']??'')==='1']);
 }
 public function saveAddress(): void {
  $u=Auth::customer();Address::save((int)$u['id'],$_POST);
  \flash('Address saved. We will use it automatically for your next orders.');\redirect('checkout');
 }
 public function place(): void {
  $u=Auth::customer();$address=Address::forCheckout((int)$u['id'],$_POST);
  $o=Order::create((int)$u['id'],$address,\post('payment_method'),\post('checkout_key'));unset($_SESSION['checkout_key']);Order::notify($o);\redirect('order',['id'=>$o['id']]);
 }
 public function account(): void { $u=Auth::customer();\render('store/account',['title'=>'Your little corner','orders'=>DB::all('SELECT * FROM orders WHERE user_id=? ORDER BY id DESC LIMIT 100',[$u['id']]),'addresses'=>DB::all('SELECT * FROM addresses WHERE user_id=? ORDER BY id DESC LIMIT 10',[$u['id']]),'myReviews'=>DB::all('SELECT r.*,p.name FROM reviews r JOIN products p ON p.id=r.product_id WHERE user_id=?',[$u['id']])]); }
 public function profile(): void { $u=Auth::customer();\require_fields(['name']);$data=['name'=>substr(\post('name'),0,120),'mobile'=>substr(\post('mobile'),0,20)];if (\post('new_password')) { $row=DB::one('SELECT password FROM users WHERE id=?',[$u['id']]);if (!password_verify(\post('current_password'),$row['password']) || strlen(\post('new_password'))<10) throw new \RuntimeException('Check your current password and use at least 10 characters for the new password.');$data['password']=password_hash(\post('new_password'),PASSWORD_DEFAULT); }DB::update('users',$data,(int)$u['id']);\flash('Profile updated.');\redirect('account'); }
 public function deleteAddress(): void { $u=Auth::customer();DB::query('DELETE FROM addresses WHERE id=? AND user_id=?',[(int)\post('id'),$u['id']]);\redirect('account'); }
 public function order(): void { $u=Auth::customer();$o=Order::own((int)($_GET['id']??0),(int)$u['id']);\render('store/order',['title'=>$o['number'],'order'=>$o,'items'=>Order::items((int)$o['id'])]); }
 public function reorder(): void { $u=Auth::customer();$o=Order::own((int)\post('id'),(int)$u['id']);Cart::add((int)$u['id'],array_map(fn($i)=>['variant_id'=>$i['variant_id'],'quantity'=>$i['quantity']],Order::items((int)$o['id'])));\flash('Available items added at current prices.');\redirect('cart'); }
 public function review(): void { $u=Auth::customer();$pid=(int)\post('product_id');$eligible=DB::one("SELECT oi.id FROM order_items oi JOIN orders o ON o.id=oi.order_id WHERE o.user_id=? AND oi.product_id=? AND o.status='Delivered'",[$u['id'],$pid]);if (!$eligible) throw new \RuntimeException('Reviews are available after your order is delivered.');$rating=(int)\post('rating');if ($rating<1 || $rating>5 || strlen(\post('body'))<10) throw new \RuntimeException('Select a rating and write at least 10 characters.');$image=Upload::image('review_image')??'';DB::query("INSERT INTO reviews (user_id,product_id,rating,body,image) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE rating=VALUES(rating),body=VALUES(body),image=VALUES(image),status='pending'",[$u['id'],$pid,$rating,substr(\post('body'),0,3000),$image]);\flash('Thank you. Your review is awaiting moderation.');\redirect('account'); }
 public function page(): void { $slug=(string)($_GET['slug']??'about');$pages=['about'=>'Our story','contact'=>'Let’s talk food','faq'=>'Frequently asked questions','shipping'=>'Shipping policy','returns'=>'Returns & refunds','privacy'=>'Privacy policy','terms'=>'Terms & conditions','reviews'=>'From our community'];if (!isset($pages[$slug])) {$this->missing();return;}\render('store/page',['title'=>$pages[$slug],'slug'=>$slug,'reviews'=>$slug==='reviews'?Catalog::reviews():[]]); }
 public function contact(): void { Auth::throttle('contact',5);\require_fields(['name','email','message']);if (!filter_var(\post('email'),FILTER_VALIDATE_EMAIL)) throw new \RuntimeException('Enter a valid email.');$id=DB::insert('contact_messages',['name'=>substr(\post('name'),0,120),'email'=>\post('email'),'message'=>substr(\post('message'),0,5000)]);\App\Core\Support::contact(substr(\post('name'),0,120),\post('email'),substr(\post('message'),0,5000),$id);\flash('Your message has been received.');\redirect('page',['slug'=>'contact']); }
 public function newsletter(): void { Auth::throttle('newsletter',10);if (!filter_var(\post('email'),FILTER_VALIDATE_EMAIL) || !\post('consent')) throw new \RuntimeException('Enter your email and agree to receive updates.');DB::query('INSERT IGNORE INTO newsletter_subscribers (email) VALUES (?)',[strtolower(\post('email'))]);\flash('You’re on the list. Thank you!');\redirect(''); }
 public function paymentStart(): void { $u=Auth::customer();header('Content-Type: application/json');echo json_encode(Payment::start((int)\post('id'),(int)$u['id'])); }
 public function paymentVerify(): void { $u=Auth::customer();Payment::verify((int)\post('id'),(int)$u['id'],\post('razorpay_payment_id'),\post('razorpay_signature'));header('Content-Type: application/json');echo json_encode(['success'=>true]); }
 public function missing(): void { http_response_code(404);\render('store/error',['title'=>'A little off the beaten path','message'=>'We couldn’t find this page. Let’s get you back to something delicious.']); }
}
