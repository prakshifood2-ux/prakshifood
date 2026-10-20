<?php
if (PHP_SAPI!=='cli') exit('CLI only');
require dirname(__DIR__).'/app/bootstrap.php';
try {
 $admin=App\Core\DB::one('SELECT id FROM admin_users WHERE email=? AND active=1',[config('support_email')]);
 if (!$admin) throw new RuntimeException('Admin account not found.');
 App\Models\EmailOtp::issue('admin',(int)$admin['id']);
 echo "Gmail SMTP accepted the admin OTP email for ".config('support_email').". Inbox delivery must be confirmed by the recipient.\n";
} catch (Throwable $e) {fwrite(STDERR,$e->getMessage()."\n");exit(1);}
