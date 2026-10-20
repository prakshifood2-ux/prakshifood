<?php
if(PHP_SAPI!=='cli')exit('CLI only');
require dirname(__DIR__).'/app/bootstrap.php';
use App\Core\DB;
DB::query("ALTER TABLE orders MODIFY status ENUM('Pending','Confirmed','Processing','Packed','Shipped','Out for delivery','Delivered','Cancelled') NOT NULL DEFAULT 'Pending'");
foreach(['courier'=>"VARCHAR(100) NOT NULL DEFAULT ''",'tracking_url'=>"VARCHAR(500) NOT NULL DEFAULT ''",'expected_delivery'=>'DATE NULL'] as $field=>$definition) if(!DB::one('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=\'orders\' AND COLUMN_NAME=?',[$field])) DB::query('ALTER TABLE orders ADD '.$field.' '.$definition);
DB::connection()->exec(file_get_contents(ROOT.'/database/fulfilment.sql'));
echo "Order tracking, invoice and email queue schema ready. Existing orders preserved; no historical emails sent.\n";
