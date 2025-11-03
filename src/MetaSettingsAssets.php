<?php
namespace simplicateca\metasettings;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

class MetaSettingsAssets extends AssetBundle
{
    public function init(): void
    {
        $this->sourcePath = '@simplicateca/metasettings/resources';
        $this->depends = [CpAsset::class];
        $this->js = ['js/MetaSettings.js'];
        $this->css = ['css/MetaSettings.css'];
        parent::init();
    }
}