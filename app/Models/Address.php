<?php
namespace App\Models;
use App\Core\DB;
class Address {
 public const FIELDS=['name','email','mobile','address','city','state','pincode','instructions'];
 public static function defaultFor(int $uid): ?array {
  return DB::one('SELECT * FROM addresses WHERE user_id=? ORDER BY id DESC LIMIT 1',[$uid]);
 }
 public static function validate(array $input): array {
  $address=[];
  foreach(self::FIELDS as $field) {
   $value=trim((string)($input[$field]??''));
   if ($field!=='instructions' && $value==='') throw new \RuntimeException(ucfirst(str_replace('_',' ',$field)).' is required.');
   $max=['name'=>120,'email'=>190,'mobile'=>10,'address'=>1000,'city'=>100,'state'=>100,'pincode'=>6,'instructions'=>1000][$field];
   if (mb_strlen($value)>$max) throw new \RuntimeException(ucfirst($field).' is too long.');
   $address[$field]=$value;
  }
  if (!filter_var($address['email'],FILTER_VALIDATE_EMAIL) || !preg_match('/^[6-9][0-9]{9}$/',$address['mobile']) || !preg_match('/^[1-9][0-9]{5}$/',$address['pincode'])) throw new \RuntimeException('Enter a valid email, Indian mobile number and PIN code.');
  return $address;
 }
 public static function forCheckout(int $uid,array $input): array {
  if (($input['address_mode']??'manual')==='saved') {
   $saved=DB::one('SELECT * FROM addresses WHERE id=? AND user_id=?',[(int)($input['address_id']??0),$uid]);
   if (!$saved) throw new \RuntimeException('Saved address is unavailable. Please refresh checkout and choose your delivery address.');
   return self::validate($saved);
  }
  return self::validate($input);
 }
 /** Caller holds a transaction; locking the account prevents duplicate first addresses. */
 public static function remember(int $uid,array $address): void {
  DB::one('SELECT id FROM users WHERE id=? FOR UPDATE',[$uid]);
  $saved=self::defaultFor($uid);$data=array_intersect_key($address,array_flip(self::FIELDS));
  if ($saved) DB::update('addresses',$data,(int)$saved['id']);
  else DB::insert('addresses',['user_id'=>$uid]+$data);
 }
 public static function save(int $uid,array $input): void {
  $address=self::validate($input);
  DB::transaction(fn()=>self::remember($uid,$address));
 }
}
