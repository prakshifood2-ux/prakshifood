<?php
namespace App\Models;
use App\Core\DB;
class Catalog {
 public static function categories(): array { return DB::all('SELECT * FROM categories WHERE active=1 ORDER BY sort_order,name'); }
 public static function products(array $filters=[]): array {
  $where='p.active=1 AND c.active=1';$args=[];
  if (!empty($filters['category'])) { $where.=' AND c.slug=?';$args[]=$filters['category']; }
  if (!empty($filters['q'])) { $where.=' AND (p.name LIKE ? OR p.description LIKE ?)';$args[]='%'.$filters['q'].'%';$args[]='%'.$filters['q'].'%'; }
  if (!empty($filters['featured'])) $where.=' AND p.featured=1';
  $sort=['price'=>'price ASC','price-desc'=>'price DESC','newest'=>'p.id DESC'][$filters['sort']??'']??'p.featured DESC,p.id';
  $page=max(1,(int)($filters['page']??1));$limit=24;$offset=($page-1)*$limit;
  return DB::all("SELECT p.*,c.name category_name,(SELECT MIN(price) FROM product_variants WHERE product_id=p.id AND active=1) price,(SELECT AVG(rating) FROM reviews WHERE product_id=p.id AND status='approved') rating,(SELECT COUNT(*) FROM reviews WHERE product_id=p.id AND status='approved') review_count FROM products p JOIN categories c ON c.id=p.category_id WHERE $where ORDER BY $sort LIMIT $limit OFFSET $offset",$args);
 }
 public static function product(string $slug): ?array { return DB::one('SELECT p.*,c.name category_name FROM products p JOIN categories c ON c.id=p.category_id WHERE p.slug=? AND p.active=1 AND c.active=1',[$slug]); }
 public static function variants(int $id): array { return DB::all('SELECT * FROM product_variants WHERE product_id=? AND active=1 ORDER BY price',[$id]); }
 public static function reviews(?int $id=null): array { return DB::all("SELECT r.*,u.name,p.name product_name FROM reviews r JOIN users u ON u.id=r.user_id JOIN products p ON p.id=r.product_id WHERE r.status='approved'".($id?' AND product_id=?':'').' ORDER BY r.id DESC LIMIT 30',$id?[$id]:[]); }
 public static function withVariants(array $products): array {
  if (!$products) return [];
  $ids=array_column($products,'id');$marks=implode(',',array_fill(0,count($ids),'?'));
  $variants=DB::all('SELECT * FROM product_variants WHERE product_id IN ('.$marks.') AND active=1 ORDER BY price',$ids);$grouped=[];
  foreach($variants as $v)$grouped[$v['product_id']][]=$v;
  foreach($products as &$p)$p['variants']=$grouped[$p['id']]??[];
  return $products;
 }
 public static function bundles(): array {
  $bundles=DB::all('SELECT * FROM bundles WHERE active=1 AND (starts_at IS NULL OR starts_at<=NOW()) AND (ends_at IS NULL OR ends_at>=NOW())');
  foreach($bundles as &$b) $b['products']=self::withVariants(DB::all('SELECT p.*,c.name category_name FROM products p JOIN categories c ON c.id=p.category_id JOIN bundle_items bi ON bi.product_id=p.id WHERE bi.bundle_id=? AND p.active=1 AND c.active=1',[$b['id']]));
  return $bundles;
 }
}
