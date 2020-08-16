# modxWebpConverter

MODX Revolution is a plugin that converts image files to webp format.

Install & use

1. Create a subdirectory /connectors/converter/ and fill the files there:
* converter.php - Server api
* converter.js - Script for admin panel
* Binaries - Binary utilities cwebp, there is for linux, windows, macos, by default connected for linux in the file converter.php, you can change it!

2. Creating a plugin in the admin panel: webp_converter.php and hang it on the OnManagerPageBeforeRender event. After that, an icon will appear in the upper-right corner. when you click on it, the site directories will be scanned in the background, and a copy of each image in the webp subdirectory will be created. 
i.e. /assets/logo.png - > /webp/assets/logo.png.webp

3. Creating a plugin in the admin panel: webp_replacer.php and hang it on the OnWebPagePrerender event. After that, all images in the HTML code will be replaced with webp, if the browser supports them.
