<?php
namespace App\Models;
use App\Core\DB;
class Setting {
 private static ?array $cache=null;
 public static function all(): array { if (self::$cache===null) self::$cache=array_column(DB::all('SELECT * FROM store_settings'),'setting_value','setting_key'); return self::$cache; }
 public static function get(string $key,string $default=''): string { return self::all()[$key]??$default; }
 public static function set(string $key,string $value): void { DB::query('INSERT INTO store_settings VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)',[$key,$value]); self::$cache=null; }
 public static function secret(string $key): string { $value=self::get($key); if (!$value) return ''; $raw=base64_decode($value,true); if (!$raw || strlen($raw)<29) return ''; return openssl_decrypt(substr($raw,28),'aes-256-gcm',hash('sha256',\config('app_key'),true),OPENSSL_RAW_DATA,substr($raw,0,12),substr($raw,12,16)) ?: ''; }
 public static function saveSecret(string $key,string $value): void { if (!\config('app_key')) throw new \RuntimeException('Configure the application encryption key first.'); $iv=random_bytes(12); $cipher=openssl_encrypt($value,'aes-256-gcm',hash('sha256',\config('app_key'),true),OPENSSL_RAW_DATA,$iv,$tag); self::set($key,base64_encode($iv.$tag.$cipher)); }
}
