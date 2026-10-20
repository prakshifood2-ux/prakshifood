<?php
if(PHP_SAPI!=='cli')exit('CLI only');
require dirname(__DIR__).'/app/bootstrap.php';
$rows=App\Core\DB::all("SELECT DISTINCT order_id FROM order_emails WHERE state IN ('pending','failed') AND attempts<5 AND (attempted_at IS NULL OR attempted_at<DATE_SUB(NOW(),INTERVAL 5 MINUTE)) LIMIT 50");
foreach($rows as $row)App\Models\OrderNotice::dispatch((int)$row['order_id'],true);
echo count($rows)." order email queues processed.\n";
