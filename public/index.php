<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
use App\Controllers\{StoreController,AuthController,AdminController,VerificationController};
use App\Core\Auth;
$route=trim((string)($_GET['r']??''),'/');$method=$_SERVER['REQUEST_METHOD'];
require ROOT.'/app/maintenance.php';
$store=new StoreController;$auth=new AuthController;$admin=new AdminController;
$verification=new VerificationController;
$get=[
 'invoice'=>fn()=>(new App\Controllers\InvoiceController)->download(),'admin/invoice'=>fn()=>(new App\Controllers\InvoiceController)->download(true),
 'verify'=>fn()=>$verification->show('customer'),'admin/verify'=>fn()=>$verification->show('admin'),
 ''=>[$store,'home'],'shop'=>[$store,'shop'],'product'=>[$store,'product'],'bundle'=>[$store,'bundle'],'cart'=>[$store,'cart'],'checkout'=>[$store,'checkout'],'account'=>[$store,'account'],'orders'=>[$store,'account'],'order'=>[$store,'order'],'page'=>[$store,'page'],
 'login'=>fn()=>$auth->form('login'),'register'=>fn()=>$auth->form('register'),'forgot'=>fn()=>$auth->form('forgot'),'reset'=>fn()=>$auth->form('reset'),
 'admin/login'=>[$admin,'login'],'admin'=>[$admin,'dashboard'],'admin/list'=>[$admin,'listing'],'admin/edit'=>[$admin,'edit'],'admin/settings'=>[$admin,'settings'],'admin/orders'=>[$admin,'orders'],'admin/order'=>[$admin,'order'],'admin/customer'=>[$admin,'customer'],'admin/exports'=>[$admin,'exports'],'admin/export'=>[$admin,'export'],
 'sitemap'=>function(){header('Content-Type: application/xml; charset=utf-8');echo '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';foreach(['','shop','bundle'] as $r) echo '<url><loc>'.e(url($r)).'</loc></url>';foreach(App\Core\DB::all('SELECT slug FROM products WHERE active=1') as $p) echo '<url><loc>'.e(url('product',['slug'=>$p['slug']])).'</loc></url>';echo '</urlset>';},
];
$post=[
 'address/save'=>[$store,'saveAddress'],
 'verify'=>fn()=>$verification->verify('customer'),'admin/verify'=>fn()=>$verification->verify('admin'),
 'otp/resend'=>fn()=>$verification->resend('customer'),'admin/otp/resend'=>fn()=>$verification->resend('admin'),
 'login'=>fn()=>$auth->submit('login'),'register'=>fn()=>$auth->submit('register'),'forgot'=>[$auth,'forgot'],'reset'=>[$auth,'reset'],'logout'=>[$auth,'logout'],
 'cart/add'=>[$store,'add'],'cart/update'=>[$store,'updateCart'],'cart/coupon'=>[$store,'coupon'],'checkout'=>[$store,'place'],'profile'=>[$store,'profile'],'address/delete'=>[$store,'deleteAddress'],'reorder'=>[$store,'reorder'],'review'=>[$store,'review'],'contact'=>[$store,'contact'],'newsletter'=>[$store,'newsletter'],'payment/start'=>[$store,'paymentStart'],'payment/verify'=>[$store,'paymentVerify'],
 'admin/login'=>[$admin,'authenticate'],'admin/logout'=>[$admin,'logout'],'admin/save'=>[$admin,'save'],'admin/archive'=>[$admin,'archive'],'admin/settings'=>[$admin,'saveSettings'],'admin/order'=>[$admin,'updateOrder'],'admin/password'=>[$admin,'password'],
];
try {
 if ($route==='payment/webhook' && $method==='POST') {App\Models\Payment::webhook(file_get_contents('php://input'),$_SERVER['HTTP_X_RAZORPAY_SIGNATURE']??'',$_SERVER['HTTP_X_RAZORPAY_EVENT_ID']??'');header('Content-Type: application/json');echo '{"ok":true}';}
 else {if ($method==='POST') Auth::csrf();$handler=($method==='POST'?$post:$get)[$route]??null;if ($handler) call_user_func($handler);else $store->missing();}
} catch (Throwable $ex) {
 if (!$ex instanceof RuntimeException || $ex instanceof PDOException) App\Core\Support::incident(get_class($ex),$route);
 error_log(date('c').' '.get_class($ex).': '.$ex->getMessage()."\n",3,ROOT.'/storage/logs/app.log');
 $message=$ex instanceof RuntimeException && !$ex instanceof PDOException?$ex->getMessage():'We couldn’t complete that request. Please try again or contact the store.';
 if (str_starts_with($route,'payment/') || $route==='cart/add' || ($_SERVER['HTTP_X_REQUESTED_WITH']??'')==='XMLHttpRequest') {http_response_code(422);header('Content-Type: application/json');echo json_encode(['error'=>$message]);}
 else {http_response_code(422);render('store/error',['title'=>'Let’s try that again','message'=>$message]);}
}
