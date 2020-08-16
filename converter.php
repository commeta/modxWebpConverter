<?php
// Server api for converting files

$modx_base_path= dirname(dirname(__DIR__));
define('MODX_BASE_PATH', $modx_base_path);

ignore_user_abort(true);
set_time_limit(30);

header('Content-type: application/json');
if(!isset($_POST['json'])) die(json_encode([]));
$json= json_decode($_POST['json'], true);



if($json['mode'] == 'clean'){ // Clean deleted copy of files into /webp/ directory
	$pattern= '';
	for($i=1; $i <= 30; $i++){ // Create pattern for search files in subdirectories
		$pattern.= ','.str_repeat('*/', $i);
	}

	foreach(glob(MODX_BASE_PATH.'{'.$pattern.'}{*.webp}', GLOB_BRACE) as $image){ // Search files recursive
		$dest= MODX_BASE_PATH.str_replace([MODX_BASE_PATH.'/webp', '.webp'], '', $image);
		if( !file_exists($dest) ) unlink($image);
	} 

	// delete empty dirs
	system( sprintf("find '%s' -depth -type d -empty -delete", MODX_BASE_PATH.'/webp')  );

	die(json_encode([
		'status'=> 'complete', 
		'mode'=> 'clean'
	]));

}



if($json['mode'] == 'get'){ // Get *.jp[e]g and *.png files list, for queue to converting
	$pattern= '';
	for($i=1; $i <= 30; $i++){ // Create pattern for search files in subdirectories
		$pattern.= ','.str_repeat('*/', $i);
	}


	$images= [];
	foreach(glob(MODX_BASE_PATH.'{'.$pattern.'}{*.jpg,*.jpeg,*.png}', GLOB_BRACE) as $image){ // Search files recursive
		$img= str_replace(MODX_BASE_PATH, '', $image);
		if( strpos($img, 'manager/') !== false || strpos($img, 'webp/') !== false) continue;

		$dest= MODX_BASE_PATH.'/webp'.$img.'.webp';
		if( file_exists($dest) && filemtime($image) < filemtime($dest) ) continue;

		$images[]= $img;
	} 

	die(json_encode([
		'status'=> 'complete', 
		'mode'=> 'get', 
		'images'=> $images,
		'count'=> count($images),
	]));
}


if($json['mode'] == 'convert'){ // Converting *.jp[e]g and *.png files to /webp/[*/]*.webp
	$cwebp= __DIR__.'/Binaries/cwebp-103-linux-x86-64';
	$dest= MODX_BASE_PATH.'/webp'.$json['file'].'.webp';
	$source= MODX_BASE_PATH.$json['file'];
	$ext= strtolower(pathinfo($json['file'], PATHINFO_EXTENSION));

	if(!is_dir(dirname($dest))){
		mkdir(dirname($dest), 0755, true);
	}

	if(file_exists($source)){
		$source_mtime= filemtime($source);
		
		if( file_exists($dest) ){
			$dest_mtime= filemtime($dest);
			if($dest_mtime > $source_mtime) goto die_convert;
		}
		
		if( $ext == 'jpg' || $ext == 'jpeg'){
			system( sprintf("%s -metadata none -quiet -pass 10 -m 6 -mt -q 70 '%s' -o '%s'", $cwebp, $source, $dest)  );
		}
		
		if( $ext == 'png' ){
			system( sprintf("%s -metadata none -quiet -pass 10 -m 6 -alpha_q 85 -mt -alpha_filter best -alpha_method 1 -q 70 '%s' -o '%s'", $cwebp, $source, $dest)  );
		}
	}
	

die_convert:

	die(json_encode([
		'status'=> 'complete', 
		'mode'=> 'convert'
	]));
}
?>
