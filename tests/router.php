<?php
// PHP's local test server only. Never use as a public production entry point.
$path=rawurldecode(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH));
if(str_starts_with($path,'/public/assets/') || str_starts_with($path,'/public/uploads/')) {if(is_file(dirname(__DIR__).$path)) return false;}
require dirname(__DIR__).'/public/index.php';
