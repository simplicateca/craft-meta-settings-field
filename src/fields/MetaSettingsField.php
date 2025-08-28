<?php
/**
 * MetaSettingsField
 */

namespace simplicateca\metasettings\fields;

use Craft;

use craft\base\ElementInterface;
use craft\base\SortableFieldInterface;
use craft\base\InlineEditableFieldInterface;
use craft\fields\BaseOptionsField;
use craft\helpers\Json;
use craft\helpers\FileHelper;

use simplicateca\metasettings\fields\data\MetaSettingsConfig;
use simplicateca\metasettings\fields\data\MetaSettingsDropdownData;
use simplicateca\metasettings\fields\conditions\MetaSettingsDropdownConditionRule;

use yii\db\Schema;

class MetaSettingsField extends BaseOptionsField implements SortableFieldInterface, InlineEditableFieldInterface
{
    public string $mode = 'json';
    public string $configFile = '';
    public string $configJson = '';
    public string $configTwig = '';

    public function init(): void
    {
        if( empty($this->options) ) {
            $config = new MetaSettingsConfig( $this->fieldConfig(), [ "handle" => $this->handle ] );
            $this->options = $config->options();
        }

        parent::init();
    }


    public function fieldConfig(): string
    {
        return match ($this->mode) {
            'twig' => $this->configTwig,
            'file' => $this->configFile,
            default => $this->configJson,
        };
    }


    public static function icon(): string {
        return 'list-check';
    }

    public static function displayName(): string {
		return Craft::t('metasettings', 'Dropdown (MetaSettings)');
	}

    public static function dbType(): string {
        return Schema::TYPE_JSON;
    }

    public static function phpType(): string {
        return sprintf('\\%s', MetaSettingsDropdownData::class);
    }

    /**
     * @inheritdoc
     */
    public function getElementConditionRuleType(): array|string|null
    {
        return MetaSettingsDropdownConditionRule::class;
    }


    public function getElementValidationRules(): array
    {
        return [
            [
                function(ElementInterface $element) {
                    if (empty($this->configFile) && empty($this->configJson) && empty($this->configTwig)) {
                        $element->addError(
                            $this->handle,
                            Craft::t('app', 'You must provide a value for at least one of JSON input, Twig template, or File path.')
                        );
                    }
                }
            ],
        ];
    }


    public function getSettingsHtml(): ?string
    {
        $suggestions = collect( FileHelper::findFiles(
            Craft::getAlias("@templates"),
            [ 'only' => ["*.json", "*.twig"]
        ]) );

        return Craft::$app->getView()->renderTemplate('metasettings/fields/settings', [
			'field'   => $this,
			'options' => $suggestions->map( function ($path) {
                $baseDir = Craft::getAlias("@templates") . DIRECTORY_SEPARATOR;
                return str_replace( $baseDir, '', $path ); }
            )->all()
		]);
	}


    /**
     * @inheritdoc
     */
    protected function inputHtml(mixed $value, ?ElementInterface $element, bool $inline): string
    {
        return $this->inputHtmlInternal($value, $element, false);
    }


    /**
     * @inheritdoc
     */
    public function getStaticHtml(mixed $value, ?ElementInterface $element = null): string
    {
        return $this->inputHtmlInternal($value, $element, true);
    }


    protected function inputHtmlInternal(mixed $value, ?ElementInterface $element, bool $static): string
    {
        $options = new MetaSettingsConfig( $this->fieldConfig(), $element, [ "handle" => $this->handle ] );

        return Craft::$app->getView()->renderTemplate('metasettings/fields/dropdown', [
            'field' 	=> $this,
            'value' 	=> $value,
            'options' 	=> $options->list(),
            'error' 	=> $options->error(),
            'namespace' => Craft::$app->getView()->getNamespace()
		]);
    }


	public function normalizeValue(mixed $value, ?ElementInterface $element): mixed
    {
        if( $value instanceof MetaSettingsDropdownData ) {
            return $value;
        }

        $data = [
            'value' => '',
            'json'  => null,
        ];

        if( is_string($value) ) {
            $array = Json::decodeIfJson($value, true);
            if( \is_array($array) && !array_is_list($array) ) {
                $data = $array + $data;
            } else {
                $data['value'] = $value;
            }
        } elseif( \is_array($value) && !array_is_list($value) ) {
            $data = $value + $data;
        }

        $metaconf = new MetaSettingsConfig( $this->fieldConfig(), $element, [ 'handle' => $this->handle ] );
        return new MetaSettingsDropdownData( $metaconf->verify( $data ) );
	}
}
