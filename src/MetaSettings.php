<?php
namespace simplicateca\metasettings;

use Craft;
use craft\base\Plugin;
use craft\web\View;
use craft\services\Fields;
use craft\events\RegisterComponentTypesEvent;
use craft\events\TemplateEvent;
use yii\base\Event;
use yii\base\InvalidConfigException;
use simplicateca\metasettings\MetaSettingsAssets;
use simplicateca\metasettings\fields\MetaSettingsField;

class MetaSettings extends Plugin
{
    public function init(): void
    {
        parent::init();

        // Register our custom field type
        Event::on(
            Fields::class,
            Fields::EVENT_REGISTER_FIELD_TYPES,
            fn(RegisterComponentTypesEvent $event) => $event->types[] = MetaSettingsField::class
        );

        // Load AssetBundle for Control Panel Requests
        if (Craft::$app->getRequest()->getIsCpRequest()) {
            Event::on(
                View::class,
                View::EVENT_BEFORE_RENDER_TEMPLATE,
                function (TemplateEvent $event) {
                    try {
                        Craft::$app->getView()->registerAssetBundle(MetaSettingsAssets::class);
                    } catch (InvalidConfigException $e) {
                        Craft::error('Error registering AssetBundle: '.$e->getMessage(), __METHOD__);
                    }
                }
            );
        }
    }

    public static function pluginHandle(): string
    {
        return 'metasettings';
    }
}
