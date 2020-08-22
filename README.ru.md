## modxWebpConverter

![modxWebpConverter](https://raw.githubusercontent.com/commeta/modxWebpConverter/master/screenShot.png "modxWebpConverter")

MODX Revolution плагин, который осуществляет конвертацию графических файлов в формат webp.

Смысл в чем:

1. Создаем подкаталог /connectors/converter/ и заливаем туда файлы:
* converter.php - Серверное api
* converter.js - Скрипт для админки
* Binaries - Бинарники утилиты cwebp, там есть для linux, windows, macos, freebsd, solaris, по умолчанию подключен для linux в файле converter.php, можно поменять!
Бинарники взяты с https://github.com/rosell-dk/webp-convert https://developers.google.com/speed/webp/docs/precompiled

2. Создаем в админке плагин: webp_converter.php и вешаем на событие OnManagerPageBeforeRender. После чего в правом верхнем углу появится значок, по клику запустится в фоне сканирование каталогов сайта, и будет создана копия каждой картинки в подкаталоге webp. 
т.е. /assets/logo.png -> /webp/assets/logo.png.webp

3. Создаем в админке плагин: webp_replacer.php и вешаем на событие OnWebPagePrerender. После чего все картинки в коде HTML будут заменены на webp, если браузер их поддерживает.

### Рузультаты тестирования

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
