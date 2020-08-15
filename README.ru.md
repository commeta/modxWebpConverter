## modxWebpConverter

MODX Revolution плагин, который осуществляет конвертацию графических файлов в формат webp.

Для автоматической подмены изображений можно воспользоваться модифицированным плагином https://github.com/commeta/CacheMinifyLastModifiedAndWebp

Для установки плагина, создайте подкаталог в пути sitemodx.com/connectors/converter/ и распакуйте в него содержимое архива.

Создайте плагин в панели управления Modx Revo, и назначте системное событие OnManagerPageBeforeRender

```webp_converter
<?php
switch ($modx->event->name) {
    case 'OnManagerPageBeforeRender':
        $modx->controller->addJavascript('/connectors/converter/converter.js');
    break;
}
```

В правом верхнем меню панели появится ссылка WEBP Конвертер.
