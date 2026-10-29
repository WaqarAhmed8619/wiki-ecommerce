<?php
if(PHP_SAPI!=='cli') exit;
require dirname(__DIR__).'/app/bootstrap.php';
foreach(['midnight-oud'=>'oud','azure'=>'azure','noir'=>'noir','velvet-rose'=>'rose','imperial'=>'imperial'] as $slug=>$image) {
    $file='/assets/images/wiki-'.$image.'.webp';
    if(is_file(ROOT.'/public'.$file)) query('UPDATE product_images im JOIN products p ON p.id=im.product_id SET im.path=? WHERE p.slug=? AND im.path LIKE ?',[$file,$slug,'/assets/images/%']);
}
echo "Sample fragrance images updated.\n";
