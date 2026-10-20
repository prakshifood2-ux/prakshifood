<?php
namespace App\Core;
use App\Models\{Catalog,Cart,Payment,Admin};
/** Shared view data is composed before a template is rendered. */
class ViewData {
 public static function compose(string $view,string $layout,array $data): array {
  if ($layout==='store') { $customer=Auth::user();$data['currentCustomer']=$customer;$data['footerCategories']=Catalog::categories();$data['cartCount']=$customer?array_sum(array_column(Cart::items((int)$customer['id']),'quantity')):0; }
  if ($layout==='admin') $data['currentAdmin']=Auth::admin();
  if(in_array($view,['store/order','admin/order'],true)) {
   $data['orderHistory']=\App\Models\OrderNotice::history((int)$data['order']['id']);
   $data['orderEmails']=$view==='admin/order'?\App\Models\OrderNotice::emails((int)$data['order']['id']):[];
  }
  if(in_array($view,['admin/order','admin/orders'],true))$data['orderStatuses']=\App\Models\Order::STATUSES;
  if ($view==='store/checkout') $data['onlinePaymentsEnabled']=Payment::configured();
  if ($view==='admin/edit') $data=array_merge(Admin::editorOptions((int)($data['id']??0),$data['module']),$data);
  if ($view==='admin/settings') $data['settingsSections']=Admin::settingsFields();
  foreach(['products','related'] as $key) if (isset($data[$key])) $data[$key]=Catalog::withVariants($data[$key]);
  return $data;
 }
}
