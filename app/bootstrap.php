<?php
declare(strict_types=1);
define('ROOT', dirname(__DIR__));
$configFile = ROOT . '/config/config.php';
$config = require is_file($configFile) ? $configFile : ROOT . '/config/config.example.php';
date_default_timezone_set('UTC');
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', ROOT . '/storage/logs/php.log');
require ROOT . '/app/Support.php';
spl_autoload_register(function(string $class): void { if (preg_match('/^[A-Za-z]+$/', $class) && is_file(ROOT . '/app/' . $class . '.php')) require ROOT . '/app/' . $class . '.php'; });
if (PHP_SAPI !== 'cli') {
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    if ($config['env'] === 'production' && !$https) { http_response_code(400); exit('HTTPS is required. Configure TLS at the application server.'); }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('wiki_session');
    $sessionDirectory=ROOT.'/storage/runtime/sessions';
    if(!is_dir($sessionDirectory)) mkdir($sessionDirectory,0700,true);
    session_save_path($sessionDirectory);
    session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$https,'httponly'=>true,'samesite'=>'Lax']);
    session_start();
    if (isset($_SESSION['last_seen']) && time() - $_SESSION['last_seen'] > 7200) { $_SESSION = []; session_regenerate_id(true); }
    $_SESSION['last_seen'] = time();
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
    header('X-Content-Type-Options: nosniff'); header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header('Cache-Control: no-store');
    if ($https) header('Strict-Transport-Security: max-age=31536000');
}
set_exception_handler(function(Throwable $e): void {
    while(ob_get_level()>0) ob_end_clean();
    error_log((string)$e); http_response_code(500);
    if (str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/')) { header('Content-Type: application/json'); echo json_encode(['error'=>'Something went wrong. Please try again.']); }
    else echo '<!doctype html><html lang="en"><meta charset="utf-8"><title>WIKI · Temporarily unavailable</title><h1>WIKI</h1><p>We could not complete your request. Please try again shortly.</p><p>First installation? Follow README.md to configure and import the database.</p></html>';
});
