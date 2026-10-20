<?php
namespace App\Core;
use PDO;
class DB {
 private static ?PDO $connection = null;
 public static function connection(): PDO {
  if (!self::$connection) self::$connection = new PDO('mysql:host='.\config('db_host').';port='.\config('db_port').';dbname='.\config('db_name').';charset=utf8mb4',\config('db_user'),\config('db_pass'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
  return self::$connection;
 }
 public static function query(string $sql,array $args=[]): \PDOStatement { $s=self::connection()->prepare($sql); $s->execute($args); return $s; }
 public static function all(string $sql,array $args=[]): array { return self::query($sql,$args)->fetchAll(); }
 public static function one(string $sql,array $args=[]): ?array { return self::query($sql,$args)->fetch() ?: null; }
 public static function insert(string $table,array $data): int {
  $fields=array_keys($data); self::query('INSERT INTO `'.$table.'` (`'.implode('`,`',$fields).'`) VALUES ('.implode(',',array_fill(0,count($fields),'?')).')',array_values($data)); return (int)self::connection()->lastInsertId();
 }
 public static function update(string $table,array $data,int $id): void { $fields=array_keys($data); self::query('UPDATE `'.$table.'` SET '.implode(',',array_map(fn($f)=>'`'.$f.'`=?',$fields)).' WHERE id=?',array_merge(array_values($data),[$id])); }
 public static function transaction(callable $fn) { $db=self::connection(); $db->beginTransaction(); try { $result=$fn(); $db->commit(); return $result; } catch (\Throwable $e) { $db->rollBack(); throw $e; } }
}
