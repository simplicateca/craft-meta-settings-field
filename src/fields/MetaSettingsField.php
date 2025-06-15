<?php
/**
 * MetaSettings Field
 */

namespace simplicateca\metasettings\fields;

use Craft;
use craft\base\Field;
use craft\base\ElementInterface;
use craft\base\SortableFieldInterface;
use craft\base\PreviewableFieldInterface;

use simplicateca\metasettings\helpers\ConfigHelper;
use simplicateca\metasettings\fields\MetaSettingsData;

class MetaSettingsField extends Field implements PreviewableFieldInterface, SortableFieldInterface
{
    public string $configFile = '';
    public string $configJson = '';
    public ?string $columnType = null;

    public static function icon(): string {
        return 'list-check';
    }

    public static function displayName(): string {
		return Craft::t('metasettings', 'Dropdown (MetaSettings)');
	}

    protected function optionsSettingLabel(): string {
        return Craft::t('metasettings', 'Options');
    }

	public function getContentColumnType(): string {
		return \yii\db\Schema::TYPE_TEXT;
	}

	public function getTableAttributeHtml( $value, ElementInterface $element = null ): string {
		return ucwords(
			preg_replace('/(?<!\ )[A-Z]/', ' $0', ( $value->label ?? $value->value ?? $value ) )
		);
	}

    public function getElementConditionRuleType(): array|string|null {
        return \simplicateca\metasettings\fields\MetaSettingsConditionRule::class;
    }

    public function getSettingsHtml(): ?string {
		return Craft::$app->getView()->renderTemplate('metasettings/fields/settings', [
			'field'     => $this,
			'options'   => ConfigHelper::findJsonFiles()
		]); // autosuggest json files in the `templates` directory
	}

	public function getInputHtml(mixed $value, ?craft\base\ElementInterface $element): string
    {
        $config = empty( $this->configFile )
            ? ( empty( $this->configJson ) ? '{}' : $this->configJson )
            : $this->configFile;

        $options = ConfigHelper::load( $config, $element );

		// when we load an option that no longer exists in the field configuration
        // $deprecated = ( $value->value && !empty($value->value) && !in_array( $value->value, array_column( $options, 'value' ) ) );
        // if( $deprecated ) {
        //     array_unshift( $options, [ 'value' => $value->value, 'label' => '[UNAVAILABLE: ' . $value->value . ']', 'disabled' => true ] );
		// }

        return Craft::$app->getView()->renderTemplate('metasettings/fields/dropdown', [
            'field' 	 => $this,
            'value' 	 => $value,
            'options' 	 => $options['error'] ?? null ? [] : $options,
            'error' 	 => $options['error'] ?? null ? $options : null,
            'deprecated' => null,
            'namespace'  => Craft::$app->getView()->getNamespace()
		]);
	}


	public function normalizeValue(mixed $value, ?craft\base\ElementInterface $element): mixed
    {
        if( $value instanceof MetaSettingsData ) {
            return $value;
        }

        $data = [
            'value' => '',
            'json'  => '{}'
        ];

        if( !empty($value) ) {

            if( is_string($value) ) {
                $json = \craft\helpers\Json::decodeIfJson($value);

                if( is_string($json) ) {
                    $data['value'] = $value;
                }

                if( is_array($json) ) {
                    $value = ( !array_diff_key($data, $json) && !array_diff_key($json, $data) )
                        ? $json
                        : [ 'value' => $value ];
                }
            }

            if( \is_array($value) ) {
                $data = array_merge( $data, array_filter($value) );
            }
        }

        $data['element'] = $element;
        $data['config']  = empty( $this->configFile )
            ? ( empty( $this->configJson ) ? '{}' : $this->configJson )
            : $this->configFile;


        return new MetaSettingsData( $data ) ?? null;
	}
}
