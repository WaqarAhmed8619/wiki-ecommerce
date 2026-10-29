<?php
// Optional deterministic format/size optimization. Originals remain untouched.
if(PHP_SAPI!=='cli') exit;
if(!function_exists('imagewebp')) exit("Enable PHP GD with WebP support.\n");
$directory=dirname(__DIR__).'/public/assets/images/';
foreach(glob($directory.'*.{jpg,png}',GLOB_BRACE) as $file){
    $source=imagecreatefromstring(file_get_contents($file));if(!$source)continue;
    $width=imagesx($source);$height=imagesy($source);$base=substr($file,0,strrpos($file,'.'));
    imagewebp($source,$base.'.webp',82);
    foreach([480,960] as $size){$targetWidth=min($size,$width);$targetHeight=(int)round($height*$targetWidth/$width);$resized=imagecreatetruecolor($targetWidth,$targetHeight);imagecopyresampled($resized,$source,0,0,0,0,$targetWidth,$targetHeight,$width,$height);imagewebp($resized,$base.'-'.$size.'.webp',80);imagedestroy($resized);}
    imagedestroy($source);
}
echo "WebP files and responsive sizes created. Originals preserved.\n";
