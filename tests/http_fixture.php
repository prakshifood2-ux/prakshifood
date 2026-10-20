<?php
if(PHP_SAPI!=='cli')exit('CLI only');
require dirname(__DIR__).'/app/bootstrap.php';
use App\Core\DB;
$action=$argv[1]??'';$name=getenv('PRAKSHI_DB_NAME');if(!preg_match('/^prakshi_http_[a-f0-9]{12}$/',$name?:''))exit('Invalid test database');
$pdo=new PDO('mysql:host='.config('db_host').';port='.config('db_port'),config('db_user'),config('db_pass'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
if($action==='drop'){$pdo->exec('DROP DATABASE IF EXISTS `'.$name.'`');exit;}
$pdo->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4');DB::connection()->exec(file_get_contents(ROOT.'/database/schema.sql'));
$source=require ROOT.'/config/app.php';$realDb=is_file(ROOT.'/config/local.php')?(require ROOT.'/config/local.php')['db_name']??'prakshi_foods':'prakshi_foods';
if(!preg_match('/^[a-zA-Z0-9_]+$/',$realDb))exit('Invalid source database');
foreach(['categories','products','product_variants','store_settings','bundles','bundle_items','coupons'] as $table)DB::query('INSERT INTO `'.$table.'` SELECT * FROM `'.$realDb.'`.`'.$table.'`');
DB::insert('admin_users',['name'=>'Test Owner','email'=>'owner@example.test','password'=>password_hash(getenv('TEST_ADMIN_PASSWORD'),PASSWORD_DEFAULT),'role'=>'owner']);
DB::insert('admin_users',['name'=>'Test Manager','email'=>'manager@example.test','password'=>password_hash(getenv('TEST_ADMIN_PASSWORD'),PASSWORD_DEFAULT),'role'=>'manager']);
echo 'Test fixture ready';
