<?php
// Generate a clean deployment bundle. Never copy local accounts, logs or secrets.
if (PHP_SAPI !== 'cli') exit;
$root=dirname(__DIR__);
$out=$root.'/storage/runtime/infinityfree';
$stage=$out.'/htdocs';
if(is_dir($stage)) exit("Package already exists; inspect it before rebuilding.\n");
mkdir($stage,0700,true);
function copyTree(string $from,string $to): void {
    if(!is_dir($to)) mkdir($to,0700,true);
    foreach(new DirectoryIterator($from) as $entry) {
        if($entry->isDot()) continue;
        $name=$entry->getFilename();
        if($entry->isDir()) copyTree($entry->getPathname(),$to.'/'.$name);
        elseif(!in_array($name,['router.php']) && !str_ends_with($name,'.png')) copy($entry->getPathname(),$to.'/'.$name);
    }
}
foreach(['app','views','public'] as $dir) copyTree($root.'/'.$dir,$stage.'/'.$dir);
foreach(['config','storage/logs','storage/uploads','storage/runtime'] as $dir) mkdir($stage.'/'.$dir,0700,true);
foreach(['app','views','config','storage'] as $dir) file_put_contents($stage.'/'.$dir.'/.htaccess',"Require all denied\n");
file_put_contents($stage.'/index.php',"<?php\nrequire __DIR__.'/public/index.php';\n");
file_put_contents($stage.'/.htaccess', <<<'HTACCESS'
Options -Indexes
DirectoryIndex index.php
RewriteEngine On
RewriteCond %{HTTPS} !=on
RewriteRule ^ https://wikifits.infinityfree.io%{REQUEST_URI} [R=301,L]
RewriteRule ^(?:app|config|views|storage|database|bin|tests)(?:/|$) - [F,L,NC]
RewriteRule (^|/)\. - [F,L]
RewriteRule ^public/(?!assets/) - [F,L,NC]
RewriteRule ^assets/(.*)$ public/assets/$1 [L]
RewriteRule ^robots\.txt$ public/robots.txt [L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^ index.php [QSA,L]
<FilesMatch "\.(env|sql|log|ini|bak|zip)$">
Require all denied
</FilesMatch>
HTACCESS);
$layout=file_get_contents($stage.'/views/layout.php');
$layout=str_replace('<body>','<body><div class="announcement" role="note">PORTFOLIO DEMO · No real payments or deliveries. Please use sample details.</div>',$layout);
file_put_contents($stage.'/views/layout.php',$layout);
$checkout=file_get_contents($stage.'/views/checkout.php');
$checkout=str_replace(['Pay when your order arrives','I agree to the','terms & conditions','Place order ·'],['Demo only — no payment or delivery','I understand this is a portfolio demo. Read the','demo terms','Place demo order ·'],$checkout);
file_put_contents($stage.'/views/checkout.php',$checkout);
file_put_contents($stage.'/public/robots.txt',"User-agent: *\nDisallow: /admin\nDisallow: /account\nDisallow: /checkout\nDisallow: /cart\nDisallow: /api\nSitemap: https://wikifits.infinityfree.io/sitemap.xml\n");
$schema=file_get_contents($root.'/database/wiki_ecommerce.sql');
$schema=preg_replace('/^(CREATE DATABASE[^\r\n]*|USE wiki_ecommerce;|SET default_storage_engine=InnoDB;)\R?/m','',$schema);
$schema=preg_replace('/^(CREATE TABLE .+)\);$/m','$1) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',$schema);
$sql="SET NAMES utf8mb4;\n".$schema."\nSTART TRANSACTION;\n";
$lastId=0; $ids=[];
function sqlValue(mixed $value): string {
    if($value===null) return 'NULL';
    if(is_int($value)) return (string)$value;
    return "'".str_replace(["\\","'","\r","\n"],["\\\\","''","\\r","\\n"],(string)$value)."'";
}
function query(string $statement,array $params=[]): object {
    global $sql,$ids,$lastId;
    if(str_starts_with($statement,'SELECT')) return new class {function fetchColumn(){return 0;}};
    if($params) { $parts=explode('?',$statement); $statement=array_shift($parts); foreach($params as $param) $statement.=sqlValue($param).array_shift($parts); }
    $sql.=$statement.";\n";
    if(preg_match('/^INSERT INTO (products|product_variants)\(/',$statement,$m)) $lastId=$ids[$m[1]]=($ids[$m[1]]??0)+1;
    return new stdClass;
}
function db(): object {return new class {function lastInsertId(){global $lastId; return $lastId;}};}
function transaction(callable $fn): void {$fn();}
require $root.'/database/seed.php';
seed();
$password=bin2hex(random_bytes(18));
query('INSERT INTO users(role_id,name,email,password_hash,verified_at) VALUES(2,?,?,?,UTC_TIMESTAMP())',['WIKI Portfolio Administrator','admin@wiki.local',password_hash($password,PASSWORD_BCRYPT,['cost'=>12])]);
query("UPDATE site_settings SET value='PORTFOLIO DEMO · EXPLORE CLOTHING & SCENTS' WHERE name='announcement'");
$sql.="COMMIT;\n";
file_put_contents($out.'/wiki-import.sql',$sql);
file_put_contents($out.'/admin-credentials.txt',"Live website: https://wikifits.infinityfree.io\nLogin: https://wikifits.infinityfree.io/admin/login\nEmail: admin@wiki.local\nPassword: ".$password."\nKeep this file private. Do not upload it.\n");
echo "Clean deployment files and fresh database prepared. Configuration must be added privately on the host.\n";
