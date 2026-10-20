<?php
if (PHP_SAPI!=='cli') exit('CLI only');
require dirname(__DIR__).'/app/bootstrap.php';
use App\Core\DB;
if (!DB::one("SHOW COLUMNS FROM users LIKE 'email_verified_at'")) DB::query('ALTER TABLE users ADD email_verified_at DATETIME NULL');
DB::connection()->exec(file_get_contents(ROOT.'/database/otp.sql'));
echo "Email OTP schema is ready.\n";
