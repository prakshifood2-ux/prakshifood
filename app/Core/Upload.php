<?php
namespace App\Core;
class Upload {
 public static function image(string $field): ?string {
  $f=$_FILES[$field]??null; if (!$f || $f['error']===UPLOAD_ERR_NO_FILE) return null;
  if ($f['error']!==UPLOAD_ERR_OK || $f['size']>5*1024*1024) throw new \RuntimeException('Upload a JPG, PNG or WebP image smaller than 5 MB.');
  $mime=(new \finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']); $ext=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$mime]??null;
  if (!$ext || !getimagesize($f['tmp_name'])) throw new \RuntimeException('Invalid image file.');
  $name=bin2hex(random_bytes(20)).'.'.$ext;
  if (!move_uploaded_file($f['tmp_name'],ROOT.'/public/uploads/'.$name)) throw new \RuntimeException('Could not save image.');
  return 'uploads/'.$name;
 }
}
