<?php
if(PHP_SAPI!=='cli') exit;
require dirname(__DIR__).'/app/bootstrap.php';
$email=strtolower(trim($argv[1]??'')); $name=trim($argv[2]??'WIKI Administrator');
if(!filter_var($email,FILTER_VALIDATE_EMAIL)) exit("Usage: php bin/create-admin.php admin@example.com \"Name\"\nSet WIKI_ADMIN_PASSWORD in the process environment first.\n");
$password=getenv('WIKI_ADMIN_PASSWORD')?:'';
if(strlen($password)<12) exit("WIKI_ADMIN_PASSWORD must contain at least 12 characters. No default password is supplied.\n");
query('INSERT INTO users(role_id,name,email,password_hash) VALUES(2,?,?,?)',[$name,$email,Auth::hash($password)]);
echo "Administrator created. Clear WIKI_ADMIN_PASSWORD from your environment.\n";
