<?php
interface PaymentGateway { public function create(int $orderId,int $amount): void; }
final class CashOnDelivery implements PaymentGateway { public function create(int $orderId,int $amount): void { query("INSERT INTO payments(order_id,provider,amount,status) VALUES(?,'cod',?,'pending')",[$orderId,$amount]); } }
class Commerce {
    public static function cart(bool $lock=false): array {
        $items=[]; $cart=$_SESSION['cart']??[]; ksort($cart,SORT_NUMERIC);
        foreach($cart as $id=>$qty) {
            $v=query("SELECT v.*,p.name,p.slug,p.status,COALESCE(p.sale_price,p.price)+v.price_adjustment unit_price,(SELECT path FROM product_images WHERE product_id=p.id ORDER BY position,id LIMIT 1) image FROM product_variants v JOIN products p ON p.id=v.product_id WHERE v.id=?".($lock?' FOR UPDATE':''),[(int)$id])->fetch();
            if(!$v || $v['status']!=='active') throw new DomainException('An item is no longer available. Remove it from your cart or clear the cart.');
            $stock=query('SELECT stock FROM inventory WHERE variant_id=?'.($lock?' FOR UPDATE':''),[$id])->fetchColumn();
            $v['stock']=(int)$stock; $v['quantity']=(int)$qty;
            if($v['unit_price']<0) throw new DomainException('This item cannot be purchased.');
            $v['line_total']=(int)$v['unit_price']*$qty; $items[]=$v;
        } return $items;
    }
    public static function totals(array $items,string $code='',string $email='',bool $lock=false): array {
        $subtotal=array_sum(array_column($items,'line_total')); $discount=0; $coupon=null;
        if($code!=='') {
            $coupon=query('SELECT * FROM coupons WHERE code=?'.($lock?' FOR UPDATE':''),[strtoupper($code)])->fetch();
            if(!$coupon || !$coupon['active'] || strtotime($coupon['starts_at'])>time() || strtotime($coupon['expires_at'])<time() || $coupon['used_count'] >= $coupon['usage_limit'] || $subtotal < $coupon['minimum_amount']) throw new DomainException('This coupon is unavailable or the minimum spend has not been reached.');
            // Coupons require an authenticated identity, preventing guest email rotation.
            $u=user(); if(!$u) throw new DomainException('Sign in to redeem a coupon.');
            $identity=hash('sha256','user:'.$u['id']);
            if(query('SELECT COUNT(*) FROM coupon_usage WHERE coupon_id=? AND identity_hash=?',[$coupon['id'],$identity])->fetchColumn() >= $coupon['per_user_limit']) throw new DomainException('You have already redeemed this coupon.');
            $discount=$coupon['type']==='percentage' ? intdiv($subtotal*(int)$coupon['value'],100) : (int)$coupon['value'];
            if($coupon['maximum_discount']!==null) $discount=min($discount,(int)$coupon['maximum_discount']);
            $discount=min($discount,$subtotal);
        }
        $shipping=($subtotal===0 || $subtotal>=cfg('free_shipping'))?0:(int)cfg('shipping');
        $tax=intdiv(($subtotal-$discount)*(int)cfg('tax_basis_points')+5000,10000);
        return compact('subtotal','discount','shipping','tax','coupon')+['total'=>$subtotal-$discount+$shipping+$tax];
    }
    public static function changeCart(): array {
        $id=integer('variant_id',1); $qty=integer('quantity',0,20); $mode=input('mode',20,false);
        if($qty===0) { unset($_SESSION['cart'][$id]); return ['message'=>'Item removed']; }
        $v=query("SELECT i.stock FROM inventory i JOIN product_variants v ON v.id=i.variant_id JOIN products p ON p.id=v.product_id WHERE v.id=? AND p.status='active'",[$id])->fetch();
        $qty=$mode==='add'?$qty+(int)($_SESSION['cart'][$id]??0):$qty;
        if(!$v || $qty>$v['stock'] || $qty>20) throw new DomainException('The requested quantity is not available.');
        if(count($_SESSION['cart']??[])>=50 && !isset($_SESSION['cart'][$id])) throw new DomainException('Your cart has reached its item limit.');
        $_SESSION['cart'][$id]=$qty;
        return ['message'=>'Added to your bag','count'=>array_sum($_SESSION['cart'])];
    }
    public static function checkout(): string {
        $email=email_input(); $name=input('first_name',60).' '.input('last_name',60); $phone=input('phone',30);
        if(!preg_match('/^[+0-9 ()-]{7,30}$/',$phone)) throw new DomainException('Enter a valid phone number.');
        $address=[]; foreach(['country','province','city','address','postal_code'] as $field) $address[$field]=input($field,$field==='address'?500:100);
        if(strtolower($address['country'])!=='pakistan') throw new DomainException('Delivery is currently available within Pakistan only.');
        $billing=$address;
        if(isset($_POST['different_billing'])) foreach(['country','province','city','address','postal_code'] as $field) $billing[$field]=input('billing_'.$field,$field==='address'?500:100);
        if(input('payment_method',30)!=='cod') throw new DomainException('This payment method is not enabled.');
        if(input('shipping_method',30)!=='standard') throw new DomainException('Select a valid shipping method.');
        if(($_POST['terms']??'')!=='1') throw new DomainException('Please agree to the terms.');
        $key=input('checkout_key',64); if(!hash_equals($_SESSION['checkout_key']??'',$key)) throw new DomainException('Checkout expired. Refresh and try again.');
        $existing=query('SELECT number FROM orders WHERE checkout_key=?',[$key])->fetchColumn(); if($existing) return $existing;
        $notes=input('notes',2000,false); $token=bin2hex(random_bytes(32));
        $number=transaction(function() use($email,$name,$phone,$address,$billing,$key,$notes,$token) {
            $items=self::cart(true); if(!$items) throw new DomainException('Your bag is empty.');
            foreach($items as $item) if($item['quantity']>$item['stock']) throw new DomainException($item['name'].' no longer has enough stock. Please update your bag.');
            $totals=self::totals($items,$_SESSION['coupon']??'',$email,true);
            $number='WK-'.gmdate('ymd').'-'.strtoupper(bin2hex(random_bytes(5)));
            query('INSERT INTO orders(number,user_id,guest_token_hash,checkout_key,email,customer_name,phone,shipping_address,billing_address,notes,shipping_method,subtotal,discount,shipping,tax,total,coupon_id) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',[$number,user()['id']??null,hash('sha256',$token),$key,$email,$name,$phone,json_encode($address),json_encode($billing),$notes,'standard',$totals['subtotal'],$totals['discount'],$totals['shipping'],$totals['tax'],$totals['total'],$totals['coupon']['id']??null]);
            $oid=(int)db()->lastInsertId();
            foreach($items as $item) {
                $variant=trim($item['size'].' '.$item['color'].' '.$item['volume']);
                query('INSERT INTO order_items(order_id,product_id,variant_id,name,sku,variant,quantity,unit_price) VALUES(?,?,?,?,?,?,?,?)',[$oid,$item['product_id'],$item['id'],$item['name'],$item['sku'],$variant,$item['quantity'],$item['unit_price']]);
                $updated=query('UPDATE inventory SET stock=stock-? WHERE variant_id=? AND stock>=?',[$item['quantity'],$item['id'],$item['quantity']]);
                if($updated->rowCount()!==1) throw new DomainException('Stock changed. Please retry.');
                if(query('SELECT stock<=minimum_stock FROM inventory WHERE variant_id=?',[$item['id']])->fetchColumn()) query("INSERT INTO notifications(kind,message) VALUES('low_stock',?)",['Low stock: '.$item['sku']]);
            }
            if($totals['coupon']) { query('UPDATE coupons SET used_count=used_count+1 WHERE id=?',[$totals['coupon']['id']]); query('INSERT INTO coupon_usage(coupon_id,order_id,identity_hash) VALUES(?,?,?)',[$totals['coupon']['id'],$oid,hash('sha256','user:'.user()['id'])]); }
            (new CashOnDelivery())->create($oid,$totals['total']);
            queue_mail($email,'WIKI order '.$number.' confirmed','Thank you for your order. Total: '.money($totals['total']).'. Track your order with this private link: '.cfg('url').'/track?number='.$number.'&token='.$token);
            audit('order.created',['order'=>$number]); return $number;
        });
        $_SESSION['guest_orders'][$number]=$token; $_SESSION['cart']=[]; unset($_SESSION['coupon']); return $number;
    }
    public static function order(string $number): array {
        $o=query('SELECT * FROM orders WHERE number=?',[$number])->fetch();
        $token=$_SESSION['guest_orders'][$number]??'';
        if(!$o || !((user() && (int)$o['user_id']===(int)user()['id']) || ($token && hash_equals($o['guest_token_hash'],hash('sha256',$token))))) throw new DomainException('Order not found. Use your private tracking link or sign in.');
        return $o;
    }
    public static function transition(int $id,string $next): void {
        $map=['pending'=>['confirmed','cancelled'],'confirmed'=>['processing','cancelled'],'processing'=>['shipped','cancelled'],'shipped'=>['out_for_delivery'],'out_for_delivery'=>['delivered'],'delivered'=>['returned'],'returned'=>['refunded'],'cancelled'=>[],'refunded'=>[]];
        transaction(function() use($id,$next,$map) {
            $o=query('SELECT * FROM orders WHERE id=? FOR UPDATE',[$id])->fetch();
            if(!$o || !in_array($next,$map[$o['status']]??[],true)) throw new DomainException('This order status transition is not allowed.');
            if($next==='shipped' && !query('SELECT 1 FROM shipments WHERE order_id=?',[$id])->fetchColumn()) throw new DomainException('Save a shipment before marking this order shipped.');
            if($next==='returned' && !query("SELECT 1 FROM returns WHERE order_id=? AND status='received'",[$id])->fetchColumn()) throw new DomainException('Mark the approved return received before restoring its inventory.');
            if($next==='refunded') throw new DomainException('Use Record refund after the actual payment has been returned.');
            if(in_array($next,['cancelled','returned'],true) && !$o['stock_restored']) { foreach(query('SELECT variant_id,quantity FROM order_items WHERE order_id=? ORDER BY variant_id',[$id])->fetchAll() as $i) query('UPDATE inventory SET stock=stock+? WHERE variant_id=?',[$i['quantity'],$i['variant_id']]); query('UPDATE orders SET stock_restored=1 WHERE id=?',[$id]); }
            query('UPDATE orders SET status=? WHERE id=?',[$next,$id]);
            queue_mail($o['email'],'WIKI order update: '.$o['number'],'Your order is now '.str_replace('_',' ',$next).'.'); audit('order.status',['id'=>$id,'status'=>$next]);
        });
    }
}
