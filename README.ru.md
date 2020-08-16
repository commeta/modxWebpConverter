## modxWebpConverter

MODX Revolution плагин, который осуществляет конвертацию графических файлов в формат webp.

Смысл в чем:

1. Создаем подкаталог /connectors/converter/ и заливаем туда файлы:
* converter.php - Серверное api
* converter.js - Скрипт для админки
* Binaries - Бинарники утилиты cwebp, там есть для linux, windows, macos, по умолчанию подключен для linux в файле converter.php, можно поменять!

2. Создаем в админке плагин: webp_converter.php и вешаем на событие OnManagerPageBeforeRender. После чего в правом верхнем углу появится значок, по клику запустится в фоне сканирование каталогов сайта, и будет создана копия каждой картинке в подкаталоге webp. 
т.е. /assets/logo.png -> /webp/assets/logo.png.webp

3. Создаем в админке плагин: webp_replacer.php и вешаем на событие OnWebPagePrerender. После чего все картинки в коде HTML будут заменены на webp, если браузер их поддерживает.
