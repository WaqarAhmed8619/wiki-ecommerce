<?php
if(PHP_SAPI!=='cli') exit;
$base=rtrim($argv[1]??'http://127.0.0.1:8080','/');
if(!preg_match('~^http://(127\.0\.0\.1|localhost)(:\d+)?$~',$base)) exit("Use an isolated localhost development server.\n");
$cookie=tempnam(sys_get_temp_dir(),'wiki-test-'); $count=0;
function request(string $path,?array $post=null): array { global $base,$cookie; $c=curl_init($base.$path); curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_COOKIEJAR=>$cookie,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_FOLLOWLOCATION=>false]); if($post!==null) {curl_setopt($c,CURLOPT_POST,true);curl_setopt($c,CURLOPT_POSTFIELDS,http_build_query($post));} $result=curl_exec($c); if($result===false) throw new RuntimeException(curl_error($c)); $size=curl_getinfo($c,CURLINFO_HEADER_SIZE); $status=curl_getinfo($c,CURLINFO_HTTP_CODE); curl_close($c); return ['status'=>$status,'headers'=>substr($result,0,$size),'body'=>substr($result,$size)]; }
function check(bool $ok,string $label): void { global $count; if(!$ok) throw new RuntimeException('FAIL: '.$label); $count++; echo 'PASS: '.$label.PHP_EOL; }
try {
 $home=request('/'); check($home['status']===200 && str_contains($home['body'],'Wear your story'),'homepage renders');
 check(str_contains(strtolower($home['headers']),'content-security-policy:') && str_contains(strtolower($home['headers']),'httponly'),'security headers and HttpOnly cookie');
 preg_match('/name="csrf-token" content="([a-f0-9]+)"/',$home['body'],$match); $csrf=$match[1];
 foreach(['/shop','/clothing','/scents','/collections','/new-arrivals','/best-sellers','/sale','/search?q=oud','/product/midnight-oud','/cart','/checkout','/login','/register','/forgot-password','/reset-password','/about','/contact','/privacy','/terms','/shipping','/refund-policy','/faq','/size-guide','/track','/sitemap.xml','/feed.xml'] as $path) { $r=request($path); check($r['status']===200,$path.' renders'); }
 check(request('/account')['status']===303,'account requires login'); check(request('/admin')['status']===303,'admin requires login');
 check(request('/api/action',['action'=>'cart','variant_id'=>1,'quantity'=>1])['status']===403,'CSRF omission rejected');
 $r=request('/api/action',['csrf'=>$csrf,'action'=>'cart','variant_id'=>1,'quantity'=>1,'mode'=>'add','price'=>1]); check($r['status']===200,'cart mutation works');
 check(str_contains(request('/cart')['body'],'Signature Oversized T-Shirt'),'cart persists in server session');
 check(request('/api/action',['csrf'=>$csrf,'action'=>'cart','variant_id'=>1,'quantity'=>999])['status']===422,'excess quantity rejected');
 $catalog=request('/api/products?sort=price-asc');check($catalog['status']===200 && !str_contains($catalog['body'],'cost_price'),'catalog API works without exposing internal cost prices');
 check(request('/config/config.php')['status']===404,'private config not exposed');
 check(request('/database/wiki_ecommerce.sql')['status']===404,'schema not exposed');
 check(request('/order/WK-FAKE')['status']===422,'unknown order access denied');
 request('/api/action',['csrf'=>$csrf,'action'=>'clear-cart']);
 echo "Completed $count HTTP checks.\n";
} finally { @unlink($cookie); }
