
Ext.onReady(function() {
	
	function manual_start(){
		console.log( 'manual_start' );
		document.getElementById('converter').innerHTML= "Поиск изображений";
		fetch_converter('get');
		
	}
	
	function files_iterator(){
		for (var i = 0, length = localStorage.length; i < length; i++) {
			let key= localStorage.key(i);
			
			//console.log( localStorage.key(i), ' = ', localStorage.getItem(localStorage.key(i)) );
			
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
		// удалить дубликаты, перепроверить если есть то вернуть новый список
	}
	
	function fetch_converter(mode, file= false){
		let upload = {
			"mode": mode,
			"file": file
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
				if(data.mode == 'get'){
					localStorage.setItem('converter', Date.now());
					localStorage.setItem('converter_count', data.count);
						
					data.images.forEach(function(file, index, created) {
						localStorage.setItem('convert_img'+index, file);
					});
						
					files_iterator();
				}

				if(data.mode == 'convert'){
					files_iterator();
				}
			}
		}).catch(() => console.log('converter ошибка!'));
	}
	
	
	// Init
	let modxUserMenu= document.getElementById('modx-user-menu');
	
	let a = document.createElement("a");
	const text = document.createTextNode( "WEBP Конвертер" );
	a.setAttribute("id", "converter");
	a.setAttribute("title", "Очередь изображений для webp конвертера");
	a.onclick= manual_start;
	a.appendChild( text );
	
	let li = document.createElement("li");
	li.appendChild(a);
	modxUserMenu.prepend(li);
  
	
	// Check reload
	let converter= localStorage.getItem('converter');
	let converter_count= localStorage.getItem('converter_count');
	
	if( converter ) {
		console.log( 'converter: ', converter );
		
		converter= parseFloat(converter);
		converter_count= parseInt(converter_count);
		
		if(converter_count > 0) files_iterator();
		
		console.log( converter );
		console.log( Date.now() );
		
		//if( converter < (Date.now() - 300) && converter_count == 0 ) fetch_converter('get');
	} else {
		fetch_converter('get');
	}
});
