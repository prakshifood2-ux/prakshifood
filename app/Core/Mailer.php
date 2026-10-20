<?php
namespace App\Core;
class Mailer {
 public static function send(string $to,string $subject,string $body,array $attachments=[]): void {
  $from=\config('mail_from');
  if (!filter_var($to,FILTER_VALIDATE_EMAIL) || !filter_var($from,FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/',$to.$from.$subject)) throw new \RuntimeException('Invalid email details.');
  if (\config('mail_mode')==='smtp') { self::smtp($to,$subject,$body,$attachments);return; }
  if (\config('mail_mode')==='mail') { [$mimeHeaders,$mimeBody]=self::mime($body,$attachments);if (!mail($to,$subject,$mimeBody,'From: '.$from."\r\nMIME-Version: 1.0\r\n".implode("\r\n",$mimeHeaders))) throw new \RuntimeException('Email could not be sent. Please try again shortly.'); }
  elseif (\config('mail_mode')==='log') {
   $dir=\config('mail_log_dir');if (!is_dir($dir) && !mkdir($dir,0700,true)) throw new \RuntimeException('Email outbox is unavailable.');
   if (file_put_contents($dir.'/'.date('Ymd-His').'-'.bin2hex(random_bytes(5)).'.txt',"To: $to\nSubject: $subject\n\n$body")===false) throw new \RuntimeException('Email could not be saved.');
   if($attachments){[$headers,$mimeBody]=self::mime($body,$attachments);file_put_contents($dir.'/'.bin2hex(random_bytes(12)).'.eml',"To: $to\r\nSubject: $subject\r\nMIME-Version: 1.0\r\n".implode("\r\n",$headers)."\r\n\r\n".$mimeBody);}
  } else throw new \RuntimeException('Email delivery has not been configured.');
 }
 public static function mime(string $body,array $attachments): array {
  if(!$attachments)return [['Content-Type: text/plain; charset=UTF-8','Content-Transfer-Encoding: base64'],chunk_split(base64_encode($body),76,"\r\n")];
  $boundary='prakshi_'.bin2hex(random_bytes(20));$payload='--'.$boundary."\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n".chunk_split(base64_encode($body),76,"\r\n");
  foreach($attachments as $a){if(!preg_match('/^[A-Za-z0-9._-]+$/',$a['name']) || $a['type']!=='application/pdf')throw new \RuntimeException('Invalid email attachment.');$payload.='--'.$boundary."\r\nContent-Type: application/pdf\r\nContent-Disposition: attachment; filename=\"".$a['name']."\"\r\nContent-Transfer-Encoding: base64\r\n\r\n".chunk_split(base64_encode($a['content']),76,"\r\n");}
  return [['Content-Type: multipart/mixed; boundary="'.$boundary.'"'],$payload.'--'.$boundary."--\r\n"];
 }
 private static function smtp(string $to,string $subject,string $body,array $attachments): void {
  $from=\config('mail_from');$port=(int)\config('smtp_port');
  if (!in_array($port,[465,587],true) || !\config('smtp_user') || !\config('smtp_password')) throw new \RuntimeException('Email delivery has not been configured.');
  [$mimeHeaders,$mimeBody]=self::mime($body,$attachments);
  $headers=array_merge(['Date: '.date(DATE_RFC2822),'From: Prakshi Foods <'.$from.'>','To: <'.$to.'>','Subject: =?UTF-8?B?'.base64_encode($subject).'?=','Message-ID: <'.bin2hex(random_bytes(16)).'@'.substr(strrchr($from,'@'),1).'>','MIME-Version: 1.0'],$mimeHeaders);
  $payload=implode("\r\n",$headers)."\r\n\r\n".$mimeBody;
  $stream=fopen('php://temp','r+');fwrite($stream,$payload);rewind($stream);
  $ch=curl_init(($port===465?'smtps':'smtp').'://'.\config('smtp_host').':'.$port);
  curl_setopt_array($ch,[CURLOPT_USERNAME=>\config('smtp_user'),CURLOPT_PASSWORD=>\config('smtp_password'),CURLOPT_MAIL_FROM=>$from,CURLOPT_MAIL_RCPT=>[$to],CURLOPT_UPLOAD=>true,CURLOPT_INFILE=>$stream,CURLOPT_INFILESIZE=>strlen($payload),CURLOPT_USE_SSL=>CURLUSESSL_ALL,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_CONNECTTIMEOUT=>12,CURLOPT_TIMEOUT=>30,CURLOPT_RETURNTRANSFER=>true]);
  $ok=curl_exec($ch);$code=curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$errno=curl_errno($ch);curl_close($ch);fclose($stream);
  if ($ok===false || $code<200 || $code>=300) {
   // Never log SMTP transcripts, passwords, OTPs or customer email contents.
   error_log(date('c')." SMTP delivery failed (curl=$errno, smtp=$code)\n",3,ROOT.'/storage/logs/mail.log');
   throw new \RuntimeException('Email could not be sent. Please retry shortly or contact '.\config('support_email').'.');
  }
 }
}
