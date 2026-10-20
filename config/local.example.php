<?php
// Copy to local.php; never commit production credentials.
return [
 'env'=>'production', 'base_url'=>'https://your-domain.example',
 'db_host'=>'127.0.0.1', 'db_name'=>'prakshi_foods',
 'db_user'=>'prakshi', 'db_pass'=>'CHANGE_ME',
 'app_key'=>'GENERATE_WITH_BIN_INSTALL',
 'mail_mode'=>'smtp', 'mail_from'=>'your-store@gmail.com',
 'smtp_host'=>'smtp.gmail.com', 'smtp_port'=>465,
 'smtp_user'=>'your-store@gmail.com', 'smtp_password'=>'YOUR_GOOGLE_APP_PASSWORD',
 'support_email'=>'your-store@gmail.com'
];
