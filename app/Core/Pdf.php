<?php
namespace App\Core;
/** Small dependency-free, paginated text PDF writer. Amounts use INR. */
class Pdf {
 public static function document(array $lines): string {
  $pages=array_chunk($lines,48);$objects=[];$kids=[];$font=3;
  foreach($pages as $n=>$page){$pageId=4+$n*2;$streamId=$pageId+1;$kids[]="$pageId 0 R";$content="BT /F1 11 Tf 48 795 Td 15 TL\n";
   foreach($page as $line){$text=iconv('UTF-8','Windows-1252//TRANSLIT//IGNORE',(string)$line);$text=str_replace(['\\','(',')',"\r","\n"],['\\\\','\\(','\\)','',' '],$text===false?'':$text);$content.='('.$text.") Tj T*\n";}
   $content.="ET\nBT /F1 9 Tf 48 35 Td (Page ".($n+1).' of '.count($pages).") Tj ET";
   $objects[$pageId]='<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 '.$font.' 0 R >> >> /Contents '.$streamId.' 0 R >>';
   $objects[$streamId]='<< /Length '.strlen($content)." >>\nstream\n".$content."\nendstream";
  }
  $objects[1]='<< /Type /Catalog /Pages 2 0 R >>';$objects[2]='<< /Type /Pages /Kids ['.implode(' ',$kids).'] /Count '.count($pages).' >>';$objects[3]='<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';ksort($objects);
  $pdf="%PDF-1.4\n";$offsets=[0];foreach($objects as $id=>$object){$offsets[$id]=strlen($pdf);$pdf.="$id 0 obj\n$object\nendobj\n";}$xref=strlen($pdf);$pdf.="xref\n0 ".(count($objects)+1)."\n0000000000 65535 f \n";foreach($objects as $id=>$unused)$pdf.=sprintf("%010d 00000 n \n",$offsets[$id]);return $pdf.'trailer << /Size '.(count($objects)+1)." /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";
 }
}
