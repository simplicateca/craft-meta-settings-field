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
    public string $label;


    /**
     * @var string The path to the configuration file
     */
    public string $config;


    /**
     * @var object A Laravel Collection of the full field options
     */
    public array $options = [];


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
    public array $element;

    private array $_settings = [];

    public function __construct( array $data ) {
        $data = array_merge([
            'value'   => null,
            'json'    => '{}',
            'config'  => null,
            'element' => null,
        ], $data);

        $this->json    = $data['json'];
        $this->value   = $data['value'];
        $this->label   = $data['value'];
        $this->config  = $data['config'];
        $this->element = ConfigHelper::minimize( $data['element'] );
        $this->options = ConfigHelper::load( $this->config, $this->element );

        $this->_freshen();
    }

    /**
     * Some times a MetaSettings field will get saved and for some reason none of the values
     * will come through. I *think* it has something to do with the way Selectize fields
     * are being loaded or flagged as dirty since -- as best as I can tell -- it only
     * happens when you save a field without interacting with it.
     *
     * So this function exist *primarily* for that reason but it also keeps settings data
     * fresh with the config files defaults when being accessed from twig templates.
     *
     * Oh and also to provide a sane fallback if an option is removed from the config file.
     */
    private function _freshen() {

        $options = collect( $this->options );
        $current = $options->firstWhere( 'value', $this->value )
                ?? $options->firstWhere( 'default' )
                ?? $options->firstWhere( 'value' )
                ?? [];

        if( empty($this->value) ) {
            $this->value = $current['value'] ?? '';
        }

        // save the label
        $this->label = $current['label'] ?? $this->value;

        // get the default & current values for any virtual inputs
        $settings = $current['settings'] ?? [];
        $virtual_defaults = $this->_defaults( $current['virtuals'] ?? [] );
        $current_json = json_decode( $this->json, true ) ?? [];

        // TODO: replace below with a function that finds all possible
        // keys that could be set inside the virtual inputs and use that to
        // prevent key poisoning / collison.
        //
        // Since I wrote the above TODO I updated `MetaSettings.js` file and there
        // is now a javascript approximation of this function that needs to be
        // run on the server side as well, so just copy that.
        //
        // Otherwise... ALWAYS set a default for every possible setting?
        // That seems excessive. Maybe a safe list of setting key names?
        //
        // This really only affects situations where massive changes are made in
        // the config file without reloading / resaving the fields in the CP AND
        // the left over values are causing issues.
        // ---------------------------------------------------------------------
        // Discard any virtuals values that don't have existing defaults
        // $merged = array_merge(
        //     $virtual_defaults,
        //     array_intersect_key(
        //         $current_json,
        //         $virtual_defaults
        //     )
        // );

        $merged = array_merge(
            $virtual_defaults,
            $current_json,
            $settings, // settings should always come last because it's immutable
        );

        $this->_settings = $merged;
    }


    // If the array keys of the keys match the keys, then the array must
    // not be associative (e.g. the keys array looked like {0:0, 1:1...}).
    public static function is_assoc( array $array ) {
        $keys = array_keys($array);
        return array_keys($keys) !== $keys;
    }


    private function _defaults( $virtuals = [] ) {

        // do a first pass to get all the values
        $values = collect( $virtuals )
            ->filter(function ($item) {
                return is_array($item) && array_key_exists('name', $item) && !empty($item['name']);
            })
            ->mapWithKeys( function ($item) {
                return [ $item['name'] => $item['value'] ?? $item['default'] ?? null ];
            })->all();


        // now go back over and double check select fields for starting values
        // and for extra attributes attached to the selected option
        foreach( $virtuals as $i ) {
            if( strtolower( $i['type'] ) == 'select' && !empty( $i['options'] ) ) {

                // try really hard to set a default starting value
                $options = $i['options'];
                reset($options);
                $firstopt = MetaSettingsData::is_assoc( $i['options'] )
                    ? key($options)
                    : collect( $i['options'] )->firstWhere( 'value' ) ?? [];

                if( empty($values[$i['name']]) && $firstopt ) {
                    $values[$i['name']] = is_array( $firstopt ) ? $firstopt['value'] : $firstopt;
                }

                // now find any extra option attributes
                if( is_array( $firstopt ) ) {
                    unset( $firstopt['optgroup'] );
                    unset( $firstopt['value'] );
                    unset( $firstopt['label'] );
                    if( !empty($firstopt) ) {
                        $values = array_merge( $values, $firstopt );
                    }
                }
            }
        }

        return $values;
    }

	public function __toString(): string{
        return (string) $this->value;
	}


    public function __isset($name) {
        return strtolower($name) == 'settings' || isset( $this->_settings[$name] );
    }


    public function __call($name, $arguments) {
        if( (strtolower($name) == 'settings') ) return $this->_settings;
        return isset( $this->_settings[$name] ) ? $this->_settings[$name] : null;
    }


    public function __get($name) {
        if( (strtolower($name) == 'settings') ) return $this->_settings;
        return isset( $this->_settings[$name] ) ? $this->_settings[$name] : null;
    }


    public function rules(): array {
        return array_merge( parent::rules(), [
            ['value',  'string'],
            ['json',   'string'],
            ['config', 'string'],
        ]);
    }
}
