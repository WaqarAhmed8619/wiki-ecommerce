<?php
if(PHP_SAPI!=='cli') exit;
require dirname(__DIR__).'/app/bootstrap.php';
if(cfg('env')!=='development') exit("Development only.\n");
if(($argv[1]??'')==='worker') {
    $vid=(int)$argv[2]; $email=$argv[3]; $key=bin2hex(random_bytes(32));
    $_SESSION=['cart'=>[$vid=>1],'checkout_key'=>$key];
    $_POST=['checkout_key'=>$key,'email'=>$email,'first_name'=>'Race','last_name'=>'Test','phone'=>'+923001234567','country'=>'Pakistan','province'=>'Sindh','city'=>'Karachi','address'=>'Test address','postal_code'=>'74000','payment_method'=>'cod','shipping_method'=>'standard','terms'=>'1'];
    try { Commerce::checkout(); echo 'SUCCESS'; } catch(DomainException $e) { echo 'SOLD_OUT'; } exit;
}
$vid=(int)query('SELECT variant_id FROM inventory ORDER BY variant_id LIMIT 1')->fetchColumn();$stock=(int)query('SELECT stock FROM inventory WHERE variant_id=?',[$vid])->fetchColumn();$email='race-'.bin2hex(random_bytes(8)).'@example.test';
try {
 query('UPDATE inventory SET stock=1 WHERE variant_id=?',[$vid]);$workers=[];
 for($i=0;$i<2;$i++){ $pipes=[];$process=proc_open([PHP_BINARY,__FILE__,'worker',(string)$vid,$email],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fclose($pipes[0]);$workers[]=[$process,$pipes]; }
 $results=[];foreach($workers as [$process,$pipes]){$results[]=trim(stream_get_contents($pipes[1]));$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);proc_close($process);if($error)throw new RuntimeException($error);}
 sort($results);if($results!==['SOLD_OUT','SUCCESS'])throw new RuntimeException('Unexpected results: '.json_encode($results));
 if((int)query('SELECT stock FROM inventory WHERE variant_id=?',[$vid])->fetchColumn()!==0)throw new RuntimeException('Stock must be zero');
 if((int)query('SELECT COUNT(*) FROM orders WHERE email=?',[$email])->fetchColumn()!==1)throw new RuntimeException('Exactly one order must exist');
 echo "PASS: simultaneous full checkouts produce one order and zero remaining stock.\n";
} finally {
 transaction(function() use($vid,$stock,$email){ foreach(query('SELECT id FROM orders WHERE email=?',[$email])->fetchAll() as $o){query('DELETE FROM payments WHERE order_id=?',[$o['id']]);query('DELETE FROM orders WHERE id=?',[$o['id']]);}query('DELETE FROM email_outbox WHERE recipient=?',[$email]);query('UPDATE inventory SET stock=? WHERE variant_id=?',[$stock,$vid]); });
}
