<?php
if (PHP_SAPI!=='cli') exit('CLI only');
require dirname(__DIR__).'/app/bootstrap.php';
use App\Core\DB;
use App\Models\Setting;
$opts=getopt('',['demo','admin-email:','admin-name:']);
$pdo=new PDO('mysql:host='.config('db_host').';port='.config('db_port').';charset=utf8mb4',config('db_user'),config('db_pass'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
if (!preg_match('/^[a-zA-Z0-9_]+$/',config('db_name'))) exit('Invalid database name');
$pdo->exec('CREATE DATABASE IF NOT EXISTS `'.config('db_name').'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$pdo->exec('USE `'.config('db_name').'`');$pdo->exec(file_get_contents(ROOT.'/database/schema.sql'));
if (!config('app_key')) { $local=is_file(ROOT.'/config/local.php')?require ROOT.'/config/local.php':[];$local['app_key']=bin2hex(random_bytes(32));file_put_contents(ROOT.'/config/local.php',"<?php\nreturn ".var_export($local,true).";\n"); }
$defaults=['brand_name'=>'Prakshi Foods','announcement'=>'A little jar. A whole lot of home. • Free shipping on orders above ₹799','shipping'=>'6900','free_shipping'=>'79900','tax_percent'=>'0','cod_enabled'=>'1','low_stock'=>'10','logo'=>'assets/images/logo.jpeg','fssai_image'=>'assets/images/fssai.png','fssai_number'=>'22723614000070','hero_eyebrow'=>'FROM OUR KITCHEN TO YOUR TABLE','hero_heading'=>'Ghar ka swad.\nHar ek bite mein.','hero_description'=>'Some flavours take you home. Discover our thoughtfully crafted pickles, made for everyday meals and memories around the table.','hero_image'=>'assets/images/hero.png','hero_button'=>'Explore our pickles','hero_button_secondary'=>'Build your own combo','story_heading'=>'Good food.\nEven better memories.','story_text'=>'There is something special about a spoonful of achar. A familiar aroma, a favourite recipe, a meal shared with the people you love. Prakshi brings that feeling to your table, one thoughtfully prepared jar at a time.','story_image'=>'assets/images/hero.png','razorpay_enabled'=>'0','razorpay_mode'=>'test','page_about'=>'At Prakshi, we believe everyday meals deserve a little extra flavour. Our collection celebrates the familiar tastes of Indian kitchens. Explore our pantry and find your favourite.','page_faq'=>"How can I place an order?\nBrowse the pantry, choose a pack size and add it to your cart. Sign in when you are ready to add your first jar.\n\nWhere can I find ingredients?\nCheck each product’s details. Contact us if you need information that has not yet been supplied.\n\nHow can I track my order?\nSign in to your account and open the order to see its status and tracking reference."];
foreach($defaults as $k=>$v) DB::query('INSERT IGNORE INTO store_settings VALUES (?,?)',[$k,$v]);
if (isset($opts['demo']) && !DB::one('SELECT id FROM products LIMIT 1')) {
 $cats=['Mango Pickle','Lemon Pickle','Green Chilli Pickle','Red Chilli Pickle','Mixed Pickle','Garlic Pickle','Chutneys & Sauces','Gift Combos'];
 foreach($cats as $i=>$name) { $slug=strtolower(str_replace([' & ',' '],['-','-'],$name));DB::insert('categories',['name'=>$name,'slug'=>$slug,'description'=>'Explore '.$name,'image'=>'assets/images/hero.png','sort_order'=>$i]); }
 $names=['Classic Mango Pickle','Sunshine Lemon Pickle','Fiery Green Chilli','Stuffed Red Chilli','The Mixed Pickle','Rustic Garlic Pickle','Tangy Mango Chutney','The Flavour Collection'];
 $desc=['Tangy, bold & beautifully familiar','A little sunshine with every meal','For those who like a little heat','Big flavour. A fiery favourite.','A delicious meeting of flavours','Rich, aromatic & full of character','The sweet side of tangy','A thoughtful gift for food lovers'];
 foreach($names as $i=>$name) { $pid=DB::insert('products',['category_id'=>$i+1,'name'=>$name,'slug'=>strtolower(str_replace(' ','-',$name)),'short_description'=>$desc[$i],'description'=>'A flavourful addition to your everyday plate. Serve with dal, rice, parathas or your favourite meal. This development catalogue is illustrative; ingredients, packaging and pricing must be confirmed before launch.','ingredients'=>'','allergens'=>'','storage'=>'','image'=>'assets/images/hero.png','featured'=>$i<4?1:0]);foreach(['200 g','500 g'] as $j=>$label) { $price=($j?349:179)+($i*10);$vid=DB::insert('product_variants',['product_id'=>$pid,'sku'=>'PF-'.($i+1).'-'.($j?500:200),'label'=>$label,'price'=>$price*100,'compare_price'=>($price+40)*100,'stock'=>40]);DB::insert('inventory_transactions',['variant_id'=>$vid,'quantity'=>40,'reason'=>'Development seed stock']);} }
 $bid=DB::insert('bundles',['name'=>'Your very own pickle trio','description'=>'Pick any 3 jars and enjoy 10% off your selection.','min_items'=>3,'discount_percent'=>10]);foreach(DB::all('SELECT id FROM products') as $p) DB::insert('bundle_items',['bundle_id'=>$bid,'product_id'=>$p['id']]);
 DB::insert('coupons',['code'=>'WELCOME10','type'=>'percent','value'=>10,'min_total'=>30000,'max_discount'=>10000,'usage_limit'=>100,'per_customer_limit'=>1]);Setting::set('demo_catalogue','1');
}
if (!DB::one('SELECT id FROM admin_users LIMIT 1')) {
 $email=$opts['admin-email']??'admin@prakshi.local';$password=getenv('PRAKSHI_ADMIN_PASSWORD')?:bin2hex(random_bytes(10));
 DB::insert('admin_users',['name'=>$opts['admin-name']??'Store Owner','email'=>$email,'password'=>password_hash($password,PASSWORD_DEFAULT),'role'=>'owner']);
 echo "Admin email: $email\nTemporary password: $password\nChange this password after signing in.\n";
}
echo "Database installed: ".config('db_name')."\n";
