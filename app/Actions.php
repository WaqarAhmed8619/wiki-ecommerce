<?php
class Actions {
    public static function run(string $action): ?array {
        switch($action) {
            case 'register': Auth::register(); break;
            case 'login': Auth::login(); break;
            case 'forgot': Auth::forgot(); break;
            case 'reset': Auth::reset(); break;
            case 'verify-email':
                $token=input('token',128); transaction(function() use($token){ $r=query('SELECT * FROM email_verifications WHERE token_hash=? AND expires_at>UTC_TIMESTAMP() FOR UPDATE',[hash('sha256',$token)])->fetch(); if(!$r) throw new DomainException('This verification link is invalid or expired.'); query('UPDATE users SET verified_at=UTC_TIMESTAMP() WHERE id=?',[$r['user_id']]); query('DELETE FROM email_verifications WHERE user_id=?',[$r['user_id']]); }); flash('Email verified. Welcome to WIKI.'); redirect('/account');
            case 'logout': $_SESSION=[]; session_regenerate_id(true); redirect('/');
            case 'cart': return Commerce::changeCart();
            case 'clear-cart': unset($_SESSION['cart'],$_SESSION['coupon']); return ['message'=>'Bag cleared'];
            case 'coupon': $code=strtoupper(input('code',40,false)); Commerce::totals(Commerce::cart(),$code); $_SESSION['coupon']=$code; return ['message'=>'Coupon updated'];
            case 'checkout': redirect('/order/'.Commerce::checkout());
            case 'wishlist':
                $u=require_user(); $pid=integer('product_id',1);
                if(!query("SELECT 1 FROM products WHERE id=? AND status='active'",[$pid])->fetchColumn()) throw new DomainException('Product unavailable.');
                query('INSERT IGNORE INTO wishlists(user_id) VALUES(?)',[$u['id']]); $wid=query('SELECT id FROM wishlists WHERE user_id=?',[$u['id']])->fetchColumn();
                if(query('SELECT 1 FROM wishlist_items WHERE wishlist_id=? AND product_id=?',[$wid,$pid])->fetchColumn()) { query('DELETE FROM wishlist_items WHERE wishlist_id=? AND product_id=?',[$wid,$pid]); return ['message'=>'Removed from wishlist']; }
                query('INSERT INTO wishlist_items(wishlist_id,product_id) VALUES(?,?)',[$wid,$pid]); return ['message'=>'Saved to your wishlist'];
            case 'newsletter':
                $email=email_input(); Auth::throttle('newsletter:'.$email,3); if(($_POST['consent']??'')!=='1') throw new DomainException('Please consent to receiving our newsletter.');
                query('INSERT INTO newsletter_subscribers(email) VALUES(?) ON DUPLICATE KEY UPDATE active=1',[$email]); return ['message'=>'You are on the list. Welcome to WIKI.'];
            case 'profile':
                $u=require_user(); query('UPDATE users SET name=?,phone=? WHERE id=?',[input('name',120),input('phone',30,false),$u['id']]); return ['message'=>'Profile saved'];
            case 'password':
                $u=require_user(); Auth::throttle('change-password:'.$u['id']); $hash=query('SELECT password_hash FROM users WHERE id=?',[$u['id']])->fetchColumn(); if(!password_verify(input('current_password',200),$hash)) throw new DomainException('Current password is incorrect.'); query('UPDATE users SET password_hash=?,session_version=session_version+1 WHERE id=?',[Auth::hash(Auth::password()),$u['id']]); unset($_SESSION['uid']); flash('Password changed. Sign in again.'); redirect('/login');
            case 'address':
                $u=require_user(); $id=isset($_POST['id'])?integer('id',0):0;
                $values=[input('label',60),input('address',500),input('city',100),input('province',100),input('country',80),input('postal_code',20)];
                if($id) { query('UPDATE user_addresses SET label=?,address=?,city=?,province=?,country=?,postal_code=? WHERE id=? AND user_id=?',array_merge($values,[$id,$u['id']])); }
                else { if(query('SELECT COUNT(*) FROM user_addresses WHERE user_id=?',[$u['id']])->fetchColumn()>=10) throw new DomainException('You can save up to 10 addresses.'); query('INSERT INTO user_addresses(label,address,city,province,country,postal_code,user_id) VALUES(?,?,?,?,?,?,?)',array_merge($values,[$u['id']])); } return ['message'=>'Address saved'];
            case 'delete-address': $u=require_user(); query('DELETE FROM user_addresses WHERE id=? AND user_id=?',[integer('id',1),$u['id']]); return ['message'=>'Address removed'];
            case 'review':
                $u=require_user(); $pid=integer('product_id',1); $verified=(bool)query("SELECT 1 FROM order_items i JOIN orders o ON o.id=i.order_id WHERE o.user_id=? AND i.product_id=? AND o.status='delivered'",[$u['id'],$pid])->fetchColumn();
                if(!$verified) throw new DomainException('Reviews are available after your purchase has been delivered.');
                query("INSERT INTO reviews(product_id,user_id,rating,body,verified) VALUES(?,?,?,?,1) ON DUPLICATE KEY UPDATE rating=VALUES(rating),body=VALUES(body),status='pending'",[$pid,$u['id'],integer('rating',1,5),input('body',3000)]); return ['message'=>'Thank you. Your review is awaiting moderation.'];
            case 'return':
                $o=Commerce::order(input('number',40)); if($o['status']!=='delivered') throw new DomainException('Returns can be requested for delivered orders.');
                if(strtotime($o['updated_at']) < time()-14*86400) throw new DomainException('The 14-day return window has passed. Please contact us.');
                query("INSERT INTO returns(order_id,user_id,reason) VALUES(?,?,?) ON DUPLICATE KEY UPDATE reason=IF(status='requested',VALUES(reason),reason)",[$o['id'],user()['id']??null,input('reason',2000)]); queue_mail($o['email'],'WIKI return request received','We have received your return request for '.$o['number'].'.'); return ['message'=>'Your return request has been received'];
            case 'contact':
                $email=email_input(); Auth::throttle('contact:'.$email,3); query("INSERT INTO notifications(kind,message) VALUES('contact',?)",[$email.': '.input('message',400)]); return ['message'=>'Thank you. Your message has been received.'];
            case 'consent': $_SESSION['analytics_consent']=($_POST['value']??'')==='yes'; return ['message'=>'Privacy preference saved'];
            case 'event':
                if(empty($_SESSION['analytics_consent'])) return ['message'=>'Analytics disabled'];
                $event=input('event',40); if(!in_array($event,['page_view','product_view','cart_add','checkout_started','search'],true)) throw new DomainException('Invalid event.');
                query('INSERT INTO analytics_events(session_hash,event,detail) VALUES(?,?,?)',[hash('sha256',session_id().gmdate('Y-m-d')),$event,input('detail',190,false)]); return ['message'=>'Recorded'];
            default: throw new DomainException('Unknown action.');
        } return null;
    }
}
