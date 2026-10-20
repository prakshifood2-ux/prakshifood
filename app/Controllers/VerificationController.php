<?php
namespace App\Controllers;
use App\Core\Auth;
use App\Models\{EmailOtp,Admin};
class VerificationController {
 public static function begin(string $kind,int $id): void {
  unset($_SESSION[$kind==='admin'?'admin_id':'user_id'],$_SESSION[$kind.'_otp_verified']);
  $challenge=EmailOtp::issue($kind,$id);session_regenerate_id(true);
  $_SESSION[$kind.'_challenge']=$challenge;
  \redirect($kind==='admin'?'admin/verify':'verify');
 }
 public function show(string $kind): void {
  $challenge=$_SESSION[$kind.'_challenge']??'';$info=EmailOtp::details($kind,$challenge);
  if (!$info) {\flash('Please sign in to request a verification code.','warning');\redirect($kind==='admin'?'admin/login':'login');}
  [$local,$domain]=explode('@',$info['email'],2);$masked=substr($local,0,2).'***@'.$domain;
  \render('store/verify',['title'=>'Verify your email','kind'=>$kind,'maskedEmail'=>$masked,'verifyRoute'=>$kind==='admin'?'admin/verify':'verify','resendRoute'=>$kind==='admin'?'admin/otp/resend':'otp/resend'],$kind==='admin'?'bare':'store');
 }
 public function verify(string $kind): void {
  Auth::throttle('otp-verify-'.$kind,30);$route=$kind==='admin'?'admin/verify':'verify';
  try {$account=EmailOtp::verify($kind,$_SESSION[$kind.'_challenge']??'',\post('otp'));}
  catch (\RuntimeException $e) {\flash($e->getMessage(),'warning');\redirect($route);}
  session_regenerate_id(true);unset($_SESSION[$kind.'_challenge']);$_SESSION[$kind.'_otp_verified']=true;
  if ($kind==='admin') {$_SESSION['admin_id']=$account['id'];$_SESSION['admin_credential']=hash('sha256',$account['password']);Admin::audit('login:otp','Admin signed in with email OTP');\redirect('admin');}
  $_SESSION['user_id']=$account['id'];$_SESSION['customer_credential']=hash('sha256',$account['password']);AuthController::finishLogin((int)$account['id']);
 }
 public function resend(string $kind): void {
  Auth::throttle('otp-send-'.$kind,10);$info=EmailOtp::details($kind,$_SESSION[$kind.'_challenge']??'');
  if (!$info) \redirect($kind==='admin'?'admin/login':'login');
  try {$_SESSION[$kind.'_challenge']=EmailOtp::issue($kind,(int)$info['account_id']);\flash('A new code has been sent. Use the newest email.');}
  catch (\RuntimeException $e) {\flash($e->getMessage(),'warning');}
  \redirect($kind==='admin'?'admin/verify':'verify');
 }
}
