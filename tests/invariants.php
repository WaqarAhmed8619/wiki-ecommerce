<?php
if(PHP_SAPI!=='cli') exit;
require dirname(__DIR__).'/app/bootstrap.php';
$_SESSION=[];
$count=0;
function expect(bool $ok,string $name): void { global $count; if(!$ok) throw new RuntimeException('FAIL: '.$name); $count++; echo 'PASS: '.$name.PHP_EOL; }
expect(Auth::hash('a long test password')!=='a long test password','passwords are hashed');
expect(password_verify('a long test password',Auth::hash('a long test password')),'password verification');
expect(e('<script>"')==='&lt;script&gt;&quot;','HTML output escaping');
expect(count(Catalog::products(['q'=>"' OR 1=1 --"]))===0,'search binds SQL metacharacters');
expect(count(Catalog::products(['type'=>'scents']))===5,'fragrance filtering');
$asc=Catalog::products(['sort'=>'price-asc']); $prices=array_map(fn($p)=>(int)($p['sale_price']??$p['price']),$asc); $sorted=$prices; sort($sorted); expect($sorted===$prices,'numeric price sorting');
db()->beginTransaction();
try {
 $v=query("SELECT v.id,p.price FROM product_variants v JOIN products p ON p.id=v.product_id WHERE p.slug='signature-oversized-t-shirt' ORDER BY v.id LIMIT 1")->fetch();
 $_POST=['variant_id'=>$v['id'],'quantity'=>2,'mode'=>'add']; Commerce::changeCart(); $items=Commerce::cart(true); expect(count($items)===1 && $items[0]['quantity']===2,'session cart stores variant quantities');
 $_POST['price']=1; $totals=Commerce::totals($items); expect($totals['subtotal']===$v['price']*2,'client price ignored');
 $_POST=['variant_id'=>$v['id'],'quantity'=>999]; try { Commerce::changeCart(); expect(false,'stock limit'); } catch(DomainException $e) { expect(true,'quantity and stock limit'); }
 query('UPDATE inventory SET stock=1 WHERE variant_id=?',[$v['id']]); $affected=query('UPDATE inventory SET stock=stock-2 WHERE variant_id=? AND stock>=2',[$v['id']])->rowCount(); expect($affected===0,'conditional stock update prevents negatives');
 expect((int)query('SELECT stock FROM inventory WHERE variant_id=?',[$v['id']])->fetchColumn()===1,'stock remains non-negative');
 try { Commerce::totals($items,'INVALID'); expect(false,'invalid coupon'); } catch(DomainException $e) { expect(true,'invalid coupons rejected'); }
 try { Commerce::totals($items,'WELCOME10'); expect(false,'guest coupon'); } catch(DomainException $e) { expect(true,'coupon requires stable account identity'); }
 $_POST=['variant_id'=>$v['id'],'quantity'=>0]; Commerce::changeCart(); expect(!Commerce::cart(),'zero quantity removes variant');
 expect(!allowed('catalog.manage'),'anonymous admin permission denied');
} finally { db()->rollBack(); }
echo "Completed $count invariant checks.\n";
