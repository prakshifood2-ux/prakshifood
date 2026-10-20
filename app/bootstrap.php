<?php
declare(strict_types=1);
define('ROOT', dirname(__DIR__));
$GLOBALS['config'] = require ROOT.'/config/app.php';
date_default_timezone_set('Asia/Kolkata');
spl_autoload_register(function ($class) {
    if (strpos($class, 'App\\') === 0) {
        $file = ROOT.'/app/'.str_replace('\\', '/', substr($class, 4)).'.php';
        if (is_file($file)) require $file;
    }
});
require __DIR__.'/helpers.php';
if (PHP_SAPI !== 'cli') {
    ini_set('session.use_strict_mode', '1');
    session_name('prakshi_session');
    session_set_cookie_params(['httponly'=>true,'secure'=>str_starts_with(config('base_url'), 'https://'),'samesite'=>'Lax','path'=>'/']);
    session_start();
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    if (!isset($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
