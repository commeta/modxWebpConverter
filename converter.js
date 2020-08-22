"use strict";

Ext.onReady(function() {
	function manual_start(){
		document.getElementById('converter').innerHTML= "Поиск изображений";
		fetch_converter('get');
	}
	
	function files_iterator(){// Converting *.jp[e]g and *.png files to /webp/[*/]*.webp
		for (var i = 0, length = localStorage.length; i < length; i++) {
			let key= localStorage.key(i);
			
			if( key.includes('convert_img') ){
				let file= localStorage.getItem(key);
				localStorage.removeItem(key);
				fetch_converter('convert', file);
				
				let converter_count= parseInt(localStorage.getItem('converter_count'));
				converter_count--;
				localStorage.setItem('converter_count', converter_count);
				document.getElementById('converter').innerHTML= converter_count;
				
				return;
			}
		}
		
		document.getElementById('converter').innerHTML= "Конвертация закончена";
		
		fetch_converter('clean'); // Clean deleted copy of files into /webp/ directory
		
		let converter_error= localStorage.getItem('converter_error');
		if( converter_error ) document.getElementById('converter').innerHTML= converter_error;
	}
	
	function fetch_converter(mode, file= false){
		let upload = {
			"mode": mode,
			"file": file,
			"cwebp": file ? localStorage.getItem("converter_cwebp") : false
		};

		let data = new FormData();
		data.append("json", JSON.stringify(upload));

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
						localStorage.setItem('convert_img'+index, file);
					});
						
					files_iterator();
				}

				if(data.mode == 'convert'){
					files_iterator();
				}
			} else {
				if(typeof( data.status ) != "undefined") document.getElementById('converter').innerHTML= data.status;
				else document.getElementById('converter').innerHTML= 'converter ошибка!';
			}
		}).catch(() => console.log('converter ошибка!'));
	}
	
	
	// Init
	let modxUserMenu= document.getElementById('modx-user-menu');
	
	let a = document.createElement("a");
	const textUserMenu = document.createTextNode( "WEBP Конвертер" );
	a.setAttribute("id", "converter");
	a.setAttribute("title", "Очередь изображений для webp конвертера");
	a.onclick= manual_start;
	a.appendChild( textUserMenu );
	
	let webpConverterLI = document.createElement("li");
	webpConverterLI.appendChild(a);
	modxUserMenu.insertBefore(webpConverterLI, modxUserMenu.firstChild);
	
	
	// Check reload
	let converter= localStorage.getItem('converter');
	let converter_count= localStorage.getItem('converter_count');
	
	if( converter ) {
		converter= parseFloat(converter);
		converter_count= parseInt(converter_count);
		
		if(converter_count > 0) files_iterator();
		
		//if( converter < (Date.now() - 300) && converter_count == 0 ) fetch_converter('get');
	} else {
		fetch_converter('get');
	}
});
