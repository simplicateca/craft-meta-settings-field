<?php
namespace simplicateca\metasettings;

use Craft;
use craft\base\Plugin;
use craft\web\View;
use craft\services\Fields;
use craft\events\RegisterComponentTypesEvent;
// use craft\events\RegisterTemplateRootsEvent;
use craft\events\TemplateEvent;

use yii\base\Event;
use yii\base\InvalidConfigException;

use simplicateca\metasettings\fields\MetaSettingsField;

class MetaSettings extends Plugin
{
    public function init()
    {
        parent::init();

        // $rootPath = $this->getBasePath() . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'samples';
        // Event::on( View::class, View::EVENT_REGISTER_SITE_TEMPLATE_ROOTS, function (RegisterTemplateRootsEvent $e) {
        //     $e->roots['_metasettings'] = $this->getBasePath() . DIRECTORY_SEPARATOR . 'templates'  . DIRECTORY_SEPARATOR . 'samples';
        // });

        // Event::on(
        //     View::class,
        //     View::EVENT_REGISTER_CP_TEMPLATE_ROOTS,
        //     function (RegisterTemplateRootsEvent $e) {
        //         $e->roots['_metasettings'] = $this->getBasePath() . DIRECTORY_SEPARATOR . 'templates'  . DIRECTORY_SEPARATOR . 'samples';
        //     }
        // );


        // Register our field
        Event::on( Fields::class, Fields::EVENT_REGISTER_FIELD_TYPES, function ( RegisterComponentTypesEvent $event ) {
            $event->types[] = MetaSettingsField::class;
        });

        // Load AssetBundle for Control Panel Requests
        if (Craft::$app->getRequest()->getIsCpRequest()) {
            Event::on( View::class, View::EVENT_BEFORE_RENDER_TEMPLATE, function ( TemplateEvent $event ) {
                try {
                    Craft::$app->getView()->registerAssetBundle(
                        \simplicateca\metasettings\assetbundles\metasettings\MetaSettingsAssets::class
                    );
                } catch ( InvalidConfigException $e ) {
                    Craft::error( 'Error registering AssetBundle - '.$e->getMessage(), __METHOD__ );
                }
            });
        }
    }

    public static function pluginHandle(): string
    {
        return 'metasettings';
    }

}