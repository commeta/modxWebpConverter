<?php
/*
 * Server api for converting files
 * https://github.com/commeta/modxWebpConverter
 * https://webdevops.ru/blog/webp-converter-plugin-modx.html
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

$param_jpeg= "-metadata none -quiet -pass 10 -m 6 -mt -q 70 -low_memory";
$param_png= "-metadata none -quiet -pass 10 -m 6 -alpha_q 85 -mt -alpha_filter best -alpha_method 1 -q 70 -low_memory";

$suppliedBinaries= [
	'winnt' => 'cwebp-110-windows-x64.exe', // Microsoft Windows 64bit
	'darwin' => 'cwebp-110-mac-10_15', // MacOSX
	'sunos' => 'cwebp-060-solaris', // Solaris
	'freebsd' => 'cwebp-060-fbsd', // FreeBSD
	'linux' => [
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


$BASE_PATH= dirname(dirname(__DIR__));
define('BASE_PATH', $BASE_PATH);


// Decrement max execution time, keep correct server or proxy connection timeout - equal php execution time!
$max_time= ini_get('max_execution_time');
$max_time--;
set_time_limit($max_time);
ini_set('MAX_EXECUTION_TIME', $max_time);

header('Content-type: application/json');
$time_limit_exception= new time_limit_exception;

if(!isset($_POST['json'])) _die(json_encode([]));
$json= json_decode($_POST['json'], true);


require_once(BASE_PATH.DIRECTORY_SEPARATOR.'config.core.php');
require_once(MODX_CORE_PATH.'model'.DIRECTORY_SEPARATOR.'modx'.DIRECTORY_SEPARATOR.'modx.class.php');

$modx= new modX();
if((!$modx) || (!$modx instanceof modX)) {
    _die(json_encode(['status'=> 'Could not create MODX class']));
}

$modx->initialize('mgr');
//$modx->getService('error', 'error.modError', '', '');

if(!$modx->user->hasSessionContext('mgr')) { // Check authorization
    _die(json_encode(['status'=> 'Unauthorized']));
}


////////////////////////////////////////////////////////////////////////
// Json API

if($json['mode'] == 'clean'){ // Clean deleted copy of files into /webp/ directory
	if( !is_dir(BASE_PATH.DIRECTORY_SEPARATOR.'webp') ) goto die_clean;

	ignore_user_abort(true);
	set_time_limit(300);
	ini_set('MAX_EXECUTION_TIME', 300);
	recursive_search_webp(BASE_PATH.DIRECTORY_SEPARATOR.'webp'); // Remove deleted copy webp files recursive
	
	$options = array(xPDO::OPT_CACHE_KEY=>'webp_on_page'); // Clear webp modx cache
	$modx->cacheManager->clean($options);
	
	recursive_remove_empty_dirs(BASE_PATH.DIRECTORY_SEPARATOR.'webp'); // Remove empty dirs

die_clean:
	
	_die(json_encode([
		'status'=> 'complete', 
		'mode'=> 'clean'
	]));
}



if($json['mode'] == 'get'){ // Get *.jp[e]g and *.png files list, for queue to converting
	$images= [];
	$cwebp= getBinary();
	
	$time_limit_exception->enable();
	recursive_search_img(BASE_PATH);
		
	$ret= json_encode([
		'status'=> 'complete', 
		'mode'=> 'get', 
		'images'=> $images,
		'count'=> count($images),
		'cwebp'=> $cwebp
	]);


	switch (json_last_error()) {
		case JSON_ERROR_NONE:
			_die($ret);
		case JSON_ERROR_DEPTH:
			_die(json_encode(['status'=> 'JSON_ERROR_DEPTH']));
		case JSON_ERROR_STATE_MISMATCH:
			_die(json_encode(['status'=> 'JSON_ERROR_STATE_MISMATCH']));
		case JSON_ERROR_CTRL_CHAR:
			_die(json_encode(['status'=> 'JSON_ERROR_CTRL_CHAR']));
		case JSON_ERROR_SYNTAX:
			_die(json_encode(['status'=> 'JSON_ERROR_SYNTAX']));
		case JSON_ERROR_UTF8:
			_die(json_encode(['status'=> 'JSON_ERROR_UTF8']));
		default:
			_die(json_encode(['status'=> 'JSON_ERROR']));
	}
}



if($json['mode'] == 'convert'){ // Converting *.jp[e]g and *.png files to /webp/[*/]*.webp
	if( isset($json['cwebp']) && is_file(__DIR__.DIRECTORY_SEPARATOR.'Binaries'.DIRECTORY_SEPARATOR.$json['cwebp']) ){
		$cwebp= __DIR__.DIRECTORY_SEPARATOR.'Binaries'.DIRECTORY_SEPARATOR.$json['cwebp'];
	} else {
		_die(json_encode(['status'=> 'Wrong Bin file!']));
	}
		
	$dest= BASE_PATH.DIRECTORY_SEPARATOR.'webp'.$json['file'].'.webp';
	$source= BASE_PATH.$json['file'];
	$imagetype= exif_imagetype($source);
	$gd_support= check_gd();
	$return_var= 255;
	$output= [];
	
	
	if(!is_dir(dirname($dest))){
		mkdir(dirname($dest), 0755, true);
	}

	if(is_file($source)){
		if( is_file($dest) ){
			if(filemtime($dest) > filemtime($source)) {
				$output[]= "Info, destination file already converted.";
				goto die_convert;
			}
		}
		
		ignore_user_abort(true);
		
		if($imagetype == IMAGETYPE_JPEG){
			exec($cwebp.' '.$param_jpeg.' "'.$source.'" -o "'.$dest.'" 2>&1', $output, $return_var);
			
			if( // Patch if error: Unsupported color conversion request, for YCCK JPGs
				!is_file($dest) &&
				$return_var != 0 && 
				$gd_support !== false && 
				$gd_support['WebP Support'] == 1 && 
				$gd_support['JPEG Support'] == 1
			){ 
				$return_var= gdConvert($source, $dest);
			}
		}
		
		if($imagetype == IMAGETYPE_PNG){
			exec($cwebp.' '.$param_png.' "'.$source.'" -o "'.$dest.'" 2>&1', $output, $return_var);
			
			if( // Patch if error:
				!is_file($dest) &&
				$return_var != 0 && 
				$gd_support !== false && 
				$gd_support['WebP Support'] == 1 && 
				$gd_support['WebP Alpha Channel Support'] == 1 && 
				$gd_support['PNG Support'] == 1
			){ 
				$return_var= gdConvert($source, $dest);
			}
		}
	} else {
		$return_var= 127;
		$output[]= "Fatal error, source file not found !!!";
	}
	
	if(!is_file($dest)){		
		$output[]= "Fatal error, destination file not created !!!";
	}
	
	if($imagetype != IMAGETYPE_JPEG && $imagetype != IMAGETYPE_PNG){
		$output[]= "Fatal error, not supported source file format !!!";
	}
	

die_convert:

	$ret= json_encode([
		'status'=> 'complete', 
		'mode'=> 'convert',
		'source'=>  $json['file'],
		'dest'=>  DIRECTORY_SEPARATOR.'webp'.$json['file'].'.webp',
		'output'=>  $output,
		'return_var'=> $return_var
	]);

	_die($ret);
}



////////////////////////////////////////////////////////////////////////
// Functions

function gdConvert($source, $dest){
	global $output;

	switch(exif_imagetype($source)){
		case IMAGETYPE_JPEG:
			$img= imagecreatefromjpeg($source);
		break;
		case IMAGETYPE_PNG:
			$img= imagecreatefrompng($source);
		break;
		default:
			return false;
	}

	$return_var= imageWebp($img, $dest, 80);
	imagedestroy($img);
				
	if($return_var && filesize($dest) % 2 == 1) { // No null byte at the end of the file
		file_put_contents($dest, "\0", FILE_APPEND);
	}
				
	if($return_var){
		$return_var= 0;
		$output[]= "Use PHP GD for convert image !";
	}
	
	return $return_var;
}



function getBinary(){ // Detect os and select converter command line tool
	global $suppliedBinaries;
	
	$disablefunc= array(); // Check disabled exec function
	$disablefunc= explode(",", str_replace(" ", "", @ini_get("disable_functions")));
	if(!is_callable("exec") || in_array("exec", $disablefunc)) {
		_die(json_encode(['status'=> 'Exec function disabled!']));	
	}
	
	// https://github.com/rosell-dk/webp-convert
	// https://developers.google.com/speed/webp/docs/precompiled
	$cwebp_path= __DIR__.DIRECTORY_SEPARATOR.'Binaries'.DIRECTORY_SEPARATOR;

	$return_var= 'Bin file for: '.PHP_OS.' not found in /connectors/converter/Binaries/';
	$output= [];

	if( !isset($suppliedBinaries[strtolower(PHP_OS)]) ) _die(json_encode(['status'=> $return_var]));
	$bin= $suppliedBinaries[strtolower(PHP_OS)]; // Select OS
	
	if( is_array($bin) ){ // Check binary
		foreach($bin as $b){
			if( is_file($cwebp_path.$b) ){
				if( !is_executable($cwebp_path.$b) ) chmod($cwebp_path.$b, 0755);
				
				$output[]= $cwebp_path.$b;
				exec($cwebp_path.$b.' 2>&1', $output, $return_var);
				if( $return_var == 0){
					$cwebp= $b;
					break;
				}
			}
		}
	} else {
		if( is_file($cwebp_path.$bin) ){
			if( strtolower(PHP_OS) != 'winnt' && !is_executable($cwebp_path.$bin) ) chmod($cwebp_path.$bin, 0755);
			
			$output[]= $cwebp_path.$bin;
			exec($cwebp_path.$bin.' 2>&1', $output, $return_var);
			if($return_var == 0) {
				$cwebp= $bin;
			}
		}
	}
	
	if( !isset($cwebp) ) {
		if(is_numeric($return_var)) {
			_die(
				json_encode(
					[
						'status'=> 'Bin file not work! return code: '.$return_var, 
						'mode'=> 'get_bin', 
						'output'=> $output, 
						'return_var'=> $return_var
					]
				)
			);
		} else {
			_die(
				json_encode(
					[
						'status'=> $return_var, 
						'mode'=> 'get_bin', 
						'output'=> $output, 
						'return_var'=> 127
					]
				)
			);
		}
	}
	// Download bin file from https://developers.google.com/speed/webp/docs/precompiled, into directory /connectors/converter/Binaries/
	
	return $cwebp;
}



function recursive_search_img($dir){ // Search jpeg and png files recursive
	$odir= opendir($dir);
	
	while(($file= readdir($odir)) !== FALSE){
		$full_path= $dir.DIRECTORY_SEPARATOR.$file;
		
		if( // Exclude subdirectories from search
			$file == '.' || $file == '..' || 
			strpos($full_path, BASE_PATH.DIRECTORY_SEPARATOR.'manager'.DIRECTORY_SEPARATOR) !== false ||
			strpos($full_path, BASE_PATH.DIRECTORY_SEPARATOR.'webp'.DIRECTORY_SEPARATOR) !== false
		){
			continue;
		}
		
		
		if(is_dir($full_path)){
			recursive_search_img($full_path);
		} else {
			$ext= strtolower(pathinfo($file, PATHINFO_EXTENSION));
			
			if(
				$ext == 'jpg' ||
				$ext == 'jpeg' ||
				$ext == 'png' 
			){
				$img= str_replace(BASE_PATH, '', $full_path);
				$dest= BASE_PATH.DIRECTORY_SEPARATOR.'webp'.DIRECTORY_SEPARATOR.$img.'.webp';
				
				if( is_file($dest) && filemtime($full_path) < filemtime($dest) ) continue;
				
				global $images;
				$images[]= $img;
			}
		}
	}
	closedir($odir);
}



function recursive_search_webp($dir){ // Search webp files recursive
	$odir= opendir($dir);
 
	while(($file= readdir($odir)) !== FALSE){
		if($file == '.' || $file == '..') continue;
		$full_path= $dir.DIRECTORY_SEPARATOR.$file;
		
		if(is_dir($full_path)){
			recursive_search_webp($full_path);
		} else {
			$ext= strtolower(pathinfo($file, PATHINFO_EXTENSION));
			
			if($ext == 'webp'){
				$dest= BASE_PATH.str_replace(
					[BASE_PATH.DIRECTORY_SEPARATOR.'webp', '.webp'], '', $full_path
				);
				
				if( !is_file($dest) ) {
					unlink($full_path);
				}
			}
		}
	}
	closedir($odir);
}



function recursive_remove_empty_dirs($dir){ // Remove empty dirs
	$odir= opendir($dir);
	$count_files= 0;
	
	while(($file= readdir($odir)) !== FALSE){
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



function _die($return){
	global $time_limit_exception;
	
	$time_limit_exception->disable();
	die($return);
}



function check_gd(){ // Prior to GD library version 2.2.5, WEBP does not have alpha channel support
	if(extension_loaded('gd') && function_exists('gd_info') ){
		$gd= gd_info();

		if(!in_array('GD Version', $gd)) $gd['GD Version']= '0.0.0';

		preg_match('/\\d+\\.\\d+(?:\\.\\d+)?/', $gd['GD Version'], $matches);
		$gd['Ver']= $matches[0];
		
		if(version_compare($gd['Ver'], '2.2.5') >= 0) {
			$gd['WebP Alpha Channel Support']= 1; 
		} else {
			$gd['WebP Alpha Channel Support']= 0;
		}
		
		
		if(!in_array('WebP Support', $gd)) $gd['WebP Support']= 0;
		if(!in_array('JPEG Support', $gd)) $gd['JPEG Support']= 0;
		if(!in_array('PNG Support', $gd)) $gd['PNG Support']= 0;
		
		
		return $gd;
	} else {
		return false;
	}
}



class time_limit_exception { // Exit if time exceed time_limit
	protected $enabled= false;

	public function __construct() {
		register_shutdown_function( array($this, 'onShutdown') );
	}
    
	public function enable() {
		$this->enabled= true;
	}   
    
	public function disable() {
		$this->enabled= false;
	}   
    
	public function onShutdown() { 
		if ($this->enabled) { //Maximum execution time of $time_limit$ second exceeded
			global $json;
						
			if($json['mode'] == 'get'){ //Too many files, use SSD instead HDD, or try again several times hoping for the system cache of the file system 
				// Another solution, use autoconverter.py or image2webp.sh from https://github.com/commeta/autoconverter
				global $images, $cwebp;
				http_response_code(200);
				
				if(isset($images) && isset($cwebp)){
					$ret= json_encode([
						'status'=> 'complete', 
						'mode'=> 'get', 
						'images'=> $images,
						'count'=> count($images),
						'cwebp'=> $cwebp,
						'execution_time' => 'exceeded'
					]);

					if(json_last_error() != JSON_ERROR_NONE) _die(json_encode(['status'=> 'Wrong filenames encoding!']));
					_die($ret);
				}
			}
		}   
	}   
}

?>
