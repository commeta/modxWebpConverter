# modxWebpConverter
![modxWebpConverter](https://raw.githubusercontent.com/commeta/modxWebpConverter/master/screenShot.png "modxWebpConverter")
MODX Revolution is a plugin that converts image files to webp format.

Install & use

1. Create a subdirectory /connectors/converter/ and fill the files there:
* converter.php - Server api
* converter.js - Script for admin panel
* Binaries - Binary utilities cwebp, there is for linux, windows, macos, freebsd, solaris.
The binaries are taken from https://github.com/rosell-dk/webp-convert https://developers.google.com/speed/webp/docs/precompiled

2. Creating a plugin in the admin panel: modx_plugin_webp_converter.php and hang it on the events:
* OnManagerPageBeforeRender
* OnWebPagePrerender
* OnSiteRefresh 
* OnTemplateSave 
* OnChunkSave 
* OnPluginSave 
* OnSnippetSave

After that, an icon will appear in the upper-right menu. When you click on it, the site directories will be scanned in the background, and a copy of each image in the webp subdirectory will be created. 
i.e. /assets/logo.png - > /webp/assets/logo.png.webp

3. After converting all found images, all images in the site's HTML code will be replaced with webp, if the browser supports them.

### The results of testing

Tested on MODX Revolution 2.7.3-pl!
* Windows 7 64bit XAMPP PHP 7.4.9, 
* linux Ubuntu 20.04 64bit LAMP PHP 7.4.3, 
* linux CentOS 7 LANMP PHP 5.4.45

Took a selection of jpg & png, 24382 files, 3385MB.

Everything worked fine, memory consumption is at a peak: on win 6 580 936b, on lin 3 816 368b.
Scanning subdirectories took: 191ms SSD, 3123ms HDD.
Compression of a single file takes from 28ms to 5800ms.
The resulting volume of compressed files: 1005MB, no loss in quality was noticed.

I opened 12 tabs in the browser at the same time, as a result, the encoding went to 12 threads, I have a Ryzen 5 2600X Six-Core processing of all files took about 45 minutes.
