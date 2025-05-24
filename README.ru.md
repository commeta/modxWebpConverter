![modxWebpConverter](https://raw.githubusercontent.com/commeta/modxWebpConverter/master/screenShot.png "modxWebpConverter")

## modxWebpConverter, руководство - [Webp конвертер для MODX Revo](https://webdevops.ru/blog/webp-converter-plugin-modx.html)
MODX Revolution плагин, который осуществляет конвертацию графических файлов в формат webp. 

Плагин автоматически конвертирует ваши JPEG/PNG-изображения в формат WebP и подставляет их в HTML-код сайта, если браузер посетителя поддерживает WebP.


---

## 📦 Возможности

- **Автоматическая конверсия** JPEG и PNG в WebP с помощью утилиты `cwebp`.  
- **Интеграция в админку MODX** — в пользовательском меню появляется кнопка «WEBP Конвертер» для запуска и мониторинга процесса.  
- **Параллельная обработка** файлов — настраивается число одновременных потоков в `converter.js`.  
- **Кеширование** — конвертированные файлы сохраняются в подкаталоге `/webp/` рядом с оригиналом.  
- **Незаметная подмена** в HTML — оригинальные `<img>` заменяются на `<picture>` с WebP-версией, если браузер поддерживает.  
- **Поддержка обновлений** — при сохранении любых элементов (документов, шаблонов, чанков, сниппетов и т. д.) происходит автоматическая конверсия новых/изменённых изображений.

---

## ⚙️ Требования

- MODX Revolution ≥ 2.7  
- PHP ≥ 7.x  
- Утилита [cwebp](https://developers.google.com/speed/webp/docs/precompiled) для вашей ОС (Windows, Linux, macOS, FreeBSD, Solaris)  
- Права на запись в корневой каталог сайта для создания папки `/webp/`

---



## Установка:

1. Создаем подкаталог /connectors/converter/ и заливаем туда файлы:
* converter.php - Серверное api
* converter.js - Скрипт для админки
* Binaries - Бинарники утилиты cwebp, там есть для linux, windows, macos, freebsd, solaris.

Бинарники взяты с https://github.com/rosell-dk/webp-convert и https://developers.google.com/speed/webp/docs/precompiled

[Установка & Обновление бинарников утилиты CWEBP](https://github.com/commeta/modxWebpConverter/blob/master/Binaries/README.md)

2. **Создайте плагин в MODX**

   * В админке откройте «Элементы → Плагины → Новый плагин».
   * Назовите его, например, `modx_plugin_webp_converter`.
   * Вставьте содержимое файла `modx_plugin_webp_converter.php` (см. [исходник](modx_plugin_webp_converter.php)).
   * Привяжите плагин к событиям:

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
3. **Проверьте появление кнопки**
   В правом верхнем меню MODX должно появиться «WEBP Конвертер». Нажмите — начнётся фоновое сканирование → конверсия → замена.



---

## 🛠 Настройка

* **Число потоков**
  Откройте `converter.js` и настройте:

  ```js
  const concurent_tasks   = 3; // число одновременно работающих вкладок
  const max_count_threads = 4; // число одновременных потоков на вкладку
  ```
* **Путь к `cwebp`**
  Если бинарник не в стандартной папке, укажите путь в `converter.php`:

  ```php
  define('WEBP_CONVERTER_BIN', '/connectors/converter/Binaries/linux/cwebp');
  ```

---

## Рузультаты тестирования

Протестировал на MODX Revolution 2.7.3-pl!
* Windows 7 64bit XAMPP PHP 7.4.9, 
* linux Ubuntu 20.04 64bit LAMP PHP 7.4.3, 
* linux CentOS 7 LANMP PHP 5.4.45

Взял солянку jpg & png, 24382 файлов, 3385MB.

Все отработало нормально, потребление памяти в пике: на win 6 580 936b, на lin 3 816 368b.
Сканирование подкаталогов заняло: 191ms SSD, 3123ms HDD.
Сжатие одного файла занимает от 28ms до 5800ms.
Результирующий объем пережатых файлов: 1005MB, потери в качестве не заметил.

Открыл одновременно 12 вкладок в браузере, в результате кодировка пошла в 12 потоков, у меня на Ryzen 5 2600X Six-Core обработка всех файлов заняла около 45 минут.

---

## ❓ Часто задаваемые вопросы

1. **Можно ли конвертировать только новые изображения?**
   Да — плагин отслеживает изменения в файлах и конвертирует только новые или изменённые.
2. **Как откатиться к оригинальным изображениям?**
   Просто удалите папку `/webp/` — HTML автоматически вернётся к оригинальным путям.
3. **Поддерживается ли GIF или SVG?**
   Нет, только JPEG и PNG.

---
