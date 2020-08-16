<?php
switch ($modx->event->name) {
    case 'OnManagerPageBeforeRender':
        $modx->controller->addJavascript('/connectors/converter/converter.js');
    break;
}
