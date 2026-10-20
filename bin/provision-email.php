<?php
if (PHP_SAPI!=='cli') exit('CLI only');
// Supply JSON through stdin. Never place the Gmail app password in shell arguments.
require dirname(__DIR__).'/app/bootstrap.php';
use App\Core\DB;
use App\Models\Setting;
$input=json_decode(stream_get_contents(STDIN),true);
if (!is_array($input) || !filter_var($input['email']??'',FILTER_VALIDATE_EMAIL) || strlen(preg_replace('/\s/','',$input['app_password']??''))!==16) exit("Invalid private SMTP configuration.\n");
$local=is_file(ROOT.'/config/local.php')?require ROOT.'/config/local.php':[];
$local=array_replace($local,['mail_mode'=>'smtp','mail_from'=>$input['email'],'smtp_host'=>'smtp.gmail.com','smtp_port'=>465,'smtp_user'=>$input['email'],'smtp_password'=>preg_replace('/\s/','',$input['app_password']),'support_email'=>$input['email']]);
if (file_put_contents(ROOT.'/config/local.php',"<?php\nreturn ".var_export($local,true).";\n")===false) exit("Could not save private configuration.\n");
$a=DB::one('SELECT * FROM admin_users WHERE email=?',[$input['email']])??DB::one("SELECT * FROM admin_users WHERE email='admin@prakshi.local'");
$password='PF!'.bin2hex(random_bytes(9));$data=['name'=>'Prakshi Store Owner','email'=>$input['email'],'password'=>password_hash($password,PASSWORD_DEFAULT),'role'=>'owner','active'=>1];
if ($a) DB::update('admin_users',$data,(int)$a['id']);else DB::insert('admin_users',$data);
Setting::set('email',$input['email']);
echo "SMTP configuration saved privately.\nAdmin email: ".$input['email']."\nNew admin password: $password\nAdmin link: ".url('admin/login')."\n";
