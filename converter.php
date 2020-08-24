<?php
/*
 * Server api for converting files
 * https://github.com/commeta/modxWebpConverter
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

// Decrement max execution time, keep correct server or proxy connection timeout - equal php execution time!
$max_time= ini_get('max_execution_time');
$max_time--;
set_time_limit($max_time);
ini_set('MAX_EXECUTION_TIME', $max_time);

header('Content-type: application/json');
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

$time_limit_exception= new time_limit_exception;


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

	_die(json_encode([
		'status'=> 'complete', 
		'mode'=> 'get', 
		'images'=> $images,
		'count'=> count($images),
		'cwebp'=> $cwebp
	]));
}



if($json['mode'] == 'convert'){ // Converting *.jp[e]g and *.png files to /webp/[*/]*.webp
	if( isset($json['cwebp']) && file_exists(__DIR__.DIRECTORY_SEPARATOR.'Binaries'.DIRECTORY_SEPARATOR.$json['cwebp']) ){
		$cwebp= __DIR__.DIRECTORY_SEPARATOR.'Binaries'.DIRECTORY_SEPARATOR.$json['cwebp'];
	} else {
		_die(json_encode(['status'=> 'Wrong Bin file!']));
	}
		
	$dest= BASE_PATH.DIRECTORY_SEPARATOR.'webp'.$json['file'].'.webp';
	$source= BASE_PATH.$json['file'];
	$ext= strtolower(pathinfo($json['file'], PATHINFO_EXTENSION));

	if(!is_dir(dirname($dest))){
		mkdir(dirname($dest), 0755, true);
	}

	if(file_exists($source)){
		if( file_exists($dest) ){
			if(filemtime($dest) > filemtime($source)) goto die_convert;
		}
		
		ignore_user_abort(true);
		
		if($ext == 'jpg' || $ext == 'jpeg'){
			$param= "-metadata none -quiet -pass 10 -m 6 -mt -q 70 -low_memory";
			exec($cwebp.' '.$param.' "'.$source.'" -o "'.$dest.'"',	$output, $return_var);
		}
		
		if($ext == 'png'){
			$param= "-metadata none -quiet -pass 10 -m 6 -alpha_q 85 -mt -alpha_filter best -alpha_method 1 -q 70 -low_memory";
			exec($cwebp.' '.$param.' "'.$source.'" -o "'.$dest.'"', $output, $return_var);
		}
	} else {
		$return_var= 127;
	}
	

die_convert:

	_die(json_encode([
		'status'=> 'complete', 
		'mode'=> 'convert',
		'return_var'=> $return_var
	]));
}



////////////////////////////////////////////////////////////////////////
// Functions

function getBinary(){ // Detect os and select converter command line tool
	$disablefunc= array(); // Check disabled exec function
	$disablefunc= explode(",", str_replace(" ", "", @ini_get("disable_functions")));
	if(!is_callable("exec") || in_array("exec", $disablefunc)) _die(json_encode(['status'=> 'Exec function disabled!']));	
	
	// https://github.com/rosell-dk/webp-convert
	// https://developers.google.com/speed/webp/docs/precompiled
	$cwebp_path= __DIR__.DIRECTORY_SEPARATOR.'Binaries'.DIRECTORY_SEPARATOR;

	$suppliedBinaries = [
		'winnt' => 'cwebp-110-windows-x64.exe',
		'darwin' => 'cwebp-110-mac-10_15',
		'sunos' => 'cwebp-060-solaris',
		'freebsd' => 'cwebp-060-fbsd',
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
	
	if( !isset($suppliedBinaries[strtolower(PHP_OS)]) ) _die(json_encode(['status'=> 'Bin file not found!']));
	$bin= $suppliedBinaries[strtolower(PHP_OS)]; // Select OS
	$return_var= 'File for: '.PHP_OS.' not work!';
	
	if( is_array($bin) ){ // Check binary
		foreach($bin as $b){
			if( file_exists($cwebp_path.$b) ){
				if( !is_executable($cwebp_path.$b) ) chmod($cwebp_path.$b, 0755);
				
				exec($cwebp_path.$b, $output, $return_var);
				if( $return_var == 0){
					$cwebp= $b;
					break;
				}
			}
		}
	} else {
		if( file_exists($cwebp_path.$bin) ){
			if( strtolower(PHP_OS) != 'winnt' && !is_executable($cwebp_path.$bin) ) chmod($cwebp_path.$bin, 0755);
			
			exec($cwebp_path.$bin, $output, $return_var);
			if($return_var == 0) $cwebp= $bin;
		}
	}
	
	if( !isset($cwebp) ) {
		if(is_numeric($return_var)) _die(json_encode(['status'=> 'Bin file not work! return code:'.$return_var]));
		else _die(json_encode(['status'=> $return_var]));
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
			strpos($full_path, BASE_PATH.DIRECTORY_SEPARATOR.'manager') !== false ||
			strpos($full_path, BASE_PATH.DIRECTORY_SEPARATOR.'webp') !== false
		){
			continue;
		}
		
		
		if(is_dir($full_path)){
			recursive_search_img($full_path);
		} else {
			if(
				strripos($file, '.jpg', -4) !== false ||
				strripos($file, '.jpeg', -5) !== false ||
				strripos($file, '.png', -4) !== false
			){
				$img= str_replace(BASE_PATH, '', $full_path);
				$dest= BASE_PATH.DIRECTORY_SEPARATOR.'webp'.DIRECTORY_SEPARATOR.$img.'.webp';
				
				if( file_exists($dest) && filemtime($full_path) < filemtime($dest) ) continue;
				
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
			if(strripos($file, '.webp', -5) !== false ){
				$dest= BASE_PATH.str_replace(
					[BASE_PATH.DIRECTORY_SEPARATOR.'webp', '.webp'], '', $full_path
				);
				
				if( !file_exists($dest) ) {
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
					_die(json_encode([
						'status'=> 'complete', 
						'mode'=> 'get', 
						'images'=> $images,
						'count'=> count($images),
						'cwebp'=> $cwebp,
						'execution_time' => 'exceeded'
					]));
				}
			}
		}   
	}   
}

?>
