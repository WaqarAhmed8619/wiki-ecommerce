<?php
// Run through bin/install.php after schema import. No default administrator password.
function seed(): void {
    if(query('SELECT COUNT(*) FROM products')->fetchColumn()) { echo "Seed skipped: catalog is not empty.\n"; return; }
    transaction(function(){
        query("INSERT INTO categories(id,name,slug,parent_id) VALUES(1,'Clothing','clothing',NULL),(2,'Scents','scents',NULL),(3,'T-Shirts','t-shirts',1),(4,'Hoodies','hoodies',1),(5,'Shirts','shirts',1),(6,'Trousers','trousers',1),(7,'Jackets','jackets',1),(8,'Unisex','unisex',2),(9,'Dresses','dresses',1),(10,'Tops','tops',1),(11,'Men','men',1),(12,'Women','women',1),(13,'Fresh','fresh',2),(14,'Woody','woody',2),(15,'Floral','floral',2),(16,'Oriental','oriental',2),(17,'Citrus','citrus',2)");
        $rows=[
            ['Signature Oversized T-Shirt','signature-oversized-t-shirt','TEE',3,'clothing',450000,null,'tee','A little room. A lot of character.','Cut from substantial 240 GSM cotton with a softly structured drape. Dropped shoulders, a clean neckline and a relaxed silhouette make this your everyday signature.'],
            ['Essential Hoodie','essential-hoodie','HOD',4,'clothing',890000,750000,'hoodie','Comfort, considered.','Brushed heavyweight cotton fleece, a double-layer hood and carefully finished seams. A generous fit that feels equally at home on slow mornings and late city nights.'],
            ['Classic Oxford Shirt','classic-oxford-shirt','OXF',5,'clothing',690000,null,'shirt','The everyday, elevated.','A breathable cotton Oxford with a refined button-down collar. Designed to be worn loose over a tee or tucked into tailored trousers.'],
            ['Urban Cargo Pants','urban-cargo-pants','CRG',6,'clothing',790000,null,'pants','Form meets function.','Durable cotton twill with articulated pockets and a relaxed straight leg. Thoughtful details for life in motion.'],
            ['Premium Jacket','premium-jacket','JKT',7,'clothing',1490000,null,'jacket','Your next forever layer.','A clean-cut outer layer in a richly textured cotton blend. Finished with a soft lining, substantial hardware and understated WIKI detailing.'],
            ['Midnight Oud','midnight-oud','OUD',8,'scents',1250000,null,'oud','Warm. Mysterious. Unforgettable.','A modern oud composition that opens with saffron and settles into resinous woods. Made for evenings that deserve to linger.'],
            ['Azure','azure','AZR',8,'scents',950000,null,'azure','A breath of somewhere new.','Bright bergamot meets sea salt and clean cedar. A fresh, quietly confident scent inspired by a sunlit coastline.'],
            ['Noir','noir','NOR',8,'scents',1100000,990000,'noir','After dark, entirely you.','Black pepper, aromatic lavender and earthy vetiver create a deeply textured fragrance with a smooth amber finish.'],
            ['Velvet Rose','velvet-rose','ROS',8,'scents',1050000,null,'rose','Softness with a presence.','Velvety rose petals meet pink pepper and warm musk. An intimate floral that wears beautifully from morning into evening.'],
            ['Imperial','imperial','IMP',8,'scents',1450000,null,'imperial','Leave a lasting impression.','A luminous opening of citrus gives way to aromatic spices, sandalwood and amber. Distinctive, balanced and elegantly long-lasting.']
        ];
        foreach($rows as $index=>$r) {
            [$name,$slug,$sku,$cat,$type,$price,$sale,$image,$short,$description]=$r;
            $family=['Woody','Fresh','Oriental','Floral','Citrus'][max(0,$index-5)]??null;
            query('INSERT INTO products(category_id,name,slug,sku,type,short_description,description,price,sale_price,cost_price,featured,fragrance_family,top_notes,middle_notes,base_notes,gender,longevity,concentration) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',[$cat,'WIKI '.$name,$slug,'WK-'.$sku,$type,$short,$description,$price,$sale,(int)($price*.4),in_array($index,[0,2,5,6])?1:0,$type==='scents'?$family:null,$type==='scents'?'Bergamot, saffron':null,$type==='scents'?'Rose, aromatic woods':null,$type==='scents'?'Amber, musk, cedar':null,$type==='scents'?'Unisex':null,$type==='scents'?'6–8 hours; varies by skin':null,$type==='scents'?'Eau de Parfum':null]);
            $pid=(int)db()->lastInsertId();
            $imagePath=$type==='scents'?'/assets/images/wiki-'.$image.'.webp':'/assets/images/'.$image.'.webp';
            foreach(['Main view','Detail study','Editorial mood'] as $pos=>$label) query('INSERT INTO product_images(product_id,path,alt,position) VALUES(?,?,?,?)',[$pid,$imagePath,$name.' — '.$label.' (sample photography)',$pos]);
            $variants=$type==='clothing'?['XS','S','M','L','XL','XXL']:['30ml','50ml','100ml'];
            foreach($variants as $n=>$v) foreach($type==='clothing'?['Black','Beige','White']:[''] as $color) {
                query('INSERT INTO product_variants(product_id,sku,size,color,volume,price_adjustment) VALUES(?,?,?,?,?,?)',[$pid,'WK-'.$sku.'-'.$v.($color?'-'.strtoupper(substr($color,0,3)):''),$type==='clothing'?$v:'',$color,$type==='scents'?$v:'',$type==='scents'?$n*200000:0]);
                query('INSERT INTO inventory(variant_id,stock,minimum_stock) VALUES(?,?,5)',[db()->lastInsertId(),$n===0?4:18]);
            }
        }
        query("INSERT INTO coupons(code,type,value,minimum_amount,maximum_discount,starts_at,expires_at,usage_limit,per_user_limit) VALUES('WELCOME10','percentage',10,500000,150000,UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 YEAR),1000,1)");
        query("INSERT INTO banners(title,body,url) VALUES('A new season of self-expression.','Explore thoughtful essentials and scents that stay with you.','/new-arrivals')");
        query("INSERT INTO site_settings(name,value) VALUES('announcement','COMPLIMENTARY DELIVERY ON ORDERS OVER PKR 15,000'),('contact_email','hello@example.com')");
    }); echo "Sample catalog created.\n";
}
