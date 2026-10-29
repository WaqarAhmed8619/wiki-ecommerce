<?php
class Admin {
    public const SECTIONS=[
        'dashboard'=>['Dashboard','reports.view'], 'products'=>['Products','catalog.manage'], 'categories'=>['Categories','catalog.manage'], 'variants'=>['Variants','catalog.manage'], 'inventory'=>['Inventory','catalog.manage'], 'orders'=>['Orders','orders.manage'], 'customers'=>['Customers','users.manage'], 'reviews'=>['Reviews','catalog.manage'], 'returns'=>['Returns','orders.manage'], 'refunds'=>['Refunds','orders.manage'], 'coupons'=>['Coupons','marketing.manage'], 'banners'=>['Promotions & banners','marketing.manage'], 'newsletter'=>['Newsletter','marketing.manage'], 'analytics'=>['Analytics','reports.view'], 'reports'=>['Sales reports','reports.view'], 'settings'=>['Website settings','settings.manage'], 'users'=>['Admin users','users.manage'], 'roles'=>['Roles & permissions','users.manage'], 'login-activity'=>['Login activity','users.manage'], 'security'=>['Security logs','users.manage'], 'notifications'=>['Notifications','orders.manage'], 'email'=>['Email queue','marketing.manage']
    ];
    public static function rows(string $section): array {
        $sql=[
            'products'=>'SELECT id,name,sku,type,price,sale_price,status,featured FROM products ORDER BY id DESC',
            'categories'=>'SELECT c.id,c.name,c.slug,p.name parent FROM categories c LEFT JOIN categories p ON p.id=c.parent_id ORDER BY c.id',
            'variants'=>'SELECT v.*,i.stock FROM product_variants v JOIN inventory i ON i.variant_id=v.id ORDER BY v.id DESC',
            'inventory'=>'SELECT v.id,v.sku,v.size,v.color,v.volume,i.stock,i.minimum_stock FROM inventory i JOIN product_variants v ON v.id=i.variant_id ORDER BY (i.stock<=i.minimum_stock) DESC,v.id',
            'orders'=>'SELECT id,number,customer_name,email,total,status,payment_status,created_at FROM orders ORDER BY id DESC',
            'customers'=>'SELECT id,name,email,phone,created_at FROM users WHERE role_id=1 ORDER BY id DESC',
            'reviews'=>'SELECT r.id,p.name product,r.rating,r.body,r.status,r.verified FROM reviews r JOIN products p ON p.id=r.product_id ORDER BY r.id DESC',
            'returns'=>'SELECT r.id,o.number,r.reason,r.status,r.created_at FROM returns r JOIN orders o ON o.id=r.order_id ORDER BY r.id DESC',
            'refunds'=>'SELECT r.id,o.number,r.amount,r.reference,r.created_at FROM refunds r JOIN orders o ON o.id=r.order_id ORDER BY r.id DESC',
            'coupons'=>'SELECT * FROM coupons ORDER BY id DESC',
            'banners'=>'SELECT * FROM banners ORDER BY id DESC',
            'newsletter'=>'SELECT id,email,active,created_at FROM newsletter_subscribers ORDER BY id DESC',
            'analytics'=>"SELECT event,COUNT(*) events,COUNT(DISTINCT session_hash) sessions FROM analytics_events WHERE created_at>DATE_SUB(UTC_TIMESTAMP(),INTERVAL 30 DAY) GROUP BY event",
            'reports'=>"SELECT DATE(created_at) day,COUNT(*) orders,SUM(total) ordered_value,SUM(CASE WHEN payment_status='paid' THEN total ELSE 0 END) collected_revenue FROM orders WHERE status NOT IN ('cancelled','refunded') GROUP BY DATE(created_at) ORDER BY day DESC",
            'settings'=>'SELECT name,value FROM site_settings ORDER BY name',
            'users'=>'SELECT u.id,u.name,u.email,r.name role FROM users u JOIN roles r ON r.id=u.role_id WHERE u.role_id<>1 ORDER BY u.id',
            'roles'=>'SELECT r.name role,p.name permission FROM role_permissions rp JOIN roles r ON r.id=rp.role_id JOIN permissions p ON p.id=rp.permission_id ORDER BY r.id,p.id',
            'login-activity'=>'SELECT id,identity_hash,success,created_at FROM login_attempts ORDER BY id DESC',
            'security'=>'SELECT id,user_id,action,context,created_at FROM audit_logs ORDER BY id DESC',
            'notifications'=>'SELECT * FROM notifications ORDER BY id DESC',
            'email'=>'SELECT id,recipient,subject,status,attempts,created_at FROM email_outbox ORDER BY id DESC'
        ][$section]??'SELECT id,name FROM products';
        $page=max(1,min(10000,(int)($_GET['page']??1))); return query($sql.' LIMIT 50 OFFSET '.(($page-1)*50))->fetchAll();
    }
    public static function run(string $action): void {
        switch($action) {
            case 'product':
                permit('catalog.manage'); $id=integer('id',0); $type=input('type',20); $status=input('status',20); $slug=input('slug',190);
                if(!in_array($type,['clothing','scents'],true)||!in_array($status,['active','draft','archived'],true)||!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/',$slug)) throw new DomainException('Invalid product type, status or slug.');
                $price=integer('price',1); $sale=input('sale_price',20,false); $sale=$sale===''?null:integer('sale_price'); if($sale!==null && $sale>$price) throw new DomainException('Sale price cannot exceed regular price.');
                $data=[integer('category_id',1),input('name',180),$slug,input('sku',80),$type,input('short_description',500),input('description',20000),$price,$sale,integer('cost_price'),$status,isset($_POST['featured'])?1:0,input('fragrance_family',80,false),input('top_notes',255,false),input('middle_notes',255,false),input('base_notes',255,false),input('gender',40,false),input('longevity',80,false),input('concentration',80,false)];
                if($id) { $data[]=$id; query('UPDATE products SET category_id=?,name=?,slug=?,sku=?,type=?,short_description=?,description=?,price=?,sale_price=?,cost_price=?,status=?,featured=?,fragrance_family=?,top_notes=?,middle_notes=?,base_notes=?,gender=?,longevity=?,concentration=? WHERE id=?',$data); }
                else { query('INSERT INTO products(category_id,name,slug,sku,type,short_description,description,price,sale_price,cost_price,status,featured,fragrance_family,top_notes,middle_notes,base_notes,gender,longevity,concentration) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',$data); $id=(int)db()->lastInsertId(); }
                audit('product.saved',['id'=>$id]); break;
            case 'archive': permit('catalog.manage'); $id=integer('id',1); query("UPDATE products SET status='archived' WHERE id=?",[$id]); audit('product.archived',['id'=>$id]); break;
            case 'category':
                permit('catalog.manage'); $slug=input('slug',120); if(!preg_match('/^[a-z0-9-]+$/',$slug)) throw new DomainException('Use a lowercase URL slug.');
                query('INSERT INTO categories(name,slug,parent_id) VALUES(?,?,?)',[input('name',100),$slug,integer('parent_id',0)?:null]); audit('category.created'); break;
            case 'variant':
                permit('catalog.manage'); transaction(function(){ $pid=integer('product_id',1); $p=query('SELECT type FROM products WHERE id=?',[$pid])->fetchColumn(); if(!$p) throw new DomainException('Product does not exist.'); $size=input('size',20,false); $color=input('color',40,false); $volume=input('volume',20,false); if(($p==='clothing' && (!$size || !$color)) || ($p==='scents' && !$volume)) throw new DomainException('Set clothing size/color or scent volume.'); query('INSERT INTO product_variants(product_id,sku,size,color,volume,price_adjustment) VALUES(?,?,?,?,?,?)',[$pid,input('sku',100),$size,$color,$volume,integer('price_adjustment',0)]); $id=db()->lastInsertId(); query('INSERT INTO inventory(variant_id,stock,minimum_stock) VALUES(?,?,?)',[$id,integer('stock'),integer('minimum_stock')]); audit('variant.created',['id'=>$id]); }); break;
            case 'inventory':
                permit('catalog.manage'); transaction(function(){ $id=integer('id',1); $row=query('SELECT * FROM inventory WHERE variant_id=? FOR UPDATE',[$id])->fetch(); if(!$row) throw new DomainException('Variant not found.'); $stock=integer('stock'); query('UPDATE inventory SET stock=?,minimum_stock=? WHERE variant_id=?',[$stock,integer('minimum_stock'),$id]); audit('inventory.adjusted',['id'=>$id,'from'=>$row['stock'],'to'=>$stock,'reason'=>input('reason',200)]); }); break;
            case 'upload': permit('catalog.manage'); self::upload(); break;
            case 'order': permit('orders.manage'); Commerce::transition(integer('id',1),input('status',30)); break;
            case 'shipment':
                permit('orders.manage'); query('INSERT INTO shipments(order_id,carrier,tracking_number) VALUES(?,?,?) ON DUPLICATE KEY UPDATE carrier=VALUES(carrier),tracking_number=VALUES(tracking_number)',[integer('id',1),input('carrier',100),input('tracking_number',120)]); audit('shipment.saved'); break;
            case 'paid':
                permit('orders.manage'); transaction(function(){ $id=integer('id',1); $o=query('SELECT * FROM orders WHERE id=? FOR UPDATE',[$id])->fetch(); if(!$o || $o['status']!=='delivered' || $o['payment_status']!=='pending') throw new DomainException('Only delivered, unpaid COD orders can be marked paid.'); query("UPDATE orders SET payment_status='paid' WHERE id=?",[$id]); query("UPDATE payments SET status='paid' WHERE order_id=? AND provider='cod'",[$id]); queue_mail($o['email'],'WIKI payment received','Payment received for '.$o['number'].'.'); audit('payment.collected',['id'=>$id]); }); break;
            case 'refund':
                permit('orders.manage'); transaction(function(){ $id=integer('id',1); $o=query('SELECT * FROM orders WHERE id=? FOR UPDATE',[$id])->fetch(); if(!$o || !in_array($o['payment_status'],['paid','partially_refunded'],true) || !in_array($o['status'],['returned','cancelled'],true)) throw new DomainException('Refunds require a paid, returned or cancelled order.'); $amount=integer('amount',1); $previous=(int)query('SELECT COALESCE(SUM(amount),0) FROM refunds WHERE order_id=?',[$id])->fetchColumn(); if($amount+$previous>$o['total']) throw new DomainException('Refund exceeds the remaining paid amount.'); query('INSERT INTO refunds(order_id,amount,reference) VALUES(?,?,?)',[$id,$amount,input('reference',190)]); $full=$amount+$previous===$o['total']; query('UPDATE orders SET payment_status=?,status=? WHERE id=?',[$full?'refunded':'partially_refunded',$full?'refunded':$o['status'],$id]); query('UPDATE payments SET status=? WHERE order_id=?',[$full?'refunded':'partially_refunded',$id]); queue_mail($o['email'],'WIKI refund recorded','A refund of '.money($amount).' has been recorded for '.$o['number'].'.'); audit('refund.recorded',['id'=>$id,'amount'=>$amount]); }); break;
            case 'return-status':
                permit('orders.manage'); transaction(function(){ $id=integer('id',1); $status=input('status',20); $old=query('SELECT status FROM returns WHERE id=? FOR UPDATE',[$id])->fetchColumn(); $map=['requested'=>['approved','rejected'],'approved'=>['received'],'received'=>[],'rejected'=>[]]; if(!in_array($status,$map[$old]??[],true)) throw new DomainException('Invalid return transition.'); query('UPDATE returns SET status=? WHERE id=?',[$status,$id]); audit('return.updated',['id'=>$id,'status'=>$status]); }); break;
            case 'review': permit('catalog.manage'); $status=input('status',20); if(!in_array($status,['approved','rejected'],true)) throw new DomainException('Invalid review status.'); query('UPDATE reviews SET status=? WHERE id=?',[$status,integer('id',1)]); audit('review.moderated'); break;
            case 'review-delete': permit('catalog.manage'); $id=integer('id',1); query('DELETE FROM reviews WHERE id=?',[$id]); audit('review.deleted',['id'=>$id]); break;
            case 'coupon':
                permit('marketing.manage'); $type=input('type',20); if(!in_array($type,['percentage','fixed'],true)) throw new DomainException('Invalid coupon type.'); $value=integer('value',1,$type==='percentage'?100:100000000); $start=input('starts_at',30); $end=input('expires_at',30); if(!strtotime($start)||!strtotime($end)||strtotime($end)<=strtotime($start)) throw new DomainException('Choose a valid coupon date range.');
                query('INSERT INTO coupons(code,type,value,minimum_amount,maximum_discount,starts_at,expires_at,usage_limit,per_user_limit,active) VALUES(?,?,?,?,?,?,?,?,?,?)',[strtoupper(input('code',40)),$type,$value,integer('minimum_amount'),integer('maximum_discount',0)?:null,date('Y-m-d H:i:s',strtotime($start)),date('Y-m-d H:i:s',strtotime($end)),integer('usage_limit',1),integer('per_user_limit',1),isset($_POST['active'])?1:0]); audit('coupon.created'); break;
            case 'coupon-toggle': permit('marketing.manage'); query('UPDATE coupons SET active=1-active WHERE id=?',[integer('id',1)]); audit('coupon.toggled'); break;
            case 'banner': permit('marketing.manage'); $url=input('url',190); if(!preg_match('~^/(?!/)[a-zA-Z0-9/?=&_-]*$~',$url)) throw new DomainException('Use a local storefront URL.'); query('INSERT INTO banners(title,body,url) VALUES(?,?,?)',[input('title',190),input('body',500),$url]); audit('banner.created'); break;
            case 'banner-toggle': permit('marketing.manage'); query('UPDATE banners SET active=1-active WHERE id=?',[integer('id',1)]); audit('banner.toggled'); break;
            case 'unsubscribe': permit('marketing.manage'); query('UPDATE newsletter_subscribers SET active=0 WHERE id=?',[integer('id',1)]); audit('newsletter.unsubscribed'); break;
            case 'setting': permit('settings.manage'); $name=input('name',100); if(!in_array($name,['announcement','contact_email','instagram_url'],true)) throw new DomainException('Unknown setting.'); query('INSERT INTO site_settings(name,value) VALUES(?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)',[$name,input('value',1000)]); audit('setting.saved',['name'=>$name]); break;
            case 'role':
                permit('users.manage'); $id=integer('id',1); $role=integer('role_id',1,3); if($id===user()['id']) throw new DomainException('You cannot change your own role.'); transaction(function() use($id,$role){ $admins=query('SELECT id FROM users WHERE role_id=2 FOR UPDATE')->fetchAll(); $target=query('SELECT role_id FROM users WHERE id=? FOR UPDATE',[$id])->fetchColumn(); if(!$target) throw new DomainException('User not found.'); if((int)$target===2 && $role!==2 && count($admins)<=1) throw new DomainException('Keep at least one administrator.'); query('UPDATE users SET role_id=?,session_version=session_version+1 WHERE id=?',[$role,$id]); audit('user.role',['id'=>$id,'role'=>$role]); }); break;
            default: throw new DomainException('Unknown admin action.');
        }
        flash('Changes saved.');
    }
    private static function upload(): void {
        $pid=integer('product_id',1); if(!query('SELECT 1 FROM products WHERE id=?',[$pid])->fetchColumn()) throw new DomainException('Product does not exist.');
        $file=$_FILES['image']??null;
        if(!$file || $file['error']!==UPLOAD_ERR_OK || $file['size']>5*1024*1024 || !is_uploaded_file($file['tmp_name'])) throw new DomainException('Choose an image smaller than 5 MB.');
        $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']); $ext=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$mime]??null;
        $dim=getimagesize($file['tmp_name']); if(!$ext || !$dim || $dim[0]>6000 || $dim[1]>6000 || $dim[0]*$dim[1]>18000000) throw new DomainException('Use a valid JPEG, PNG or WebP up to 18 megapixels.');
        if(!function_exists('imagecreatefromstring')) throw new DomainException('The PHP GD extension is required for secure image uploads.');
        $image=imagecreatefromstring(file_get_contents($file['tmp_name'])); if(!$image) throw new DomainException('This image could not be decoded.');
        $name=bin2hex(random_bytes(24)).'.jpg'; $path=ROOT.'/storage/uploads/'.$name;
        if(!imagejpeg($image,$path,88)) throw new RuntimeException('Image encoding failed'); imagedestroy($image);
        try { query('INSERT INTO product_images(product_id,path,alt,position) VALUES(?,?,?,?)',[$pid,'/media/'.$name,input('alt',255),integer('position',0,100)]); } catch(Throwable $e) { unlink($path); throw $e; }
        audit('product.image',['product'=>$pid]);
    }
}
