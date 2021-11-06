/*! converter.js | (c) 2021 commeta <dcs-spb@ya.ru> Apache 2.0 License | https://github.com/commeta/modxWebpConverter, https://webdevops.ru/blog/webp-converter-plugin-modx.html */

Ext.onReady(function() {
	"use strict";
		
	const concurent_tasks= 3; // Setup this value equal to the number of server processor cores -1
	const max_count_threads= 4; // Setup this value equal to the number of server processor cores -1

	
	function manual_start(){ // Click in menu link
		if(localStorage.getItem('converter_mode') == "clean"){ // Generate report
			document.getElementById('converter').innerHTML= "Конвертация закончена";
			localStorage.setItem('converter_mode', 'report');
			
			let output= "";
			let keys= [];
			
			for(let i= 0, length= localStorage.length; i < length; i++) {
				let key= localStorage.key(i);
				
				if(key && key.includes('convert_log') ){
					output+= "<li>" + localStorage.getItem(key) + "<hr /></li>";
					keys.push(key);
				}
			}
			
			
			if(output.length){
				for(let i= 0; i < keys.length; i++) {
					localStorage.removeItem(keys[i]);
				}
				
				Ext.MessageBox.minWidth = parseInt(document.documentElement.clientWidth) / 100 * 70;
				Ext.MessageBox.alert('WEBP Конвертер: Журнал','<div style="max-height: 70vh; overflow-y: auto;"><ul>' + output + '<ul></div>');
			} else {
				document.getElementById('converter').innerHTML= "Журнал пуст";
				
				setTimeout(() => document.getElementById('converter').innerHTML= "Конвертация закончена", 3000);
			}
			
			return;
		}
		
		
		let converter_count= localStorage.getItem('converter_count');
		if(converter_count && parseInt(converter_count) > 0) {
			if(window.count_threads <= (max_count_threads - 1)) { // Max threads!
				window.count_threads++;
				files_iterator();
			}
		} else {
			if(localStorage.getItem('converter_mode') != "get"){
				document.getElementById('converter').innerHTML= "Поиск изображений";
				fetch_converter('get');
			}
		}
	}
	
	
	function files_iterator(){// Converting *.jp[e]g and *.png files to /webp/[*/]*.webp
		for(let i= 0, length= localStorage.length; i < length; i++) {
			let key= localStorage.key(i);
			
			if(key && key.includes('convert_img') ){
				localStorage.setItem(window.converter_token, "active");
				
				let file= localStorage.getItem(key);
				localStorage.removeItem(key);
				fetch_converter('convert', file);
				
				let converter_count= parseInt(localStorage.getItem('converter_count'));
				converter_count--;
				localStorage.setItem('converter_count', converter_count);
				
				if(window.count_threads == 1) document.getElementById('converter').innerHTML= converter_count;
				else document.getElementById('converter').innerHTML= window.count_threads + 'x ' + converter_count;
				
				return;
			}
		}
		
		document.getElementById('converter').innerHTML= "Просмотр журнала";
		localStorage.setItem(window.converter_token, "stopped");
		localStorage.setItem('converter_count', 0);
		
		if(localStorage.getItem('converter_mode') != "clean"){
			fetch_converter('clean'); // Clean deleted copy of files into /webp/ directory
			window.count_threads= 0;
		}
		
		let converter_error= localStorage.getItem('converter_error');
		if( converter_error ) document.getElementById('converter').innerHTML= converter_error;
	}
	
	function error_catcher(){ // Catch error
		let converter_mode= localStorage.getItem('converter_mode');
		if(converter_mode) {
			if(converter_mode == "get"){
				document.getElementById('converter').innerHTML= "Ошибка, попробуйте позже";
				localStorage.setItem('converter_mode', 'error');
			}
			if(converter_mode == "convert"){
				files_iterator();
			}
			if(converter_mode == "clean"){
				document.getElementById('converter').innerHTML= "Просмотр журнала";
			}
		}
	}
	
	function fetch_converter(mode, file= false){
		let upload = {
			"mode": mode,
			"file": file,
			"cwebp": file ? localStorage.getItem("converter_cwebp") : false
		};

		let data= new FormData();
		data.append("json", JSON.stringify(upload));
		
		localStorage.setItem('converter_mode', mode);
		
		fetch("/connectors/converter/converter.php", {
			method: "POST",
			body: data
		}).then(response => {
			if(response.status !== 200) {
				return Promise.reject();
			}
			
			return response.json();
		}).then(function(data) {
			if(typeof( data.status ) != "undefined" && data.status == "complete"){
				if(data.mode == 'get'){// Get *.jp[e]g and *.png files list, for queue to converting
					localStorage.setItem('converter', Date.now());
					localStorage.setItem('converter_count', data.count);
					localStorage.setItem('converter_cwebp', data.cwebp);
					
					if(typeof( data.execution_time ) != "undefined" && data.execution_time == "exceeded"){
						localStorage.setItem('converter_error', 'Ошибка: слишком много файлов');
					}
					
					data.images.forEach(function(file, index, created) {
						localStorage.setItem('convert_img_'+index, file);
					});
						
					window.count_threads++;
					files_iterator();
				}

				if(data.mode == 'convert'){
					if(typeof( data.output ) != "undefined" && data.output){
						let output=  "<b>Source:</b> " + data.source + "<br /><br />";
						output+= "<b>Destination:</b> " + data.dest;
						
						if(data.output.length) {
							output+= "<br /><br />";
							
							data.output.forEach(function(line, index) {
								output+= "<span style='color:red'>" + line + "</span><br />";
							});
						}
						
						
						let itemName= '';
						do { // Add log message
							itemName= 'convert_log_' + rand();
						}	while(localStorage.getItem(itemName) !== null);
						
						localStorage.setItem(itemName, output);
					}
					
					files_iterator();
				}
				
    			if(data.mode == 'flg'){
        			document.getElementById('converter').innerHTML= "Поиск изображений";
        			fetch_converter('get');
    			}
				
			} else {
				if(typeof( data.status ) != "undefined") {
					document.getElementById('converter').innerHTML= data.status;
					localStorage.setItem('converter_mode', data.status);
					
					if(data.mode == 'get_bin' && typeof( data.output ) != "undefined"){
						let output= "";
						
						data.output.forEach(function(line, index, created) {
							output+= "<li>" + line + "</li>";
						});
						
						Ext.MessageBox.minWidth = parseInt(document.documentElement.clientWidth) / 100 * 70;
						Ext.MessageBox.alert(data.status,'<div style="max-height: 70vh; overflow-y: auto;"><ul>' + output + '<ul></div>');
					}
				} else {
					error_catcher();
				}
			}
		}).catch(() => error_catcher());
	}
	
	
	const rand= function() {
		return Math.random().toString(12).substr(2); // remove `0.`
	};

	let token= function() {
		return "converter_token_" + rand() + rand(); // to make it longer
	};

	
	// Init
	let modxUserMenu= document.getElementById('modx-user-menu');
	let a= document.createElement("a");
	let textUserMenu= document.createTextNode( "WEBP Конвертер" );
	a.setAttribute("id", "converter");
	a.setAttribute("title", "Очередь изображений для webp конвертера");
	a.onclick= manual_start;
	a.appendChild( textUserMenu );
	
	let webpConverterLI= document.createElement("li");
	webpConverterLI.appendChild(a);
	modxUserMenu.insertBefore(webpConverterLI, modxUserMenu.firstChild);

	window.count_threads= 0;
	let count_parallel_tabs= 0;
	
	// Detect concurent tasks
	if(localStorage.length > 0) {
		for(let i= 0, length= localStorage.length; i < length; i++) {
			let key= localStorage.key(i);
			
			if(key && key.includes('converter_token') ){
				let converter_token= localStorage.getItem(key);
				if(converter_token == "active"){
					count_parallel_tabs++;
				}
			}
		}
	}
	
	window.converter_token= token(); // generate token
	localStorage.setItem(window.converter_token, "passive");
	
	window.addEventListener("unload", function() { // Delete token on unload
		localStorage.removeItem(window.converter_token);
	});


	// Check reload
	let converter= localStorage.getItem('converter');
	let converter_count= localStorage.getItem('converter_count');
	
	if(converter && converter_count) {
		converter_count= parseInt(converter_count);
		
		// Autostart max concurent task
		if(converter_count > 0 && count_parallel_tabs < concurent_tasks) {
			window.count_threads++;
			files_iterator();
		}
		
		// Autostart after clean cache, === -1 is Off, === 0 is On
		if(converter_count === 0){
		    fetch_converter('flg');
		}
	}
});
