<?php
/*
 * Server api for converting files
 * 
 * Copyright 2020 commeta <dcs-spb@ya.ru>
 * 
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
 * MA 02110-1301, USA.
 * 
 * 
 */


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
	if( !is_dir(BASE_PATH.DIRECTORY_SEPARATOR.'webp') ) goto die_clean;

	recursive_search_webp(BASE_PATH.DIRECTORY_SEPARATOR.'webp'); // Remove deleted copy webp files recursive
	recursive_remove_empty_dirs(BASE_PATH.DIRECTORY_SEPARATOR.'webp'); // Remove empty dirs

	$options = array(xPDO::OPT_CACHE_KEY=>'webp_on_page'); // Clear webp modx cache
	$modx->cacheManager->clean($options);
		
die_clean:
	
	die(json_encode([
		'status'=> 'complete', 
		'mode'=> 'clean'
	]));
}



if($json['mode'] == 'get'){ // Get *.jp[e]g and *.png files list, for queue to converting
	$images= [];
	recursive_search_img(BASE_PATH, $images);

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
			exec(
				$cwebp.' -metadata none -quiet -pass 10 -m 6 -mt -q 70 -low_memory "'.$source.'" -o "'.$dest.'"',
				$output,
				$return_var
			);
		}
		
		if( $ext == 'png' ){
			exec(
				$cwebp.' -metadata none -quiet -pass 10 -m 6 -alpha_q 85 -mt -alpha_filter best -alpha_method 1 -q 70 -low_memory "'.$source.'" -o "'.$dest.'"',
				$output,
				$return_var
			);
		}
	} else {
		$return_var= -1;
	}
	

die_convert:

	die(json_encode([
		'status'=> 'complete', 
		'mode'=> 'convert',
		'return_var'=> $return_var
	]));
}



////////////////////////////////////////////////////////////////////////
// Functions


function getBinary(){ // Detect os and select converter command line tool
	// https://github.com/rosell-dk/webp-convert
	// https://developers.google.com/speed/webp/docs/precompiled
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
			'cwebp-061-linux-x86-64'
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



function recursive_search_img($dir, &$images){ // Search jpeg and png files recursive
	$odir = opendir($dir);
 
	while(($file = readdir($odir)) !== FALSE){
		if(
			$file == '.' || 
			$file == '..' || 
			stripos($dir.DIRECTORY_SEPARATOR.$file, BASE_PATH.DIRECTORY_SEPARATOR.'manager') !== false || 
			stripos($dir.DIRECTORY_SEPARATOR.$file, BASE_PATH.DIRECTORY_SEPARATOR.'webp') !== false
		){
			continue;
		}
		
		if(is_dir($dir.DIRECTORY_SEPARATOR.$file)){
			recursive_search_img($dir.DIRECTORY_SEPARATOR.$file, $images);
		} else {
			if(
				strripos($file, '.jpg', -4) !== false ||
				strripos($file, '.jpeg', -5) !== false ||
				strripos($file, '.png', -4) !== false
			){
				$img= str_replace(BASE_PATH, '', $dir.DIRECTORY_SEPARATOR.$file);
				$dest= BASE_PATH.DIRECTORY_SEPARATOR.'webp'.DIRECTORY_SEPARATOR.$img.'.webp';
				
				if( file_exists($dest) && filemtime($dir.DIRECTORY_SEPARATOR.$file) < filemtime($dest) ) continue;
				$images[]= $img;
			}
		}
	}
	closedir($odir);
}



function recursive_search_webp($dir){ // Search webp files recursive
	$odir = opendir($dir);
 
	while(($file = readdir($odir)) !== FALSE){
		if($file == '.' || $file == '..') continue;
		
		if(is_dir($dir.DIRECTORY_SEPARATOR.$file)){
			recursive_search_webp($dir.DIRECTORY_SEPARATOR.$file);
		} else {
			if( strripos($file, '.webp', -5) !== false ){
				$dest= BASE_PATH.str_replace(
					[BASE_PATH.DIRECTORY_SEPARATOR.'webp', '.webp'], 
					'', 
					$dir.DIRECTORY_SEPARATOR.$file
				);
				
				if( !file_exists($dest) ) {
					unlink($dir.DIRECTORY_SEPARATOR.$file);
				}
			}
		}
	}
	closedir($odir);
}



function recursive_remove_empty_dirs($dir){ // Remove empty dirs
	$odir = opendir($dir);
	$count_files= 0;
	
	while(($file = readdir($odir)) !== FALSE){
		if($file == '.' || $file == '..') continue;
		
		$count_files++;
		
		if(is_dir($dir.DIRECTORY_SEPARATOR.$file)){
			$count_files += recursive_remove_empty_dirs($dir.DIRECTORY_SEPARATOR.$file);
		}
	}
	
	closedir($odir);
	
	if($count_files == 0) {
		rmdir( $dir );
		return -1;
	}
	
	return $count_files;
}

?>
