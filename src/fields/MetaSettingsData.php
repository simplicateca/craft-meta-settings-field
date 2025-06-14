<?php
/**
 * MetaSettingsData - Store the serialized field data
 */

namespace simplicateca\metasettings\fields;

use Craft;
use simplicateca\metasettings\helpers\ConfigHelper;


class MetaSettingsData extends \craft\base\Model
{
    /**
     * @var string The value of the selected option
     */
    public string $value;


    /**
     * @var string The label of the selected option
     */
    // public string $label;


    /**
     * @var string The path to the configuration file
     */
    // public string $config;


    /**
     * @var object A Laravel Collection of the full field options
     */
    // public array $options = [];


    /**
     * @var string Virtual Input field values as a json encoded string.
     */
    public string $json = '{}';


    /**
     * @var object The Craft CMS Element object this field is attached to.
     *
     * This is stored to use as a variable when parsing the JSON config file
     * as it can technically contain twig conditionals and such.
     */
    // public array $element;


    /**
     * The decoded JSON values of all settings for the selected option
     *
     * @var array
     */
    private array $settings = [];


    /**
     * MetaSettingsData constructor.
     *
     * @param array $data
     */
    public function __construct( array $data ) {
        // $this->config  = $data['config']  ?? '';

        $options = ConfigHelper::load( $data['config'], $data['element'] ?? [] );
        $options = collect( $options );
        //$this->label = $data['label'] ?? $data['value'] ?? '';

        $this->json  = $data['json']  ?? '{}';
        $this->value = $data['value'] ?? '';

        $selected = $options->firstWhere( 'value', $this->value )
                 ?? $options->whereNotNull('value') ?? null;

        if( $selected ) {

            $this->value = $selected['value'] ?? $this->value;
            //$this->label = $selected['label'] ?? $this->label;

            if( $selected['settings'] ?? false ) {

                $json = json_decode( $this->json, true ) ?? [];

                foreach( $selected['settings'] as $field ) {

                    $name = $field['name'] ?? null;
                    if( ! $name ) { continue; }

                    if( $field['value'] ?? false ) {
                        $this->settings[$name] = $field['value'];
                    }

                    // if "freeform" input type
                    if( in_array( $field['type'], ['text', 'textarea', 'email', 'url', 'icon', 'money', 'date', 'time'] ) ) {
                        $this->settings[$name] = $json[$name] ?? $field['default'] ?? $field[$field['type']]['default'] ?? '';
                    };

                    // enum types
                    if( in_array( $field['type'], ['select', 'radiogroup'] ) && !empty($field['options']) ) {

                        $eval  = $json[$name] ?? $field['default'] ?? null;
                        $enum  = collect( $field['options'] );
                        $match = $enum->firstWhere( 'value', $eval ) ?? $enum->first() ?? null;

                        if( $match ) {
                            $this->settings[$name] = $match['value'];

                            $params = $match;
                            unset($params['value']);
                            unset($params['default']);
                            unset($params['label']);

                            // Merge any extra attributes into the settings
                            if( ! empty($params) ) {
                                foreach( $params as $k => $v ) {
                                    $this->settings[$k] = $v;
                                }
                            }
                        }
                    };

                    // lightswitch types
                    if( $field['type'] == 'lightswitch' ) {
                        $val = $json[$name] ?? $field['default'] ?? $field[$field['type']]['default'] ?? false;
                        $this->settings[$name] = (bool) $val;
                    }

                    // color types
                    if( $field['type'] == 'color' ) {
                        $val = $json[$name] ?? $field['default'] ?? $field[$field['type']]['default'] ?? '';

                        // Ensure the value is a valid HTML hex color code
                        $this->settings[$name] = preg_match('/^#([0-9a-fA-F]{6}|[0-9a-fA-F]{3})$/', $val)
                            ? $val
                            : '#000000'; // Default to black if invalid
                    }

                    // number types
                    if( $field['type'] == 'number' ) {
                        $min = $field['min'] ?? $field['number']['min'] ?? null;
                        $max = $field['max'] ?? $field['number']['max'] ?? null;

                        $val = $json[$name] ?? $field['default'] ?? $field[$field['type']]['default'] ?? '';

                        $this->settings[$name] = is_numeric($val)
                            ? ( $min && $val < $min ? $min : ( $max && $val > $max ? $max : $val ) )
                            : ( is_numeric($min) ? $min : ( is_numeric($max) ? $max : 0 ) );
                    }

                }
            }

            $this->json = \craft\helpers\Json::encode( $this->settings );
        }
    }

	public function __toString(): string{
        return (string) $this->value;
	}


    public function __isset($name) {
        return strtolower($name) == 'settings' || isset( $this->settings[$name] );
    }


    public function __call($name, $arguments) {
        if( (strtolower($name) == 'settings') ) return $this->settings;
        return isset( $this->settings[$name] ) ? $this->settings[$name] : null;
    }


    public function __get($name) {
        if( (strtolower($name) == 'settings') ) return $this->settings;
        return isset( $this->settings[$name] ) ? $this->settings[$name] : null;
    }


    public function rules(): array {
        return array_merge( parent::rules(), [
            ['value',  'string'],
            ['json',   'string'],
            ['config', 'string'],
        ]);
    }
}
