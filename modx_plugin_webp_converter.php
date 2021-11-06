<?php
/*
 * Modx revo plugin:
 *  Replace img files from jpg or png to webp
 *  Clear cached img array
 *  Add button in user menu Modx revo admin panel
 * 
 * 
 * Use events: 
 *   OnManagerPageBeforeRender
 *   OnWebPagePrerender
 *   OnSiteRefresh
 *   OnTemplateSave
 *   OnChunkSave
 *   OnPluginSave
 *   OnSnippetSave
 *   OnTemplateVarSave
 *   OnDocFormSave
 * 
 * https://github.com/commeta/modxWebpConverter
 * https://webdevops.ru/blog/webp-converter-plugin-modx.html
 * 
 * Copyright 2021 commeta <dcs-spb@ya.ru>
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
 
$disable_replacing_for_logged_user= false; // set true for Disable replacing for logged manager user !!!



if($modx->event->name == 'OnManagerPageBeforeRender') {// convert jpg and png images to webp in admin panel
	$modx->controller->addJavascript('/connectors/converter/converter.js');
}



if(
	$modx->event->name == 'OnSiteRefresh' ||
	$modx->event->name == 'OnTemplateSave' ||
	$modx->event->name == 'OnChunkSave' ||
	$modx->event->name == 'OnPluginSave' ||
	$modx->event->name == 'OnTemplateVarSave' ||
	$modx->event->name == 'OnDocFormSave' ||
	$modx->event->name == 'OnSnippetSave'
) {
	$options= [xPDO::OPT_CACHE_KEY=>'webp_on_page']; // Clear webp modx cache
	$modx->cacheManager->clean($options);
	file_put_contents(MODX_CONNECTORS_PATH.'/converter/converter.flg', ''); // Autostart search new files, after reload admin page
}



if(!function_exists('rel2abs_img')) { 
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
}



if(!function_exists('check_image_file_for_webp_converter')) {
	function check_image_file_for_webp_converter($img_real, &$webp_on_page){
		static $uniq_imgs= [];
		
		$img_real= trim($img_real);
		if(in_array($img_real, $uniq_imgs)) return;
		$uniq_imgs[]= $img_real;
		
		$ext= strtolower(pathinfo($img_real, PATHINFO_EXTENSION));
		if(
			$ext == 'jpg' ||
			$ext == 'jpeg' ||
			$ext == 'png' 
		) {
			$abs= rel2abs_img( $img_real, MODX_SITE_URL.parse_url($_SERVER['REQUEST_URI'])['path'] );
			$abs_base= str_replace('//', '/', MODX_BASE_PATH.$abs);

			$webp= '/webp'.$abs.'.webp';
			$webp_base= str_replace('//', '/', MODX_BASE_PATH.$webp);
								
			if( file_exists($abs_base) && file_exists($webp_base)  ){
				$webp_on_page[$img_real]= $webp;
			}
		}
	}
}



if( // replace jpg and png images to webp
	$modx->event->name == 'OnWebPagePrerender' && 
	stripos($_SERVER['HTTP_ACCEPT'], 'image/webp') !== false
){
	if($disable_replacing_for_logged_user && $modx->user->hasSessionContext('mgr')) return ''; 
	
	$options= [xPDO::OPT_CACHE_KEY=>'webp_on_page'];
	$cache_key= md5(MODX_SITE_URL.$_SERVER['REQUEST_URI']);

	$cached_webp_on_page= $modx->cacheManager->get($cache_key, $options);
	$output= &$modx->resource->_output;

	if( empty($cached_webp_on_page) ){
		$webp_on_page= [];
		
		preg_match_all('/<img[^>]+>/i', $output, $result);
		if(count($result)){ // Search images in img tag
			foreach($result[0] as $img_tag)	{
				$img_tag= str_replace("'", '"', $img_tag); // src
				preg_match('/(src)=("[^"]*")/i', $img_tag, $img[$img_tag]);						
				$img_real= str_replace('"', '', $img[$img_tag][2]);
				check_image_file_for_webp_converter($img_real, $webp_on_page);
				
				preg_match('/(data-src)=("[^"]*")/i', $img_tag, $img[$img_tag]); // data-src					
				$img_real= str_replace('"', '', $img[$img_tag][2]);
				check_image_file_for_webp_converter($img_real, $webp_on_page);
				
				preg_match('/(srcset)=("[^"]*")/i', $img_tag, $img[$img_tag]); // srcset
				$srcset= explode(',', str_replace('"', '', $img[$img_tag][2]));
				foreach($srcset as $src_item){
				    $src_a= explode(' ', $src_item);
				    if(isset($src_a[0]) && !empty($src_a[0])) {
				        check_image_file_for_webp_converter($src_a[0], $webp_on_page);
				    } else {
				        if(isset($src_a[1]) && !empty($src_a[1])) {
				            check_image_file_for_webp_converter($src_a[1], $webp_on_page);
				        }
				    }
				}
			}
		}

		preg_match_all('/url\(([^)]*)"?\)/iu', $output, $result);
		if(count($result)){ // Search images in url css rules
			foreach($result[1] as $img_tag)	{
				if(stripos($img_real, 'data:')) continue;
				$img_real= str_replace(['"',"'"], '', $img_tag);
				check_image_file_for_webp_converter($img_real, $webp_on_page);
			}
		}
		
		$webp_on_page['/webp/webp/']= '/webp/';
		$webp_on_page['//webp/']= '/webp/';
		$webp_on_page['.webp.webp']= '.webp';
		
		if(count($webp_on_page)) $output= str_replace(array_keys($webp_on_page), array_values($webp_on_page), $output);
		$modx->cacheManager->set($cache_key, serialize($webp_on_page), 0, $options);
	} else {
		$webp_on_page= unserialize($cached_webp_on_page);
		if(count($webp_on_page)){
			$output= str_replace(array_keys($webp_on_page), array_values($webp_on_page), $output);
		}
	}
	return '';
}
