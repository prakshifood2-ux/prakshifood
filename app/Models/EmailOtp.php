<?php
namespace App\Models;
use App\Core\{DB,Mailer};
class EmailOtp {
 private static function table(string $kind): string {
  if (!in_array($kind,['customer','admin'],true)) throw new \RuntimeException('Invalid verification request.');
  return $kind==='admin'?'admin_users':'users';
 }
 private static function digest(string $challenge,string $code): string {
  if (!\config('app_key')) throw new \RuntimeException('Application security key is not configured.');
  return hash_hmac('sha256',$challenge.'|'.$code,\config('app_key'));
 }
 public static function issue(string $kind,int $id): string {
  $table=self::table($kind);$challenge=bin2hex(random_bytes(32));$code=(string)random_int(100000,999999);
  $account=DB::transaction(function() use($table,$kind,$id,$challenge,$code) {
   $account=DB::one('SELECT * FROM '.$table.' WHERE id=? AND active=1 FOR UPDATE',[$id]);if (!$account) throw new \RuntimeException('Account is unavailable. Please sign in again.');
   $old=DB::one('SELECT * FROM email_otps WHERE kind=? AND account_id=? FOR UPDATE',[$kind,$id]);
   if ($old && strtotime($old['sent_at'])>time()-60) throw new \RuntimeException('Please wait 60 seconds before requesting another code.');
   $sameWindow=$old && strtotime($old['window_start'])>time()-3600;
   if ($sameWindow && $old['send_count']>=5) throw new \RuntimeException('Too many verification emails. Please try again in one hour.');
   $data=[$challenge,$account['email'],self::digest($challenge,$code),hash('sha256',$account['password']),date('Y-m-d H:i:s',time()+600),date('Y-m-d H:i:s'),$sameWindow?$old['window_start']:date('Y-m-d H:i:s'),$sameWindow?$old['send_count']+1:1,$kind,$id];
   if ($old) DB::query('UPDATE email_otps SET challenge_id=?,email=?,code_hash=?,credential_hash=?,expires_at=?,sent_at=?,window_start=?,send_count=?,attempts=0,consumed_at=NULL WHERE kind=? AND account_id=?',$data);
   else DB::query('INSERT INTO email_otps (challenge_id,email,code_hash,credential_hash,expires_at,sent_at,window_start,send_count,kind,account_id) VALUES (?,?,?,?,?,?,?,?,?,?)',$data);
   return $account;
  });
  try { Mailer::send($account['email'],'Your Prakshi '.($kind==='admin'?'admin login':'email verification').' code',"Hello ".$account['name'].",\n\nYour ".($kind==='admin'?'admin login':'email verification')." OTP is: $code\n\nThis code expires in 10 minutes and works only once in the browser where you requested it. Never share this code.\n\nIf you did not request it, do not enter or share the code. For help, contact ".\config('support_email').".\n\nPrakshi Foods"); }
  catch (\Throwable $e) { DB::query('UPDATE email_otps SET consumed_at=NOW() WHERE challenge_id=?',[$challenge]);throw $e; }
  return $challenge;
 }
 public static function details(string $kind,string $challenge): ?array { self::table($kind);return DB::one('SELECT account_id,email,expires_at,consumed_at,attempts FROM email_otps WHERE kind=? AND challenge_id=?',[$kind,$challenge]); }
 public static function verify(string $kind,string $challenge,string $code): array {
  $table=self::table($kind);
  // Commit failed-attempt counters before reporting an error to the controller.
  $result=DB::transaction(function() use($kind,$challenge,$code,$table) {
   $otp=DB::one('SELECT * FROM email_otps WHERE kind=? AND challenge_id=? FOR UPDATE',[$kind,$challenge]);
   if (!$otp || $otp['consumed_at'] || strtotime($otp['expires_at'])<=time() || $otp['attempts']>=5) return ['error'=>'This code has expired, was already used, or is locked. Request a new code.'];
   if (!preg_match('/^[0-9]{6}$/',$code) || !hash_equals($otp['code_hash'],self::digest($challenge,$code))) {DB::query('UPDATE email_otps SET attempts=attempts+1 WHERE challenge_id=?',[$challenge]);return ['error'=>'Incorrect code. '.max(0,4-$otp['attempts']).' attempts remaining.'];}
   $account=DB::one('SELECT * FROM '.$table.' WHERE id=? AND active=1',[$otp['account_id']]);
   if (!$account || $account['email']!==$otp['email'] || !hash_equals($otp['credential_hash'],hash('sha256',$account['password']))) {DB::query('UPDATE email_otps SET consumed_at=NOW() WHERE challenge_id=?',[$challenge]);return ['error'=>'Your account changed. Please sign in again.'];}
   DB::query('UPDATE email_otps SET consumed_at=NOW() WHERE challenge_id=?',[$challenge]);
   if ($kind==='customer') DB::query('UPDATE users SET email_verified_at=COALESCE(email_verified_at,NOW()) WHERE id=?',[$account['id']]);
   return ['account'=>$account];
  });
  if (isset($result['error'])) throw new \RuntimeException($result['error']);return $result['account'];
 }
}
