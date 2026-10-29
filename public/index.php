<?php
require dirname(__DIR__).'/app/bootstrap.php';
$path=rtrim(rawurldecode(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH)??'/'),'/')?:'/';
try {
    if(preg_match('~^/media/([a-f0-9]{48}\.jpg)$~',$path,$m)) { $file=ROOT.'/storage/uploads/'.$m[1]; if(!is_file($file)) { http_response_code(404); exit; } header('Content-Type: image/jpeg'); header('Cache-Control: public, max-age=86400'); readfile($file); exit; }
    if($_SERVER['REQUEST_METHOD']==='POST') {
        check_csrf(); $action=input('action',60);
        if(str_starts_with($path,'/admin') && $path!=='/admin/login') { Admin::run($action); redirect('/admin/'.(array_key_exists($_POST['section']??'',Admin::SECTIONS)?$_POST['section']:'dashboard')); }
        $result=Actions::run($action);
        if(str_starts_with($path,'/api/')) { header('Content-Type: application/json'); echo json_encode($result); exit; }
        flash($result['message']??'Saved.'); $back=input('back',190,false); redirect(preg_match('~^/(?!/)[a-zA-Z0-9/?=&_-]*$~',$back)?$back:'/');
    }
    if($_SERVER['REQUEST_METHOD']!=='GET') { http_response_code(405); header('Allow: GET, POST'); exit; }
    if($path==='/api/products') { header('Content-Type: application/json'); $fields=array_flip(['id','name','slug','sku','type','short_description','price','sale_price','image','rating','review_count','category_name']); echo json_encode(array_map(fn($product)=>array_intersect_key($product,$fields),Catalog::products($_GET))); exit; }
    if($path==='/sitemap.xml') { header('Content-Type: application/xml'); echo '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'; foreach(array_merge(['/','/shop','/clothing','/scents','/collections','/about'],array_map(fn($p)=>'/product/'.$p['slug'],query("SELECT slug FROM products WHERE status='active'")->fetchAll())) as $url) echo '<url><loc>'.e(cfg('url').$url).'</loc></url>'; echo '</urlset>'; exit; }
    if($path==='/feed.xml') { header('Content-Type: application/xml'); echo '<?xml version="1.0" encoding="UTF-8"?><products>'; foreach(Catalog::products([],48) as $p) echo '<product><id>'.e($p['sku']).'</id><name>'.e($p['name']).'</name><link>'.e(cfg('url').'/product/'.$p['slug']).'</link><price currency="'.e(cfg('currency')).'">'.e(number_format(($p['sale_price']??$p['price'])/100,2,'.','')).'</price></product>'; echo '</products>'; exit; }
    if($path==='/') render('home',['title'=>'WIKI — Wear your story. Leave your signature.']);
    elseif(in_array($path,['/shop','/clothing','/scents','/search','/new-arrivals','/best-sellers','/sale','/collections'],true)) {
        $filters=$_GET; if(in_array($path,['/clothing','/scents'],true)) $filters['type']=substr($path,1); if($path==='/sale') $filters['sale']=1; if($path==='/new-arrivals') $filters['sort']='newest'; if($path==='/best-sellers') $filters['sort']='best';
        render('shop',['title'=>ucwords(str_replace('-',' ',substr($path,1))).' · WIKI','filters'=>$filters,'products'=>Catalog::products($filters)]);
    }
    elseif(preg_match('~^/product/([a-z0-9-]+)$~',$path,$m)) { $p=Catalog::one($m[1]); $recent=$_SESSION['recent']??[]; $_SESSION['recent']=array_slice(array_values(array_unique(array_merge([$p['slug']],$recent))),0,8); render('product',['title'=>$p['name'].' · WIKI','p'=>$p,'variants'=>Catalog::variants($p['id']),'recent'=>$recent]); }
    elseif($path==='/cart') render('cart',['title'=>'Your bag · WIKI']);
    elseif(in_array($path,['/checkout','/guest-checkout'],true)) { if(!empty($_SESSION['cart'])) { if(!isset($_SESSION['checkout_key']) || query('SELECT 1 FROM orders WHERE checkout_key=?',[$_SESSION['checkout_key']])->fetchColumn()) $_SESSION['checkout_key']=bin2hex(random_bytes(32)); } render('checkout',['title'=>'Checkout · WIKI']); }
    elseif(in_array($path,['/login','/register','/forgot-password','/reset-password','/admin/login'],true)) render('auth',['title'=>'Your account · WIKI','mode'=>basename($path)]);
    elseif($path==='/wishlist') { require_user(); render('account',['title'=>'Wishlist · WIKI','section'=>'wishlist']); }
    elseif($path==='/account' || str_starts_with($path,'/account/')) { require_user(); $section=substr($path,9)?:'dashboard'; if(!in_array($section,['dashboard','orders','profile','addresses','returns','wishlist'],true)) throw new DomainException('Page not found.'); render('account',['title'=>ucfirst($section).' · WIKI','section'=>$section]); }
    elseif(preg_match('~^/order/(WK-[A-Z0-9-]+)$~',$path,$m)) render('order',['title'=>'Order details · WIKI','order'=>Commerce::order($m[1])]);
    elseif($path==='/track') {
        if(isset($_GET['number'],$_GET['token'])) { $n=(string)$_GET['number']; $token=(string)$_GET['token']; $hash=query('SELECT guest_token_hash FROM orders WHERE number=?',[$n])->fetchColumn(); if($hash && hash_equals($hash,hash('sha256',$token))) { $_SESSION['guest_orders'][$n]=$token; redirect('/order/'.rawurlencode($n)); } throw new DomainException('This tracking link is invalid.'); }
        render('page',['title'=>'Track your order · WIKI','page'=>'track']);
    }
    elseif($path==='/admin' || str_starts_with($path,'/admin/')) { require_user(); $section=substr($path,7)?:'dashboard'; if(!isset(Admin::SECTIONS[$section])) throw new DomainException('Page not found.'); permit(Admin::SECTIONS[$section][1]); render('admin',['title'=>Admin::SECTIONS[$section][0].' · WIKI Admin','section'=>$section]); }
    elseif(in_array($path,['/about','/contact','/privacy','/terms','/shipping','/refund-policy','/faq','/size-guide','/verify-email'],true)) render('page',['title'=>ucwords(str_replace('-',' ',substr($path,1))).' · WIKI','page'=>substr($path,1)]);
    else { http_response_code(404); render('page',['title'=>'Page not found · WIKI','page'=>'404']); }
} catch(DomainException $exception) {
    while(ob_get_level()>0) ob_end_clean();
    if(str_starts_with($path,'/api/')) { if(http_response_code()<400) http_response_code(422); header('Content-Type: application/json'); echo json_encode(['error'=>$exception->getMessage()]); }
    else { if(http_response_code()<400) http_response_code(422); render('page',['title'=>'Please check your request · WIKI','page'=>'error','error'=>$exception->getMessage()]); }
}
