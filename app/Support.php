<?php
function cfg(string $key): mixed { global $config; return $config[$key] ?? null; }
function db(): PDO { static $pdo; if (!$pdo) { $d=cfg('db'); $pdo=new PDO($d['dsn'],$d['user'],$d['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]); } return $pdo; }
function query(string $sql, array $params=[]): PDOStatement { $s=db()->prepare($sql); $s->execute($params); return $s; }
function e(mixed $v): string { return htmlspecialchars((string)$v, ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function money(int $minor): string { return cfg('currency').' '.number_format($minor/100,0); }
function redirect(string $url): never { header('Location: '.$url, true,303); exit; }
function csrf(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function csrf_field(): string { return '<input type="hidden" name="csrf" value="'.e(csrf()).'">'; }
function check_csrf(): void { if (!hash_equals(csrf(), (string)($_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''))) { http_response_code(403); throw new DomainException('Your session expired. Refresh and try again.'); } }
function input(string $key, int $max=255, bool $required=true): string { $value=$_POST[$key] ?? ''; if (!is_string($value)) throw new DomainException('Invalid input.'); $v=trim($value); if (($required && $v==='') || mb_strlen($v)>$max) throw new DomainException('Please check the '.str_replace('_',' ',$key).' field.'); return $v; }
function integer(string $key,int $min=0,int $max=100000000): int { $v=filter_var($_POST[$key] ?? null,FILTER_VALIDATE_INT); if ($v===false || $v===null || $v<$min || $v>$max) throw new DomainException('Invalid '.str_replace('_',' ',$key).'.'); return $v; }
function email_input(): string { $v=strtolower(input('email',190)); if (!filter_var($v,FILTER_VALIDATE_EMAIL)) throw new DomainException('Enter a valid email address.'); return $v; }
function user(): ?array { static $u=false; if ($u===false) { $u=isset($_SESSION['uid']) ? query('SELECT id,name,email,phone,role_id,session_version FROM users WHERE id=?',[$_SESSION['uid']])->fetch() : null; if ($u && $u['session_version']!==($_SESSION['version']??null)) { unset($_SESSION['uid']); $u=null; } } return $u ?: null; }
function require_user(): array { $u=user(); if (!$u) redirect('/login'); return $u; }
function allowed(string $permission): bool { $u=user(); return $u && (bool)query('SELECT 1 FROM role_permissions rp JOIN permissions p ON p.id=rp.permission_id WHERE rp.role_id=? AND p.name=?',[$u['role_id'],$permission])->fetchColumn(); }
function permit(string $permission): void { if (!allowed($permission)) { http_response_code(403); throw new DomainException('You do not have permission for this action.'); } }
function audit(string $action,array $context=[]): void { query('INSERT INTO audit_logs(user_id,action,context) VALUES(?,?,?)',[user()['id']??null,$action,json_encode($context,JSON_THROW_ON_ERROR)]); }
function queue_mail(string $email,string $subject,string $body): void { query('INSERT INTO email_outbox(recipient,subject,body) VALUES(?,?,?)',[$email,$subject,$body]); }
function flash(string $message): void { $_SESSION['flash']=$message; }
function image_path(array $p): string { $path=$p['image'] ?: '/assets/images/placeholder.svg'; if(str_starts_with($path,'/assets/images/')) { $webp=preg_replace('/\.(jpg|png)$/','.webp',$path); if(is_file(ROOT.'/public'.$webp)) return $webp; } return $path; }
function image_srcset(array $p): string { $path=image_path($p); if(str_starts_with($path,'/assets/images/') && str_ends_with($path,'.webp')) { $base=substr($path,0,-5); if(is_file(ROOT.'/public'.$base.'-480.webp')) return $base.'-480.webp 480w, '.$base.'-960.webp 960w'; } return ''; }
function render(string $view,array $data=[]): void { global $path; extract($data,EXTR_SKIP); ob_start(); require ROOT.'/views/'.$view.'.php'; $content=ob_get_clean(); require ROOT.'/views/layout.php'; }
function transaction(callable $fn): mixed { db()->beginTransaction(); try { $result=$fn(); db()->commit(); return $result; } catch(Throwable $e) { if(db()->inTransaction()) db()->rollBack(); throw $e; } }
