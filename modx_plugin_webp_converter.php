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
	file_put_contents(MODX_CONNECTORS_PATH.'converter/converter.flg', ''); // Autostart search new files, after reload admin page
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
    // If replacing is disabled for logged-in users and the user is logged in, exit
    if ($disable_replacing_for_logged_user && $modx->user->hasSessionContext('mgr'))
        return '';

    // Set up caching options
    $options = [xPDO::OPT_CACHE_KEY => 'webp_on_page'];
    $cache_key = md5(MODX_SITE_URL . $_SERVER['REQUEST_URI']);

    // Try to get cached WebP replacements
    $cached_webp_on_page = $modx->cacheManager->get($cache_key, $options);
    $output = &$modx->resource->_output;

    if (empty($cached_webp_on_page)) {
        // If no cache, process the page
        $webp_on_page = [];

        // Load HTML and get relevant elements
        $dom = new DOMDocument();
        @$dom->loadHTML($output, LIBXML_NOERROR);
        $images = $dom->getElementsByTagName('img');
        $divs = $dom->getElementsByTagName('div');
        $sections = $dom->getElementsByTagName('section');
        $footers = $dom->getElementsByTagName('footer');
        $styles = $dom->getElementsByTagName('style');

        // Combine all elements into one array
        $elements = array_merge(
            iterator_to_array($images),
            iterator_to_array($divs),
            iterator_to_array($sections),
            iterator_to_array($footers)
        );

        // Process each element
        foreach ($elements as $element) {
            $src = $element->getAttribute('src');
            $dataSrc = $element->getAttribute('data-src');
            $dataBackground = $element->getAttribute('data-background');
            $srcset = $element->getAttribute('srcset');
            $style = $element->getAttribute('style');

            // Check various source attributes for WebP conversion
            $sources = array($src, $dataSrc, $dataBackground);
            foreach ($sources as $source) {
                check_image_file_for_webp_converter($source, $webp_on_page);
            }

            // Process srcset if present
            if ($srcset) {
                $srcsetArray = explode(',', $srcset);
                foreach ($srcsetArray as $srcsetItem) {
                    $srcsetItemArray = explode(' ', trim($srcsetItem));
                    check_image_file_for_webp_converter($srcsetItemArray[0], $webp_on_page);
                }
            }

            // Process inline style if present
            if ($style) {
                preg_match_all('/url\s*\(\s*[\'"]?([^\'")]+)[\'"]?\s*\)/i', $style, $matches);
                if (!empty($matches[1])) {
                    foreach ($matches[1] as $url) {
                        check_image_file_for_webp_converter($url, $webp_on_page);
                    }
                }
            }
        }

        // Process <style> tags
        foreach ($styles as $style) {
            preg_match_all('/url\s*\(\s*[\'"]?([^\'")]+)[\'"]?\s*\)/i', $style->nodeValue, $matches);
            if (!empty($matches[1])) {
                foreach ($matches[1] as $url) {
                    check_image_file_for_webp_converter($url, $webp_on_page);
                }
            }
        }

        // Add some additional replacements
        $webp_on_page['/webp/webp/'] = '/webp/';
        $webp_on_page['//webp/'] = '/webp/';
        $webp_on_page['.webp.webp'] = '.webp';

        // Apply replacements if any found
        if (count($webp_on_page))
            $output = str_replace(array_keys($webp_on_page), array_values($webp_on_page), $output);

        // Cache the results
        $modx->cacheManager->set($cache_key, serialize($webp_on_page), 0, $options);
    } else {
        // If cache exists, use it
        $webp_on_page = unserialize($cached_webp_on_page);
        if (count($webp_on_page)) {
            $output = str_replace(array_keys($webp_on_page), array_values($webp_on_page), $output);
        }
    }
    return '';
}
