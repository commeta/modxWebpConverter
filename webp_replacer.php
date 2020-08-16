<?php
// Modx revo plugin: replace jpg and png images to webp

if ($modx->event->name == 'OnWebPagePrerender' && strpos( $_SERVER['HTTP_ACCEPT'], 'image/webp' ) !== false) {
    $options = array(xPDO::OPT_CACHE_KEY=>'webp_on_page');

    $cached_webp_on_page= $modx->cacheManager->get($cache_key, $options);
    $output= &$modx->resource->_output;

    if( empty($cached_webp_on_page) ){
        $webp_on_page= [];
        $uniq_imgs= [];
        preg_match_all('/<img[^>]+>/i',$output, $result);
        
        if (count($result))	{
        	foreach($result[0] as $img_tag)	{			
        		preg_match('/(src)=("[^"]*")/i',$img_tag, $img[$img_tag]);						
        		$img_real = str_replace('"','',$img[$img_tag][2]);
        		$img_real = str_replace('./','',$img_real);			
        
         	 	if ((strpos($img_real, '.jpg')!==false) or (strpos($img_real, '.jpeg')!==false) or (strpos($img_real, '.png')!==false)) {
         	 	    if( !in_array($img_real, $uniq_imgs) ){
         	 	        $uniq_imgs[]= $img_real;
         	 	        
         	 	        $abs= rel2abs_img( $img_real, MODX_SITE_URL.parse_url($_SERVER['REQUEST_URI'])['path'] );
         	 	        $abs_base= str_replace('//', '/', MODX_BASE_PATH.$abs);
         	 	        
         	 	        $webp= '/webp'.$abs.'.webp';
         	 	        $webp_base= str_replace('//', '/', MODX_BASE_PATH.$webp);
         	 	        
         	 	        if( file_exists($abs_base) && file_exists($webp_base) ){
         	 	            $webp_on_page[$img_real]= $webp;
         	 	        }
         	 	    }
         	 	}
        	}
        	
            $output = str_replace(array_keys($webp_on_page), array_values($webp_on_page), $output);
        }

        $modx->cacheManager->set($cache_key, serialize($webp_on_page), 0, $options);
    } else {
        $webp_on_page= unserialize($cached_webp_on_page);
        if( count($webp_on_page) ){
            $output = str_replace(array_keys($webp_on_page), array_values($webp_on_page), $output);
        }
    }
    return '';
}


function rel2abs_img( $rel, $base ) {
	// parse base URL  and convert to local variables: $scheme, $host,  $path
	extract( parse_url( $base ) );

	if ( strpos( $rel,"//" ) === 0 ) {
		return $scheme . ':' . $rel;
	}

	// return if already absolute URL
	if ( parse_url( $rel, PHP_URL_SCHEME ) != '' ) {
		return $rel;
	}

	// queries and anchors
	if ( $rel[0] == '#' || $rel[0] == '?' ) {
		return $base . $rel;
	}

	// remove non-directory element from path
	$path = preg_replace( '#/[^/]*$#', '', $path );

	// destroy path if relative url points to root
	if ( $rel[0] ==  '/' ) {
		$path = '';
	}

	// dirty absolute URL
	$abs = $path . "/" . $rel;

	// replace '//' or  '/./' or '/foo/../' with '/'
	$abs = preg_replace( "/(\/\.?\/)/", "/", $abs );
	$abs = preg_replace( "/\/(?!\.\.)[^\/]+\/\.\.\//", "/", $abs );

	// absolute URL is ready!
	return $abs;
}
