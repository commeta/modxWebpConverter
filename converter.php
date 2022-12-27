<?php
/*
 * PHP CRON use request events
 * Copyright 2022 commeta <dcs-spb@ya.ru>
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
// CRON Jobs
define("CRON_SITE_ROOT", preg_match('/\/$/',$_SERVER["DOCUMENT_ROOT"]) ? $_SERVER["DOCUMENT_ROOT"] : $_SERVER["DOCUMENT_ROOT"].DIRECTORY_SEPARATOR);

$GLOBALS['cron_jobs']= [];

$GLOBALS['cron_jobs'][]= [ // CRON Job 1, example
	'name' => 'job1',
	'date' => '31-12-2022', // "day-month-year" execute job on the specified date
	'callback' => CRON_SITE_ROOT . "cron/inc/callback_cron.php",
	'multithreading' => false
];


$GLOBALS['cron_jobs'][]= [ // CRON Job 2, multithreading example
	'name' => 'job2multithreading',
	'interval' => 60 * 60 * 24, // 1 start in 24 hours
	'callback' => CRON_SITE_ROOT . "cron/inc/callback_cron.php",
	'multithreading' => true
];


for( // CRON job 3, multithreading example, four core
	$i= 0;
	$i< 4; // Max processor cores
	$i++	
) {
	$GLOBALS['cron_jobs'][]= [ // CRON Job 3, multithreading example
		'name' => 'multithreading_' . $i,
		'time' => '07:20:00', // "hours:minutes:seconds" execute job on the specified time every day
		'callback' => CRON_SITE_ROOT . "cron/inc/callback_cron.php",
		'multithreading' => true
	];
}
 
 
////////////////////////////////////////////////////////////////////////
// Variables
define("CRON_LOG_FILE", CRON_SITE_ROOT . 'cron/log/cron.log'); // false switched off
define("CRON_DAT_FILE", CRON_SITE_ROOT . 'cron/dat/cron.dat');

define("CRON_DELAY", 0);  // interval between requests in seconds, 0 to max int, increases the accuracy of the job timer hit
define("CRON_LOG_ROTATE_MAX_SIZE", 10 * 1024 * 1024); // 10 in MB
define("CRON_LOG_ROTATE_MAX_FILES", 5);
define("CRON_URL_KEY", 'my_secret_key'); // change this!


////////////////////////////////////////////////////////////////////////
// Debug
/*
@file_put_contents(
	CRON_LOG_FILE, 
	microtime() . " DEBUG: start  microtime:" . 
		print_r([
			$_SERVER['QUERY_STRING'], 
			$_SERVER['SERVER_NAME'], 
			$_SERVER['REQUEST_METHOD'], 
			$_SERVER['REQUEST_URI']
		] , true) . " \n",
	FILE_APPEND | LOCK_EX
);
*/

////////////////////////////////////////////////////////////////////////
// Functions
if(!function_exists('open_cron_socket')) { 
	function open_cron_socket($cron_url_key, $process_id= false){ // Start job in parallel process
		if($process_id !== false) $cron_url_key.= '&process_id=' . $process_id;
		$cron_url= 'https://' . strtolower(@$_SERVER["HTTP_HOST"]) . "/". basename(__FILE__) ."?cron=" . $cron_url_key;

		$wget= false;
		if(strtolower(PHP_OS) == 'linux') {
			foreach(explode(':', getenv('PATH')) as $path){
				if(is_executable($path.'/wget')) {
					$wget= $path.'/wget';
					break 1;
				}
			}
		}
		
		if(
			is_callable("shell_exec") &&
			$wget
		){
			shell_exec($wget . ' -T 1 --delete-after -q "' . $cron_url . '" > /dev/null &');
		} else {
			@fclose( 
				@fopen(
					$cron_url, 
					'r', 
					false, 
					stream_context_create([
						'http'=>[
							'timeout' => 0.04
						]
					])
				)
			);
		}
	}
}



////////////////////////////////////////////////////////////////////////
// main
if(
	isset($_REQUEST["cron"]) &&
	$_REQUEST["cron"] == CRON_URL_KEY
){
	
	////////////////////////////////////////////////////////////////////////
	// Classes: system api
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
				_die();
			}   
		}   
	}
	
	
	// Functions: system api
	function cron_session_add_event($event){
		$GLOBALS['cron_session']['finish']= time();
		$GLOBALS['cron_session']['events'][]= $event;
		write_cron_session();

		if(CRON_LOG_FILE){
			file_put_contents(
				CRON_LOG_FILE,
				implode(' ', $event) . "\n",
				FILE_APPEND | LOCK_EX
			);
		}
	}

	function write_cron_session(){ 
		$serialized= serialize($GLOBALS['cron_session']);

		rewind($GLOBALS['cron_resource']);
		fwrite($GLOBALS['cron_resource'], $serialized);
		ftruncate($GLOBALS['cron_resource'], mb_strlen($serialized));
		fflush($GLOBALS['cron_resource']);
	}
	
	function tick_interrupt($s= false){
			if(isset($GLOBALS['cron_dat_file'])){ // update mtime stream descriptor file
				touch($GLOBALS['cron_dat_file']);
				return true;
			}
			
			/*
			if(isset($GLOBALS['cron_resource'])){ // debug, auto save system variables
				write_cron_session();
				return true;
			}
			*/
	}

	function _die($return= ''){
		tick_interrupt('_die');
		$GLOBALS['cron_limit_exception']->disable();
		die($return);
	}

	function fcgi_finish_request(){
		// check if fastcgi_finish_request is callable
		if(is_callable('fastcgi_finish_request')) {
			session_write_close();
			fastcgi_finish_request();
		}

		while(ob_get_level()) ob_end_clean();
		
		ob_start();
		
		header(filter_input(INPUT_SERVER, 'SERVER_PROTOCOL', FILTER_SANITIZE_STRING).' 200 OK');
		header('Content-Encoding: none');
		header('Content-Length: '.ob_get_length());
		header('Connection: close');
		http_response_code(200);

		@ob_end_flush();
		@ob_flush();
		@flush();
	}


	function init_background_cron(){
		ignore_user_abort(true);
		fcgi_finish_request();

		if (is_callable('proc_nice')) {
			proc_nice(15);
		}

		set_time_limit(600);
		ini_set('MAX_EXECUTION_TIME', 600);
		
		ini_set('error_reporting', E_ALL);
		ini_set('display_errors', 1); // 1 to debug
		ini_set('display_startup_errors', 1);
		
		$GLOBALS['cron_limit_exception']= new time_limit_exception;
		$GLOBALS['cron_limit_exception']->enable();
		
		if(is_callable('register_tick_function')) {
			declare(ticks=1);
			register_tick_function('tick_interrupt', 'register_tick_function');
		}
	}

	function cron_log_rotate($cron_log_rotate_max_size, $cron_log_rotate_max_files){ // LOG Rotate
		if(CRON_LOG_FILE && filesize(CRON_LOG_FILE) >  $cron_log_rotate_max_size / $cron_log_rotate_max_files) {
			rename(CRON_LOG_FILE, CRON_LOG_FILE . "." . time());
			file_put_contents(
				CRON_LOG_FILE, 
				date('m/d/Y H:i:s',time()) . " INFO: log rotate\n", 
				FILE_APPEND | LOCK_EX
			);
				
			$the_oldest = time();
			$log_old_file = '';
			$log_files_size = 0;
						
			foreach(glob(CRON_LOG_FILE . '*') as $file_log_rotate){
				$log_files_size+= filesize($file_log_rotate);
				if ($file_log_rotate == CRON_LOG_FILE) {
					continue;
				}
					
				$log_mtime = filectime($file_log_rotate);
				if ($log_mtime < $the_oldest) {
					$log_old_file = $file_log_rotate;
					$the_oldest = $log_mtime;
				}
			}

			if ($log_files_size >  $cron_log_rotate_max_size) {
				if (file_exists($log_old_file)) {
					unlink($log_old_file);
					file_put_contents(
						CRON_LOG_FILE, 
						date('m/d/Y H:i:s', time()) . "INFO: log removal\n",
						FILE_APPEND | LOCK_EX
					);
				}
			}
		}
	}

	//function queue_manager(){}
	
	function multithreading_dispatcher(){
		// Dispatcher init
		$dat_file= dirname(CRON_DAT_FILE) . DIRECTORY_SEPARATOR . $_GET["process_id"] . '.dat';
		
		
		// Check interval
		foreach($GLOBALS['cron_jobs'] as $job) {
			if($job['name'] == $_GET["process_id"] && $job['multithreading']) {
				if(!isset($job['interval']) || isset($job['date']) ||  isset($job['time'])) $interval= 0;
				else $interval= $job['interval'];
				
				if(filemtime($dat_file) + $interval > time()) _die();
			}
		}
		
		touch($dat_file);
		$GLOBALS['cron_resource']= fopen($dat_file, "r+");
		
		
		if(flock($GLOBALS['cron_resource'], LOCK_EX | LOCK_NB)) {
			$GLOBALS['cron_dat_file']= $dat_file;
			
			$cs=unserialize(@fread($GLOBALS['cron_resource'], filesize($dat_file)));
				
			if(is_array($cs) ){
				$GLOBALS['cron_session']= $cs;
			} else {
				$GLOBALS['cron_session']= [
					'finish'=> 0
				];
			}
			
			$GLOBALS['cron_session']['events']= [];


			foreach($GLOBALS['cron_jobs'] as $job) {
				if($job['name'] == $_GET["process_id"] && $job['multithreading']) {
					// include connector
					if(file_exists($job['callback'])) {
						include $job['callback'];

						cron_session_add_event([
							'date'=> date('m/d/Y H:i:s', time()),
							'message'=> 'INFO:',
							'name' => $job['name'],
							'callback' => $job['callback'],
							'mode' => 'multithreading'
						]);
					} else {
						cron_session_add_event([
							'date'=> date('m/d/Y H:i:s', time()),
							'message'=> 'ERROR:',
							'name' => $job['name'],
							'callback' => $job['callback'],
							'mode' => 'multithreading'
						]);
					}
					
					break;
				}
			}
			
			$GLOBALS['cron_session']['finish']= time();
			write_cron_session();

			// END Job
			flock($GLOBALS['cron_resource'], LOCK_UN);
		}

		fclose($GLOBALS['cron_resource']);
		unset($GLOBALS['cron_resource']);
		
		_die();
	}


	function main_job_dispatcher(){
		foreach($GLOBALS['cron_jobs'] as $job){
			$dat_file= dirname(CRON_DAT_FILE) . DIRECTORY_SEPARATOR . $job['name'] . '.dat';
			
			
			if(!isset($GLOBALS['cron_session'][$job['name']]['last_update'])) { // init
				$GLOBALS['cron_session'][$job['name']]['last_update']= 0;
			}


			if(!isset($GLOBALS['cron_session'][$job['name']]['complete'])){
				$GLOBALS['cron_session'][$job['name']]['complete']= false;
			}
			
			
			if(isset($job['date']) && isset($job['time'])){ // check date time, one - time
					$job['interval']= 0;
					$t= explode(':', $job['time']);
					$d= explode('-', $job['date']);
				
				
				
				
				
					if(
						!$GLOBALS['cron_session'][$job['name']]['complete'] && 
						$job['date'] == date('d-m-Y', time()) && // 23 over check
						(
							intval($t[0]) + 1 == intval(date("H")) ||
							(
								intval($t[0]) == intval(date("H"))  &&
								intval($t[1]) <= intval(date("i")) 
							)
						)
					){
						$GLOBALS['cron_session'][$job['name']]['last_update']= time() - 1;
						if(file_exists($dat_file)) touch($dat_file, time() - 1);
					} else {// lock job forever, dat!
						$GLOBALS['cron_session'][$job['name']]['last_update']= PHP_INT_MAX;
						if(file_exists($dat_file)) touch($dat_file, time() + 60 * 60 * 24);
					}
					
					if(is_array($t) && is_array($d)){
						$time_stamp= mktime(intval($t[0]), intval($t[1]), intval($t[2]), intval($d[1]), intval($d[0]), intval($d[2]));
						
					}
					
					
					
					
					
			} else {
				if(isset($job['date'])){ // check date, one - time
					$job['interval']= 0;
					
					if(
						!$GLOBALS['cron_session'][$job['name']]['complete'] && 
						$job['date'] == date('d-m-Y', time())
					){
						$GLOBALS['cron_session'][$job['name']]['last_update']= time() - 1;
						if(file_exists($dat_file)) touch($dat_file, time() - 1);
					} else {// lock job forever
						$GLOBALS['cron_session'][$job['name']]['last_update']= PHP_INT_MAX;
						if(file_exists($dat_file)) touch($dat_file, time() + 60 * 60 * 24);
					}
				}
				
				if(isset($job['time'])){ // check time, every day
					$t= explode(':', $job['time']);
					$job['interval']= 0;
									
					if( 
						intval($t[0]) + 1 == intval(date("H")) ||
						(
							intval($t[0]) == intval(date("H"))  &&
							intval($t[1]) <= intval(date("i")) 
						)
					){
						if(!$GLOBALS['cron_session'][$job['name']]['complete']){
							$GLOBALS['cron_session'][$job['name']]['last_update']= time() - 1;
						} else {// lock job
							$GLOBALS['cron_session'][$job['name']]['last_update']= PHP_INT_MAX;
							if(file_exists($dat_file)) touch($dat_file, time() + 60 * 60 * 24);
						}
					} else {// lock job
						$GLOBALS['cron_session'][$job['name']]['last_update']= PHP_INT_MAX;
						if(file_exists($dat_file)) touch($dat_file, time() + 60 * 60 * 24);
					}
					
					// unlock job
					if(intval($t[0]) > intval(date("H"))) {
						$GLOBALS['cron_session'][$job['name']]['complete']= false;
						if(file_exists($dat_file)) touch($dat_file, time() - 1);
					}
				}
			}
			
			
			
			
			if($GLOBALS['cron_session'][$job['name']]['last_update'] == PHP_INT_MAX) {
				continue;
			}
			
			
			if($job['multithreading']){ // refresh last update
				if(file_exists($dat_file)){
					$GLOBALS['cron_session'][$job['name']]['last_update']= filemtime($dat_file);
				}
			}
			
			
			// Job timer
			if($GLOBALS['cron_session'][$job['name']]['last_update'] + $job['interval']  < time()){
				$GLOBALS['cron_session'][$job['name']]['complete']= true;

				
				if($job['multithreading']){  // start multithreading example
					open_cron_socket(CRON_URL_KEY, $job['name']); 
				} else {
					// include connector
					if(file_exists($job['callback'])){
						include $job['callback'];
						
						cron_session_add_event([
							'date'=> date('m/d/Y H:i:s', time()),
							'message'=> 'INFO:',
							'name' => $job['name'],
							'callback' => $job['callback'],
							'mode' => 'singlethreading'
						]);
					} else {
						cron_session_add_event([
							'date'=> date('m/d/Y H:i:s', time()),
							'message'=> 'ERROR:',
							'name' => $job['name'],
							'callback' => $job['callback'],
							'mode' => 'singlethreading'
						]);
					}
				}
				
				$GLOBALS['cron_session'][$job['name']]['last_update']= time();
			}
		}
		
	}
	
	////////////////////////////////////////////////////////////////////////
	// start in background
	init_background_cron();
	
	foreach($GLOBALS['cron_jobs'] as $k => $job){ // check job name symbols
		$GLOBALS['cron_jobs'][$k]['name']= mb_eregi_replace("[^a-zA-Z0-9_]", '', $job['name']);
	}



	////////////////////////////////////////////////////////////////////////
	// multithreading 
	if( // job in parallel process. For long tasks, a separate dispatcher is needed
		isset($_GET["process_id"])
	){
		foreach($GLOBALS['cron_jobs'] as $job) {
			if($job['name'] == $_GET["process_id"] && $job['multithreading']) {
				multithreading_dispatcher();
			}
		}
		
		_die();
	}
	////////////////////////////////////////////////////////////////////////
	
	
	
	
	////////////////////////////////////////////////////////////////////////
	// Dispatcher init
	if(@filemtime(CRON_DAT_FILE) + CRON_DELAY > time()) _die();

	touch(CRON_DAT_FILE);
	$GLOBALS['cron_resource']= fopen(CRON_DAT_FILE, "r+");
	
	if(flock($GLOBALS['cron_resource'], LOCK_EX | LOCK_NB)) {
		$GLOBALS['cron_dat_file']= CRON_DAT_FILE;
		$cs= unserialize(@fread($GLOBALS['cron_resource'], filesize(CRON_DAT_FILE)));
		
		if(is_array($cs) ){
			$GLOBALS['cron_session']= $cs;
		} else {
			$GLOBALS['cron_session']= [
				'finish'=> 0
			];
		}

		$GLOBALS['cron_session']['events']= [];
		
		if(CRON_LOG_FILE && !is_dir(dirname(CRON_LOG_FILE))) {
			mkdir(dirname(CRON_LOG_FILE), 0755, true);
		}
		
		
		//###########################################
		// check jobs
		main_job_dispatcher();
		if(CRON_DELAY == 0){
			for($i= 0; $i<= 600; $i++){
				main_job_dispatcher();
				sleep(1);
			}
		}
		
		//###########################################
		cron_log_rotate(CRON_LOG_ROTATE_MAX_SIZE, CRON_LOG_ROTATE_MAX_FILES);
		
		$GLOBALS['cron_session']['finish']= time();
		write_cron_session();
		
		// END Jobs
		flock($GLOBALS['cron_resource'], LOCK_UN);
	}

	fclose($GLOBALS['cron_resource']);
	unset($GLOBALS['cron_resource']);

	_die();
} else {
	
	////////////////////////////////////////////////////////////////////////
	// check time out to start in background 
	if(file_exists(CRON_DAT_FILE)){
		if(filemtime(CRON_DAT_FILE) + CRON_DELAY < time()){
			open_cron_socket(CRON_URL_KEY);
		} 
	} else {
		@mkdir(dirname(CRON_DAT_FILE), 0755, true);
		touch(CRON_DAT_FILE, time() - CRON_DELAY);
		
		if(CRON_LOG_FILE) {
			mkdir(dirname(CRON_LOG_FILE), 0755, true);
			touch(CRON_LOG_FILE);
		}
	}

	unset($GLOBALS['cron_jobs']);
}
?>
