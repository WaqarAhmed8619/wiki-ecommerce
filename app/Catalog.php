<?php
class Catalog {
    public static function products(array $filters=[],int $limit=12): array {
        foreach($filters as $key=>$value) if(!is_scalar($value)) unset($filters[$key]);
        $where=["p.status='active'"]; $params=[];
        if(!empty($filters['q'])) { $where[]='(p.name LIKE ? OR p.sku LIKE ? OR p.description LIKE ? OR c.name LIKE ?)'; for($i=0;$i<4;$i++) $params[]='%'.mb_substr((string)$filters['q'],0,100).'%'; }
        foreach(['type','category','family'] as $k) if(!empty($filters[$k])) { $where[]=['type'=>'p.type=?','category'=>'c.slug=?','family'=>'p.fragrance_family=?'][$k]; $params[]=$filters[$k]; }
        foreach(['min'=>'>=','max'=>'<='] as $k=>$op) if(isset($filters[$k]) && $filters[$k]!=='') { $where[]='COALESCE(p.sale_price,p.price) '.$op.' ?'; $params[]=max(0,(int)$filters[$k])*100; }
        if(!empty($filters['sale'])) $where[]='p.sale_price IS NOT NULL';
        if(!empty($filters['featured'])) $where[]='p.featured=1';
        foreach(['size','color','volume'] as $k) if(!empty($filters[$k])) { $where[]='EXISTS(SELECT 1 FROM product_variants vf WHERE vf.product_id=p.id AND vf.'.$k.'=?)'; $params[]=$filters[$k]; }
        if(!empty($filters['available'])) $where[]='EXISTS(SELECT 1 FROM product_variants av JOIN inventory ai ON ai.variant_id=av.id WHERE av.product_id=p.id AND ai.stock>0)';
        if(!empty($filters['rating'])) { $where[]="COALESCE((SELECT AVG(rating) FROM reviews WHERE product_id=p.id AND status='approved'),0)>=?"; $params[]=(int)$filters['rating']; }
        $sort=['featured'=>'p.featured DESC,p.id DESC','newest'=>'p.created_at DESC,p.id DESC','price-asc'=>'COALESCE(p.sale_price,p.price) ASC','price-desc'=>'COALESCE(p.sale_price,p.price) DESC','rating'=>'rating DESC','best'=>'sold DESC'][$filters['sort']??'featured']??'p.id DESC';
        $page=max(1,min(10000,(int)($filters['page']??1))); $limit=max(1,min(48,$limit)); $offset=($page-1)*$limit;
        return query("SELECT p.*,c.name category_name,(SELECT path FROM product_images WHERE product_id=p.id ORDER BY position,id LIMIT 1) image,(SELECT AVG(rating) FROM reviews WHERE product_id=p.id AND status='approved') rating,(SELECT COUNT(*) FROM reviews WHERE product_id=p.id AND status='approved') review_count,(SELECT COALESCE(SUM(oi.quantity),0) FROM order_items oi JOIN orders o ON o.id=oi.order_id WHERE oi.product_id=p.id AND o.status NOT IN ('cancelled','refunded','returned')) sold FROM products p JOIN categories c ON c.id=p.category_id WHERE ".implode(' AND ',$where).' ORDER BY '.$sort.' LIMIT '.$limit.' OFFSET '.$offset,$params)->fetchAll();
    }
    public static function one(string $slug): array { $p=query("SELECT p.*,c.name category_name FROM products p JOIN categories c ON c.id=p.category_id WHERE p.slug=? AND p.status='active'",[$slug])->fetch(); if(!$p) throw new DomainException('Product not found.'); return $p; }
    public static function variants(int $id): array { return query('SELECT v.*,i.stock FROM product_variants v JOIN inventory i ON i.variant_id=v.id WHERE v.product_id=? ORDER BY v.id',[$id])->fetchAll(); }
}
