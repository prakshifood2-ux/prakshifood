<?php
namespace App\Controllers;
use App\Core\Auth;
use App\Models\{Order,Invoice};
class InvoiceController {
 public function download(bool $admin=false): void {
  $id=(int)($_GET['id']??0);
  if($admin)Auth::protect();else {$u=Auth::customer();Order::own($id,(int)$u['id']);}
  $invoice=Invoice::forOrder($id);header('Content-Type: application/pdf');header('Content-Disposition: attachment; filename="'.$invoice['number'].'.pdf"');header('Cache-Control: private, no-store');echo Invoice::pdf($invoice);
 }
}
