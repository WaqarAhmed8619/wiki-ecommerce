<?php
if(PHP_SAPI!=='cli') exit;
require dirname(__DIR__).'/app/bootstrap.php';
if(cfg('env')!=='development') exit("Development only.\n");
$_SESSION=[]; $checks=0;
function ok(bool $value,string $label): void { global $checks; if(!$value) throw new RuntimeException('FAIL: '.$label); $checks++; echo 'PASS: '.$label.PHP_EOL; }
class Client {
 public string $cookie; public string $csrf='';
 public function __construct(){ $this->cookie=tempnam(sys_get_temp_dir(),'wiki-journey');$this->get('/'); }
 public function get(string $url): array { return $this->request($url); }
 public function post(string $url,array $data): array { return $this->request($url,['csrf'=>$this->csrf]+$data); }
 private function request(string $url,?array $data=null): array { $c=curl_init('http://127.0.0.1:8080'.$url);curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_COOKIEJAR=>$this->cookie,CURLOPT_COOKIEFILE=>$this->cookie]);if($data!==null){curl_setopt($c,CURLOPT_POST,true);curl_setopt($c,CURLOPT_POSTFIELDS,$data);} $r=curl_exec($c);if($r===false)throw new RuntimeException(curl_error($c));$size=curl_getinfo($c,CURLINFO_HEADER_SIZE);$status=curl_getinfo($c,CURLINFO_HTTP_CODE);curl_close($c);$body=substr($r,$size);if(preg_match('/name="csrf-token" content="([a-f0-9]+)"/',$body,$m))$this->csrf=$m[1];preg_match('/Location: ([^\r\n]+)/i',substr($r,0,$size),$loc);return ['status'=>$status,'body'=>$body,'location'=>$loc[1]??'']; }
 public function __destruct(){@unlink($this->cookie);}
}
$prefix='test-'.bin2hex(random_bytes(5)); $email=$prefix.'@example.test';$adminEmail='admin-'.$email;$password=bin2hex(random_bytes(16));$ids=[];
$vid=(int)query("SELECT v.id FROM product_variants v JOIN products p ON p.id=v.product_id WHERE p.slug='signature-oversized-t-shirt' ORDER BY v.id LIMIT 1")->fetchColumn();$originalStock=(int)query('SELECT stock FROM inventory WHERE variant_id=?',[$vid])->fetchColumn();
try {
 $customer=new Client();$r=$customer->post('/register',['action'=>'register','name'=>'Journey Customer','email'=>$email,'password'=>$password]);ok($r['status']===303,'registration');$uid=(int)query('SELECT id FROM users WHERE email=?',[$email])->fetchColumn();$ids[]=$uid;
 $r=$customer->post('/login',['action'=>'login','email'=>$email,'password'=>$password]);ok($r['location']==='/account','customer login');$customer->get('/account');
 $welcome=query("SELECT body FROM email_outbox WHERE recipient=? AND subject='Welcome to WIKI' ORDER BY id DESC LIMIT 1",[$email])->fetchColumn();preg_match('/token=([a-f0-9]+)/',$welcome,$verification);ok($customer->post('/verify-email',['action'=>'verify-email','token'=>$verification[1]])['status']===303,'email verification');ok((bool)query('SELECT verified_at FROM users WHERE id=?',[$uid])->fetchColumn(),'email verification persisted');
 ok($customer->get('/admin')['status']===403,'customer blocked from admin');ok($customer->post('/admin/products',['action'=>'archive','id'=>1])['status']===403,'customer blocked from admin mutation');
 foreach(['/account','/account/profile','/account/addresses','/account/orders','/account/returns','/wishlist'] as $url)ok($customer->get($url)['status']===200,'customer page '.$url);
 ok($customer->post('/api/action',['action'=>'address','label'=>'Test','address'=>'10 Test Street','city'=>'Karachi','province'=>'Sindh','country'=>'Pakistan','postal_code'=>'74000'])['status']===200,'address creation');
 $addressId=query('SELECT id FROM user_addresses WHERE user_id=?',[$uid])->fetchColumn();ok($customer->get('/account/addresses?edit='.$addressId)['status']===200,'address editor');ok($customer->post('/api/action',['action'=>'address','id'=>$addressId,'label'=>'Home','address'=>'12 Test Street','city'=>'Lahore','province'=>'Punjab','country'=>'Pakistan','postal_code'=>'54000'])['status']===200,'address update');ok(query('SELECT city FROM user_addresses WHERE id=?',[$addressId])->fetchColumn()==='Lahore','address changes persisted');
 ok($customer->post('/api/action',['action'=>'wishlist','product_id'=>1])['status']===200,'wishlist persistence');
 ok($customer->post('/api/action',['action'=>'cart','variant_id'=>$vid,'quantity'=>2,'mode'=>'add'])['status']===200,'customer cart');
 ok($customer->post('/api/action',['action'=>'coupon','code'=>'WELCOME10'])['status']===200,'coupon application');
 $checkout=$customer->get('/checkout');preg_match('/name="checkout_key" value="([a-f0-9]+)"/',$checkout['body'],$key);
 $payload=['action'=>'checkout','checkout_key'=>$key[1],'email'=>$email,'first_name'=>'Journey','last_name'=>'Customer','phone'=>'+923001234567','country'=>'Pakistan','province'=>'Sindh','city'=>'Karachi','address'=>'10 Test Street','postal_code'=>'74000','payment_method'=>'cod','shipping_method'=>'standard','terms'=>'1','price'=>1,'total'=>1];
 $r=$customer->post('/checkout',$payload);ok($r['status']===303 && str_starts_with($r['location'],'/order/'),'order checkout');$number=basename($r['location']);$order=query('SELECT * FROM orders WHERE number=?',[$number])->fetch();$oid=$order['id'];
 ok($order['subtotal']===900000 && $order['discount']===90000 && $order['total']===835000,'server authoritative totals including coupon and shipping');
 ok((int)query('SELECT stock FROM inventory WHERE variant_id=?',[$vid])->fetchColumn()===$originalStock-2,'variant stock deducted');
 $r=$customer->post('/checkout',$payload);ok($r['location']==='/order/'.$number && query('SELECT COUNT(*) FROM orders WHERE checkout_key=?',[$key[1]])->fetchColumn()===1,'checkout replay is idempotent');
 $stranger=new Client();ok($stranger->get('/order/'.$number)['status']===422,'order IDOR blocked');ok($stranger->post('/api/action',['action'=>'coupon','code'=>'WELCOME10'])['status']===422,'guest coupon blocked');
 query('INSERT INTO users(role_id,name,email,password_hash) VALUES(2,?,?,?)',['Journey Admin',$adminEmail,Auth::hash($password)]);$ids[]=(int)db()->lastInsertId();$admin=new Client();$admin->post('/admin/login',['action'=>'login','email'=>$adminEmail,'password'=>$password]);$admin->get('/admin');
 $productData=['action'=>'product','section'=>'products','id'=>0,'category_id'=>3,'name'=>'Journey Product','slug'=>$prefix,'sku'=>$prefix,'type'=>'clothing','short_description'=>'Test product','description'=>'Test description','price'=>100000,'sale_price'=>'','cost_price'=>50000,'status'=>'draft'];
 ok($admin->post('/admin/products',$productData)['status']===303,'admin product create');$pid=(int)query('SELECT id FROM products WHERE sku=?',[$prefix])->fetchColumn();$productData['id']=$pid;$productData['name']='Updated Journey Product';ok($admin->post('/admin/products',$productData)['status']===303,'admin product update');ok(query('SELECT name FROM products WHERE id=?',[$pid])->fetchColumn()==='Updated Journey Product','product changes persisted');
 ok($admin->post('/admin/variants',['action'=>'variant','section'=>'variants','product_id'=>$pid,'sku'=>$prefix.'-M','size'=>'M','color'=>'Black','volume'=>'','price_adjustment'=>0,'stock'=>10,'minimum_stock'=>2])['status']===303,'variant creation');
 $upload=$admin->post('/admin/products',['action'=>'upload','section'=>'products','product_id'=>$pid,'alt'=>'Test photo','position'=>0,'image'=>new CURLFile(ROOT.'/public/assets/images/tee.jpg','image/jpeg','test.jpg')]);ok($upload['status']===303,'validated image upload and re-encoding');$uploaded=query('SELECT path FROM product_images WHERE product_id=?',[$pid])->fetchColumn();ok($admin->get($uploaded)['status']===200,'private upload served as image');
 ok($admin->post('/admin/products',['action'=>'upload','section'=>'products','product_id'=>$pid,'alt'=>'Bad upload','position'=>1,'image'=>new CURLFile(__FILE__,'image/jpeg','bad.jpg')])['status']===422,'spoofed image upload rejected');
 ok($admin->post('/admin/products',['action'=>'archive','section'=>'products','id'=>$pid])['status']===303,'product archive');
 foreach(array_keys(Admin::SECTIONS) as $section)ok($admin->get('/admin/'.$section)['status']===200,'admin page '.$section);
 ok($admin->post('/admin/orders',['action'=>'order','section'=>'orders','id'=>$oid,'status'=>'delivered'])['status']===422,'illegal order transition rejected');
 foreach(['processing'] as $status)ok($admin->post('/admin/orders',['action'=>'order','section'=>'orders','id'=>$oid,'status'=>$status])['status']===303,'order '.$status);
 ok($admin->post('/admin/orders',['action'=>'shipment','section'=>'orders','id'=>$oid,'carrier'=>'Test Carrier','tracking_number'=>'TRACK-TEST'])['status']===303,'shipment creation');
 foreach(['shipped','out_for_delivery','delivered'] as $status)ok($admin->post('/admin/orders',['action'=>'order','section'=>'orders','id'=>$oid,'status'=>$status])['status']===303,'order '.$status);
 ok($admin->post('/admin/orders',['action'=>'paid','section'=>'orders','id'=>$oid])['status']===303,'COD payment collection');
 ok($customer->post('/api/action',['action'=>'review','product_id'=>1,'rating'=>5,'body'=>'Test review <script>alert(1)</script>'])['status']===200,'verified purchase review');
 $rid=query('SELECT id FROM reviews WHERE user_id=?',[$uid])->fetchColumn();ok($admin->post('/admin/reviews',['action'=>'review','section'=>'reviews','id'=>$rid,'status'=>'approved'])['status']===303,'review moderation');ok(str_contains($customer->get('/product/signature-oversized-t-shirt')['body'],'&lt;script&gt;alert(1)&lt;/script&gt;'),'stored review XSS escaped');
 ok($customer->post('/api/action',['action'=>'return','number'=>$number,'reason'=>'Test return'])['status']===200,'customer return request');$returnId=query('SELECT id FROM returns WHERE order_id=?',[$oid])->fetchColumn();
 foreach(['approved','received'] as $status)ok($admin->post('/admin/returns',['action'=>'return-status','section'=>'returns','id'=>$returnId,'status'=>$status])['status']===303,'return '.$status);
 ok($admin->post('/admin/orders',['action'=>'order','section'=>'orders','id'=>$oid,'status'=>'returned'])['status']===303,'returned order');ok((int)query('SELECT stock FROM inventory WHERE variant_id=?',[$vid])->fetchColumn()===$originalStock,'returned inventory restored once');
 ok($admin->post('/admin/refunds',['action'=>'refund','section'=>'refunds','id'=>$oid,'amount'=>$order['total']+1,'reference'=>$prefix])['status']===422,'excess refund rejected');
 ok($admin->post('/admin/refunds',['action'=>'refund','section'=>'refunds','id'=>$oid,'amount'=>$order['total'],'reference'=>$prefix])['status']===303,'refund reconciliation');
 ok($customer->post('/forgot-password',['action'=>'forgot','email'=>$email])['status']===303,'password reset request');$body=query("SELECT body FROM email_outbox WHERE recipient=? AND subject='Reset your WIKI password' ORDER BY id DESC LIMIT 1",[$email])->fetchColumn();preg_match('/token=([a-f0-9]+)/',$body,$token);$reset=new Client();
 ok($reset->post('/reset-password',['action'=>'reset','token'=>$token[1],'password'=>$password.'new'])['status']===303,'password reset token');ok($reset->post('/reset-password',['action'=>'reset','token'=>$token[1],'password'=>$password.'other'])['status']===422,'reset replay rejected');ok($customer->get('/account')['status']===303,'password reset revokes old sessions');
 echo "Completed $checks journey checks.\n";
} finally {
 transaction(function() use($ids,$email,$adminEmail,$vid,$originalStock,$prefix){
  foreach(query('SELECT id,coupon_id FROM orders WHERE email=?',[$email])->fetchAll() as $o){query('DELETE FROM refunds WHERE order_id=?',[$o['id']]);query('DELETE FROM returns WHERE order_id=?',[$o['id']]);query('DELETE FROM shipments WHERE order_id=?',[$o['id']]);query('DELETE FROM payments WHERE order_id=?',[$o['id']]);query('DELETE FROM coupon_usage WHERE order_id=?',[$o['id']]);if($o['coupon_id'])query('UPDATE coupons SET used_count=GREATEST(0,used_count-1) WHERE id=?',[$o['coupon_id']]);query('DELETE FROM orders WHERE id=?',[$o['id']]);}
  foreach($ids as $id){query('DELETE FROM reviews WHERE user_id=?',[$id]);query('DELETE FROM audit_logs WHERE user_id=?',[$id]);query('DELETE FROM users WHERE id=?',[$id]);}
  query('DELETE FROM email_outbox WHERE recipient IN (?,?)',[$email,$adminEmail]);query('UPDATE inventory SET stock=? WHERE variant_id=?',[$originalStock,$vid]);
  foreach(query('SELECT im.path FROM product_images im JOIN products p ON p.id=im.product_id WHERE p.sku=?',[$prefix])->fetchAll() as $image){$file=ROOT.'/storage/uploads/'.basename($image['path']);if(is_file($file))unlink($file);}query('DELETE FROM products WHERE sku=?',[$prefix]);
 });
}
