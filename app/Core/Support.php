<?php
namespace App\Core;
class Support {
 public static function contact(string $name,string $email,string $message,int $id): bool {
  try {Mailer::send(\config('support_email'),'New Prakshi support request #'.$id,"A customer submitted a support request.\n\nName: $name\nReply to: $email\n\n$message\n\nYou can also view this saved message in Store Studio.");return true;}
  catch (\Throwable $e) {error_log(date('c')." Support email notification failed for message #$id; message remains in admin inbox.\n",3,ROOT.'/storage/logs/mail.log');return false;}
 }
 public static function incident(string $type,string $route): void {
  // One alert per 15 minutes. Never include request data, passwords, OTPs or stack traces.
  $f=fopen(ROOT.'/storage/logs/incident-alert.lock','c+');if (!$f) return;
  if (!flock($f,LOCK_EX)) {fclose($f);return;}
  $last=(int)stream_get_contents($f);if ($last>time()-900) {flock($f,LOCK_UN);fclose($f);return;}
  rewind($f);ftruncate($f,0);fwrite($f,(string)time());flock($f,LOCK_UN);fclose($f);
  try {Mailer::send(\config('support_email'),'Prakshi store: technical issue detected',"A server error occurred at ".date('c').".\nError category: $type\nRoute: ".substr(preg_replace('/[^a-zA-Z0-9\/-]/','',$route),0,80)."\n\nPlease review the private application log. No customer information or credentials are included in this alert.");}
  catch (\Throwable $ignored) {error_log('Prakshi incident email could not be sent.');}
 }
}
