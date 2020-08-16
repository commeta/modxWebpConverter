<?php
// Modx revo plugin: convert jpg and png images to webp in admin panel
switch ($modx->event->name) {
    case 'OnManagerPageBeforeRender':
        $modx->controller->addJavascript('/connectors/converter/converter.js');
    break;
}
