<?php
namespace App\Core;
class Auth {
 public static function user(): ?array {
  if (empty($_SESSION['customer_otp_verified']) || !isset($_SESSION['user_id'])) return null;
  $u=DB::one('SELECT id,name,email,mobile,active,password,email_verified_at FROM users WHERE id=? AND active=1 AND email_verified_at IS NOT NULL',[$_SESSION['user_id']]);
  if (!$u || !hash_equals(hash('sha256',$u['password']),$_SESSION['customer_credential']??'')) return null;
  unset($u['password']);return $u;
 }
 public static function admin(): ?array {
  if (empty($_SESSION['admin_otp_verified']) || !isset($_SESSION['admin_id'])) return null;
  $a=DB::one('SELECT id,name,email,role,password FROM admin_users WHERE id=? AND active=1',[$_SESSION['admin_id']]);
  if (!$a || !hash_equals(hash('sha256',$a['password']),$_SESSION['admin_credential']??'')) return null;
  unset($a['password']);return $a;
 }
 public static function customer(): array { $u=self::user(); if (!$u) { \flash('Please sign in to continue.','warning'); \redirect('login'); } return $u; }
 public static function protect(string $role=''): array { $a=self::admin(); if (!$a) \redirect('admin/login'); if ($role && $a['role']!==$role) { http_response_code(403); exit('Administrator permission required.'); } return $a; }
 public static function csrf(): void { if (!hash_equals($_SESSION['csrf']??'', (string)($_POST['_token']??$_SERVER['HTTP_X_CSRF_TOKEN']??''))) throw new \RuntimeException('Your session expired. Refresh the page and try again.'); }
 public static function throttle(string $scope,int $limit=12): void {
  $key=hash('sha256',$scope.'|'.($_SERVER['REMOTE_ADDR']??'cli'));
  DB::query('INSERT INTO rate_limits (bucket,attempts,expires_at) VALUES (?,1,DATE_ADD(NOW(), INTERVAL 15 MINUTE)) ON DUPLICATE KEY UPDATE attempts=IF(expires_at<NOW(),1,attempts+1),expires_at=IF(expires_at<NOW(),DATE_ADD(NOW(),INTERVAL 15 MINUTE),expires_at)',[$key]);
  if ((int)DB::one('SELECT attempts FROM rate_limits WHERE bucket=?',[$key])['attempts']>$limit) throw new \RuntimeException('Too many attempts. Please try again in 15 minutes.');
 }
}
