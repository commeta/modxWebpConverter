# modxWebpConverter

![modxWebpConverter](https://raw.githubusercontent.com/commeta/modxWebpConverter/master/screenShot.png "modxWebpConverter")

## modxWebpConverter Guide – [WebP Converter for MODX Revo](https://webdevops.ru/blog/webp-converter-plugin-modx.html)
A MODX Revolution plugin that automatically converts image files to the WebP format.

The plugin automatically converts your JPEG/PNG images to WebP format and injects them into your site's HTML if the visitor's browser supports WebP.

---

## 📦 Features

- **Automatic conversion** of JPEG and PNG to WebP using the `cwebp` utility.  
- **MODX admin integration** — a "WEBP Converter" button appears in the user menu to launch and monitor the process.  
- **Parallel processing** — the number of concurrent threads can be configured in `converter.js`.  
- **Caching** — converted files are saved in a `/webp/` subdirectory next to the original.  
- **Seamless HTML replacement** — original `<img>` tags are replaced with `<picture>` and WebP versions when the browser supports it.  
- **Update support** — new or updated images are automatically converted whenever elements like documents, templates, chunks, snippets, etc., are saved.

---

## ⚙️ Requirements

- MODX Revolution ≥ 2.7  
- PHP ≥ 7.x  
- The [cwebp](https://developers.google.com/speed/webp/docs/precompiled) utility for your OS (Windows, Linux, macOS, FreeBSD, Solaris)  
- Write permissions in the site root to create the `/webp/` directory

---

## 🚀 Installation

1. **Create a subdirectory `/connectors/converter/` and upload the following files:**
   - `converter.php` — server-side API  
   - `converter.js` — admin panel script  
   - `Binaries` — contains `cwebp` executables for Linux, Windows, macOS, FreeBSD, and Solaris

   Binaries sourced from:
   - https://github.com/rosell-dk/webp-convert  
   - https://developers.google.com/speed/webp/docs/precompiled  

   [Installation & Update Guide for CWEBP Binaries](https://github.com/commeta/modxWebpConverter/blob/master/Binaries/README.md)

2. **Create a plugin in MODX**

   - In the MODX admin panel, go to: Elements → Plugins → New Plugin  
   - Name it something like `modx_plugin_webp_converter`  
   - Paste the content of the file `modx_plugin_webp_converter.php` (see [source](modx_plugin_webp_converter.php))  
   - Attach the plugin to the following system events:

     ```
     OnManagerPageBeforeRender
     OnWebPagePrerender
     OnSiteRefresh
     OnTemplateSave
     OnChunkSave
     OnPluginSave
     OnSnippetSave
     OnTemplateVarSave
     OnDocFormSave
     ```

3. **Verify the appearance of the button**  
   You should see the "WEBP Converter" button in the top-right MODX admin menu. Click it to launch background scanning → conversion → replacement.

---

## 🛠 Configuration

- **Number of threads**  
  Open `converter.js` and adjust:

  ```js
  const concurent_tasks   = 3; // number of simultaneously running browser tabs
  const max_count_threads = 4; // number of concurrent threads per tab
  ```

* **Path to `cwebp`**
  If the binary is not in the default location, specify its path in `converter.php`:

  ```php
  define('WEBP_CONVERTER_BIN', '/connectors/converter/Binaries/linux/cwebp');
  ```

---

## 🧪 Testing Results

Tested on MODX Revolution 2.7.3-pl with:

* Windows 7 64bit (XAMPP, PHP 7.4.9)
* Linux Ubuntu 20.04 64bit (LAMP, PHP 7.4.3)
* Linux CentOS 7 (LANMP, PHP 5.4.45)

Test set: mixed JPEG & PNG, 24,382 files, 3,385 MB total.

All worked as expected. Peak memory usage:

* Windows: 6,580,936 bytes
* Linux: 3,816,368 bytes

Subdirectory scanning time:

* 191 ms (SSD)
* 3123 ms (HDD)

Compression time per file: 28 ms to 5800 ms
Total WebP size: 1005 MB (\~70% reduction).
No noticeable quality loss.

With 12 browser tabs open in parallel, the script used 12 threads. On a Ryzen 5 2600X Six-Core processor, the full conversion took \~45 minutes.

---

## ❓ FAQ

1. **Can I convert only new images?**
   Yes — the plugin tracks file changes and only converts new or modified images.

2. **How do I revert to the original images?**
   Just delete the `/webp/` folder — HTML will automatically fall back to the original image paths.

3. **Are GIF or SVG images supported?**
   No, only JPEG and PNG formats are supported.

---



