<?php
// Server api for converting files

////////////////////////////////////////////////////////////////////////
// Init
$BASE_PATH= dirname(dirname(__DIR__));
define('BASE_PATH', $BASE_PATH);

ignore_user_abort(true);
set_time_limit(30);

header('Content-type: application/json');
if(!isset($_POST['json'])) die(json_encode([]));
$json= json_decode($_POST['json'], true);


require_once(BASE_PATH.DIRECTORY_SEPARATOR.'config.core.php');
require_once(MODX_CORE_PATH.'model'.DIRECTORY_SEPARATOR.'modx'.DIRECTORY_SEPARATOR.'modx.class.php');

$modx= new modX();
if((!$modx) || (!$modx instanceof modX)) {
    die(json_encode(['status'=> 'Could not create MODX class']));
}

$modx->initialize('mgr');
//$modx->getService('error', 'error.modError', '', '');

if(!$modx->user->hasSessionContext('mgr')) { // Check authorization
    die(json_encode(['status'=> 'Unauthorized']));
}


////////////////////////////////////////////////////////////////////////
// Json API

if($json['mode'] == 'clean'){ // Clean deleted copy of files into /webp/ directory
	$pattern= '';
	for($i=1; $i <= 30; $i++){ // Create pattern for search files in subdirectories
		$pattern.= ','.str_repeat('*'.DIRECTORY_SEPARATOR, $i);
	}

	foreach(glob(BASE_PATH.'{'.$pattern.'}{*.webp}', GLOB_BRACE) as $image){ // Search files recursive
		$dest= BASE_PATH.str_replace([BASE_PATH.DIRECTORY_SEPARATOR.'webp', '.webp'], '', $image);
		if( !file_exists($dest) ) {
			unlink($image);
			if( count(glob(dirname($image).DIRECTORY_SEPARATOR.'*')) == 0 ) rmdir(dirname($image)); // delete empty dirs
		}
	} 
	
	clearCache();
	
	die(json_encode([
		'status'=> 'complete', 
		'mode'=> 'clean'
	]));
}



if($json['mode'] == 'get'){ // Get *.jp[e]g and *.png files list, for queue to converting
	$pattern= '';
	for($i=1; $i <= 30; $i++){ // Create pattern for search files in subdirectories
		$pattern.= ','.str_repeat('*'.DIRECTORY_SEPARATOR, $i);
	}


	$images= [];
	foreach(glob(BASE_PATH.'{'.$pattern.'}{*.jpg,*.jpeg,*.png}', GLOB_BRACE) as $image){ // Search files recursive
		$img= str_replace(BASE_PATH, '', $image);
		if( strpos($img, 'manager'.DIRECTORY_SEPARATOR) !== false || strpos($img, 'webp'.DIRECTORY_SEPARATOR) !== false) continue;

		$dest= BASE_PATH.DIRECTORY_SEPARATOR.'webp'.$img.'.webp';
		if( file_exists($dest) && filemtime($image) < filemtime($dest) ) continue;

		$images[]= $img;
	} 

	die(json_encode([
		'status'=> 'complete', 
		'mode'=> 'get', 
		'images'=> $images,
		'count'=> count($images),
		'cwebp'=> getBinary()
	]));
}


if($json['mode'] == 'convert'){ // Converting *.jp[e]g and *.png files to /webp/[*/]*.webp
	if( isset($json['cwebp']) && file_exists(__DIR__.DIRECTORY_SEPARATOR.'Binaries'.DIRECTORY_SEPARATOR.$json['cwebp']) ){
		$cwebp= __DIR__.DIRECTORY_SEPARATOR.'Binaries'.DIRECTORY_SEPARATOR.$json['cwebp'];
	} else {
		die(json_encode(['status'=> 'Wrong Bin file!']));
	}
	
	$dest= BASE_PATH.DIRECTORY_SEPARATOR.'webp'.$json['file'].'.webp';
	$source= BASE_PATH.$json['file'];
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
			exec( sprintf("%s -metadata none -quiet -pass 10 -m 6 -mt -q 70 -low_memory '%s' -o '%s'", $cwebp, $source, $dest)  );
		}
		
		if( $ext == 'png' ){
			exec( sprintf("%s -metadata none -quiet -pass 10 -m 6 -alpha_q 85 -mt -alpha_filter best -alpha_method 1 -q 70 -low_memory '%s' -o '%s'", $cwebp, $source, $dest)  );
		}
	}
	

die_convert:

	die(json_encode([
		'status'=> 'complete', 
		'mode'=> 'convert'
	]));
}


function clearCache() { // Clear webp cache
	global $modx;

	$options = array(xPDO::OPT_CACHE_KEY=>'webp_on_page');
	$modx->cacheManager->clean($options);
}


function getBinary(){
	$cwebp_path= __DIR__.DIRECTORY_SEPARATOR.'Binaries'.DIRECTORY_SEPARATOR;

	$suppliedBinaries = [
		'WINNT' => 'cwebp-110-windows-x64.exe',
		'Darwin' => 'cwebp-110-mac-10_15',
		'SunOS' => 'cwebp-060-solaris',
		'FreeBSD' => 'cwebp-060-fbsd',
		'Linux' => [
			// Dynamically linked executable.
			// It seems it is slightly faster than the statically linked
			'cwebp-110-linux-x86-64',

			// Statically linked executable
			// It may be that it on some systems works, where the dynamically linked does not (see #196)
			'cwebp-103-linux-x86-64-static',

			// Old executable for systems in case both of the above fails
			'cwebp-061-linux-x86-64',
		]
	];
	
	if( !isset($suppliedBinaries[PHP_OS]) ) die(json_encode(['status'=> 'Bin file not found!']));
	$bin= $suppliedBinaries[PHP_OS]; // Select OS

	
	if( is_array($bin) ){ // Check binary
		foreach($bin as $b){
			if( !is_executable($b) ) chmod($cwebp_path.$b, 0755);
			exec( $cwebp_path.$b, $output, $return_var);
			if( $return_var == 0){
				$cwebp= $cwebp_path.$b;
				break;
			}
		}
	} else {
		if( !is_executable($bin) ) chmod($cwebp_path.$bin, 0755);
		exec($cwebp_path.$bin, $output, $return_var);
		if( $return_var == 0) $cwebp= $cwebp_path.$bin;
	}
	
	if( !isset($cwebp) ) die(json_encode(['status'=> 'Bin file not work!']));
	
	return str_replace($cwebp_path, '', $cwebp);
}
?>
