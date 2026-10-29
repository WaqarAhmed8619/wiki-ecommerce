<?php
class Auth {
    public static function throttle(string $identity, int $limit=8): void {
        $keys=[hash('sha256',$identity),hash('sha256','ip:'.($_SERVER['REMOTE_ADDR']??'cli'))];
        foreach($keys as $i=>$key) {
            if ((int)query('SELECT COUNT(*) FROM login_attempts WHERE identity_hash=? AND created_at > DATE_SUB(UTC_TIMESTAMP(),INTERVAL 15 MINUTE)',[$key])->fetchColumn()>=($i ? 40 : $limit)) throw new DomainException('Too many attempts. Please try again in 15 minutes.');
        }
        foreach($keys as $key) query('INSERT INTO login_attempts(identity_hash) VALUES(?)',[$key]);
    }
    public static function password(): string { $p=input('password',200); if(strlen($p)<12) throw new DomainException('Use a password with at least 12 characters.'); return $p; }
    public static function hash(string $p): string { return password_hash($p,defined('PASSWORD_ARGON2ID')?PASSWORD_ARGON2ID:PASSWORD_BCRYPT); }
    public static function register(): void {
        $email=email_input(); self::throttle('register:'.$email); $name=input('name',120); $hash=self::hash(self::password());
        try { query('INSERT INTO users(name,email,password_hash) VALUES(?,?,?)',[$name,$email,$hash]); } catch(PDOException $e) { if($e->getCode()==='23000') throw new DomainException('This email cannot be registered. Try signing in or resetting your password.'); throw $e; }
        $uid=(int)db()->lastInsertId(); $token=bin2hex(random_bytes(32));
        query('INSERT INTO email_verifications(user_id,token_hash,expires_at) VALUES(?,?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY))',[$uid,hash('sha256',$token)]);
        queue_mail($email,'Welcome to WIKI','Welcome to WIKI. Your wardrobe, thoughtfully considered. Verify your email using this private link: '.cfg('url').'/verify-email?token='.$token); flash('Your account is ready. Check your welcome email to verify your address.'); redirect('/login');
    }
    public static function login(): void {
        $email=email_input(); self::throttle('login:'.$email); $u=query('SELECT * FROM users WHERE email=?',[$email])->fetch();
        $dummy='$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
        if (!password_verify(input('password',200),$u['password_hash']??$dummy) || !$u) throw new DomainException('Email or password is incorrect.');
        session_regenerate_id(true); $_SESSION['uid']=$u['id']; $_SESSION['version']=$u['session_version']; $_SESSION['csrf']=bin2hex(random_bytes(32));
        if(password_needs_rehash($u['password_hash'],defined('PASSWORD_ARGON2ID')?PASSWORD_ARGON2ID:PASSWORD_BCRYPT)) query('UPDATE users SET password_hash=? WHERE id=?',[self::hash(input('password',200)),$u['id']]);
        query('UPDATE login_attempts SET success=1 WHERE identity_hash=? ORDER BY id DESC LIMIT 1',[hash('sha256','login:'.$email)]);
        audit('login'); redirect($u['role_id']==1?'/account':'/admin');
    }
    public static function forgot(): void {
        $email=email_input(); self::throttle('reset:'.$email,4); $u=query('SELECT id FROM users WHERE email=?',[$email])->fetch();
        if($u) { $token=bin2hex(random_bytes(32)); transaction(function() use($u,$token,$email) { query('DELETE FROM password_resets WHERE user_id=?',[$u['id']]); query('INSERT INTO password_resets(user_id,token_hash,expires_at) VALUES(?,?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 30 MINUTE))',[$u['id'],hash('sha256',$token)]); queue_mail($email,'Reset your WIKI password',cfg('url').'/reset-password?token='.$token); }); }
        flash('If the account exists, a password reset email has been queued.'); redirect('/forgot-password');
    }
    public static function reset(): void {
        $hash=self::hash(self::password()); $token=input('token',128); self::throttle('reset-token:'.hash('sha256',$token));
        transaction(function() use($hash,$token) { $r=query('SELECT * FROM password_resets WHERE token_hash=? AND expires_at>UTC_TIMESTAMP() FOR UPDATE',[hash('sha256',$token)])->fetch(); if(!$r) throw new DomainException('This reset link has expired.'); query('UPDATE users SET password_hash=?,session_version=session_version+1 WHERE id=?',[$hash,$r['user_id']]); query('DELETE FROM password_resets WHERE user_id=?',[$r['user_id']]); });
        flash('Password updated. Please sign in again.'); redirect('/login');
    }
}
