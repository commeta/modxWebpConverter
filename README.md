# modxWebpConverter

MODX Revolution is a plugin that converts image files to webp format.

You can use a modified plugin to automatically replace images https://github.com/commeta/CacheMinifyLastModifiedAndWebp

To install the plugin, create a subdirectory in the path sitemodx.com/connectors/converter/ and unpack the contents of the archive into it.

Create a plugin in the Modx Revo control panel, and assign the OnManagerPageBeforeRender system event

```
<?php
switch ($modx->event->name) {
    case 'OnManagerPageBeforeRender':
        $modx->controller->addJavascript('/connectors/converter/converter.js');
    break;
}
```

The WEBP Converter link will appear in the upper-right menu of the panel.
