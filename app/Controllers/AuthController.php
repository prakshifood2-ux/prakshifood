<?php
namespace App\Controllers;
use App\Core\{Auth,DB,Mailer};
use App\Models\Cart;
class AuthController {
 public function form(string $mode='login'): void { \render('store/auth',compact('mode')+['title'=>ucfirst($mode)]); }
 public function submit(string $mode): void {
  Auth::throttle('auth');$email=strtolower(\post('email'));$password=\post('password');
  if (!filter_var($email,FILTER_VALIDATE_EMAIL)) throw new \RuntimeException('Enter a valid email address.');
  if ($mode==='register') {
   \require_fields(['name','email','password']);if (strlen($password)<10) throw new \RuntimeException('Use a password of at least 10 characters.');if ($password!==\post('password_confirmation')) throw new \RuntimeException('Passwords do not match.');if (!\post('consent')) throw new \RuntimeException('Please accept the terms and privacy policy.');
   if (DB::one('SELECT id FROM users WHERE email=?',[$email])) throw new \RuntimeException('An account with this email already exists.');
   $id=DB::insert('users',['name'=>substr(\post('name'),0,120),'email'=>$email,'mobile'=>substr(\post('mobile'),0,20),'password'=>password_hash($password,PASSWORD_DEFAULT)]);
  } else { $u=DB::one('SELECT * FROM users WHERE email=? AND active=1',[$email]);if (!$u || !password_verify($password,$u['password'])) throw new \RuntimeException('Email or password is incorrect.');$id=$u['id']; }
  VerificationController::begin('customer',(int)$id);
 }
 public static function finishLogin(int $id): void {
  if (isset($_SESSION['pending_cart'])) { $pending=$_SESSION['pending_cart'];unset($_SESSION['pending_cart']);try {Cart::add((int)$id,$pending['items'],$pending['bundle_id']);\flash('Welcome! Your selected items have been added to the cart.');} catch (\RuntimeException $e) {\flash($e->getMessage(),'warning');} \redirect('cart'); }
  \redirect('account');
 }
 public function logout(): void { unset($_SESSION['user_id'],$_SESSION['pending_cart'],$_SESSION['customer_otp_verified'],$_SESSION['customer_credential'],$_SESSION['customer_challenge']);session_regenerate_id(true);\redirect(''); }
 public function forgot(): void {
  Auth::throttle('reset',5);$u=DB::one('SELECT * FROM users WHERE email=? AND active=1',[strtolower(\post('email'))]);
  if ($u) { $token=bin2hex(random_bytes(32));DB::query('DELETE FROM password_resets WHERE user_id=?',[$u['id']]);DB::insert('password_resets',['user_id'=>$u['id'],'token_hash'=>hash('sha256',$token),'expires_at'=>date('Y-m-d H:i:s',time()+3600)]);Mailer::send($u['email'],'Reset your Prakshi password','Reset your password within one hour: '.\url('reset',['token'=>$token])); }
  \flash('If that account exists, a password reset link has been sent.');\redirect('forgot');
 }
 public function reset(): void {
  Auth::throttle('reset-submit');$p=\post('password');if (strlen($p)<10 || $p!==\post('password_confirmation')) throw new \RuntimeException('Passwords must match and contain at least 10 characters.');
  DB::transaction(function() use($p) { $row=DB::one('SELECT * FROM password_resets WHERE token_hash=? AND expires_at>NOW() FOR UPDATE',[hash('sha256',\post('token'))]);if (!$row) throw new \RuntimeException('This reset link has expired.');DB::update('users',['password'=>password_hash($p,PASSWORD_DEFAULT)],(int)$row['user_id']);DB::query('DELETE FROM password_resets WHERE user_id=?',[$row['user_id']]); });
  \flash('Password updated. Please sign in.');\redirect('login');
 }
}
