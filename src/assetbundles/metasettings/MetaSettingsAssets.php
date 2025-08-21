<?php

namespace simplicateca\metasettings\assetbundles\metasettings;

class MetaSettingsAssets extends \craft\web\AssetBundle
{
    public function init(): void
    {
        parent::init();

        $this->sourcePath = '@simplicateca/metasettings/assetbundles/metasettings/dist';
        $this->depends    = [\craft\web\assets\cp\CpAsset::class];
        $this->js         = ['js/MetaSettings.js'];
        $this->css        = ['css/MetaSettings.css'];
    }
}