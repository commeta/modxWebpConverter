<?php
// Modx revo plugin: convert jpg and png images to webp in admin panel
// Добавляет индикатор в левом верхнем меню. Осуществляет конвертацию изображений в формат webp.
switch ($modx->event->name) {
    case 'OnManagerPageBeforeRender':
        $modx->controller->addJavascript('/connectors/converter/converter.js');
    break;
}
