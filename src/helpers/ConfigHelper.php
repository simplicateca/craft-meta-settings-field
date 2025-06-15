<?php
/**
 * Utility functions for managing MetaSettings JSON config files
 */

namespace simplicateca\metasettings\helpers;

use Craft;
use craft\base\Field;
use craft\base\ElementInterface;

class ConfigHelper
{

    public static function load( string|null $input = null, mixed $element = null ): array {

        if( !$input ) { return [ 'error' => "No field configuration provided", "string" => $input ]; }

        $config = \craft\helpers\Json::decodeIfJson( $input, true );

        // could not parse json, so assume it's a file path
        if( is_string( $config ) ) {
            $jsonString = Craft::$app->getView()->renderString(
                ConfigHelper::twigIncludeStatement($input),
                ConfigHelper::normalizeElement($element),
                Craft::$app->getView()::TEMPLATE_MODE_SITE
            );

            $config = \craft\helpers\Json::decodeIfJson( $jsonString, true );

            // If there was a problem decoding the JSON, return an error message
            if( json_last_error() !== JSON_ERROR_NONE || !is_array($config) ) {
                return [ 'error' => json_last_error_msg(), 'string' => $jsonString ];
            }
        }

        if( !is_array( $config ) ) {
            return [ 'error' => "Invalid field configuration format: expected an array or a valid JSON string, got " . gettype($config), "string" => $input ];
        }

        return self::parseConfig( $config );
    }

    // Transforms either of these formats:
    //   { opt1: { label: "Opt1" }, opt2: { label: "Opt2" }, ... }
    //   { opt1: "Opt1", opt2: "Opt2", ... }
    // into:
    //   [ { value: "opt1", label: "Opt1" }, { value: "opt2", label: "Opt2" }, ... ]
    public static function parseConfig( $object = [] ): array {
        $array = ( array_is_list($object) )
            ? $object
            : array_map(
                fn($key, $value) => is_array($value)
                    ? ['value' => $key] + $value
                    : ['value' => $key],
                array_keys($object),
                $object
            );

        // Only return $options with a minimum of `$idField` field
        return array_values( array_map(
            fn($item) => [
                'label' => $item['label'] ?? $item['value'] ?? null,
                'value' => $item['value'] ?? $item['label'] ?? null,
                'settings' => $item['settings'] ?? false
                    ? self::parseSettings($item['settings'])
                    : null,
            ] + $item,
            array_filter($array, fn($item) => isset($item['value']) || isset($item['label']) )
        ) );
    }


    // Transforms either of these formats:
    //   { opt1: { label: "Opt1" }, opt2: { label: "Opt2" }, ... }
    //   { opt1: "Opt1", opt2: "Opt2", ... }
    // into:
    //   [ { value: "opt1", label: "Opt1" }, { value: "opt2", label: "Opt2" }, ... ]
    public static function parseSettings( $object = [] ): array {
        $array = ( array_is_list($object) )
            ? $object
            : array_map(
                fn($key, $value) => is_array($value)
                    ? ['name' => $key] + $value
                    : ['name' => $key, 'value' => $value],
                array_keys($object),
                $object
            );

        return array_values( array_map(
            fn($item) => [
                'label'   => $item['label'] ?? $item['name'],
                'type'    => self::settingType($item),
                'options' => self::parseOptions( $item['options'] ?? $item['select'] ?? $item['radiogroup'] ?? null ),
            ] + $item,
            array_filter($array, fn($item) => isset($item['name']))
        ) );
    }


    public static function parseOptions( $options = [] ): array {
        $options = $options ?? [];
        $options = ( $options && array_is_list($options) )
            ? $options
            : array_map(
                fn($key, $value) => is_array($value)
                    ? ['value' => $key] + $value
                    : ['value' => $key, 'label' => $value],
                array_keys($options),
                $options
            );

        return array_values( array_map(
            fn($item) => [
                'label' => $item['label'] ?? $item['value'] ?? null,
                'value' => $item['value'] ?? $item['label'] ?? null
            ] + $item,
            array_filter($options, fn($item) => isset($item['value']) || isset($item['label']) )
        ) );
    }


    public static function settingType( $setting ): string {
        $types = [
            'text', 'textarea', 'number', 'email', 'url', 'lightswitch',
            'icon', 'select', 'radiogroup', 'money', 'color', 'date', 'time'
        ];

        if( is_array($setting) && isset($setting['value']) ) {
            return 'hidden';
        }

        if( is_array($setting) && isset($setting['type']) && in_array(strtolower($setting['type']), $types) ) {
            return strtolower( $setting['type'] );
        }

        if( is_array($setting) ) {
            foreach( $types as $type ) {
                if( isset($setting[$type]) && !empty($setting[$type]) ) {
                    return strtolower( $type );
                }
            }
        }

        return 'text';
    }


    // return all options as a "value => label" pair for a file path or an existing options array
    public static function allowedValues( $source = null, $element = null ): array {
        $options = ConfigHelper::load( $source, $element );

        if( $options['error'] ?? false ) {
            return [];
        }

        return collect( $options )
            ->mapWithKeys( fn($i) => [$i['value'] => $i['label']] )
            ->all();
    }


    // return a single option hash by key value
    public static function option( $source = null, $value = null ): mixed {
        $allowed = collect( self::allowedValues( $source ) );
        return $allowed->firstWhere( 'value', $value )
            ?? $allowed->first();
    }


    // get a list of possible json config files for autosuggest
    public static function findJsonFiles(): array {

        $configFiles = \craft\helpers\FileHelper::findFiles(
            Craft::getAlias("@templates"),
            [ 'only' => ["*.json", "*.twig"]
        ]);

		return
            collect( $configFiles )
            ->map( function ($path) {
                $baseDir = Craft::getAlias("@templates") . DIRECTORY_SEPARATOR;
                return str_replace( $baseDir, '', $path );
            })
            ->all();
    }

    private static function twigIncludeStatement( string $filename ): string {
        $sanitized = trim( preg_replace('/[{}%]/', '', $filename, -1) );
        return "{{- include( '{$sanitized}', ignore_missing = true ) | trim -}}";
    }


    public static function normalizeElement( ElementInterface|array|null $element = null ): array {

        if( !$element ) { return []; }
        if( is_array($element) ) { return $element; }

        // find the overall owner of this field (if it's different from the immediate element)
        // i.e. is this field attached directly to an entry element or is it part of a matrix
        // or a super table field that is itself attached to an entry element?
        $owner = $element->primaryOwner ?? $element;

        return [
            'name'  => $element->type->name   ?? $element->volume->name   ?? $element->site->name   ?? null,
            'type'  => $element->type->handle ?? $element->volume->handle ?? $element->site->handle ?? null,
            'field' => $element->field->handle ?? null,
            'owner' => [
                'id'      => $owner->id ?? null,
                'type'    => $owner->type->handle    ?? null,
                'section' => $owner->section->handle ?? null,
                'volume'  => $owner->volume->handle  ?? null,
                'level'   => $owner->level           ?? null,
                'site'    => $owner->site->handle    ?? null,
            ],
        ];
    }
}
