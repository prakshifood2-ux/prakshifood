<?php
$settings = array_replace([
    'name' => 'Prakshi Foods', 'env' => 'local',
    'base_url' => 'http://localhost/prakshi%20foods',
    'db_host' => '127.0.0.1', 'db_port' => '3306', 'db_name' => 'prakshi_foods',
    'db_user' => 'root', 'db_pass' => '',
    'mail_mode' => 'log', 'mail_from' => 'orders@example.com',
    'smtp_host' => 'smtp.gmail.com', 'smtp_port' => 465, 'smtp_user' => '', 'smtp_password' => '',
    'support_email' => 'prakshifoods@gmail.com', 'mail_log_dir' => ROOT.'/storage/mail',
    'app_key' => '',
], is_file(__DIR__.'/local.php') ? require __DIR__.'/local.php' : []);
// Environment overrides support isolated tests and deployments without committed secrets.
foreach (['PRAKSHI_DB_NAME'=>'db_name','PRAKSHI_BASE_URL'=>'base_url','PRAKSHI_MAIL_MODE'=>'mail_mode','PRAKSHI_MAIL_LOG_DIR'=>'mail_log_dir'] as $env=>$key) {
    if (getenv($env)) $settings[$key]=getenv($env);
}
return $settings;
