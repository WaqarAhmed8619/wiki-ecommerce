<?php
// Local demonstration database only; never invoke through HTTP.
if(PHP_SAPI!=='cli') exit;
$root=dirname(__DIR__);
$pdo=new PDO('mysql:host=127.0.0.1;port=3307;charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$pdo->exec(file_get_contents($root.'/database/wiki_ecommerce.sql'));
$password=bin2hex(random_bytes(24));
$pdo->exec("CREATE USER 'wiki_local'@'127.0.0.1' IDENTIFIED BY ".$pdo->quote($password));
$pdo->exec("GRANT SELECT, INSERT, UPDATE, DELETE ON wiki_ecommerce.* TO 'wiki_local'@'127.0.0.1'");
$config=require $root.'/config/config.example.php';
$config['db']=['dsn'=>'mysql:host=127.0.0.1;port=3307;dbname=wiki_ecommerce;charset=utf8mb4','user'=>'wiki_local','password'=>$password];
file_put_contents($root.'/config/config.php',"<?php\nreturn ".var_export($config,true).";\n");
echo "Isolated local database initialized. Private configuration saved.\n";
