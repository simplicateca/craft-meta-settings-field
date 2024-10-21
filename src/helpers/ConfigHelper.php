<?php
/**
 * Utility functions for managing MetaSettings JSON config files
 */

namespace simplicateca\metasettings\helpers;

use Craft;

class ConfigHelper
{

    public static function load( string $filename = null, mixed $element = null ): array {

        if( !$filename ) { return []; }

        // Tell Craft we don't want to render to the regular output right now
        $view = Craft::$app->getView();
		$templateMode = $view->getTemplateMode();
		$view->setTemplateMode($view::TEMPLATE_MODE_SITE);

        // Force the config to render as an array rather than risk it being a single object.
        // Its easy enough to pull the inner array out and move on with life than risk throwing errors.
        $jsonEncodedOptions = '[' . $view->renderString(
            ConfigHelper::getConfigInclude( $filename ),
            ConfigHelper::minimize( $element )
        ) . ']';

        // Decode the the json and make sure we don't have a nested array situation per the above comment
        $options = json_decode( $jsonEncodedOptions, true );
        $options = is_array( $options[0][0] ?? null ) ? $options[0] : $options;

        // Tell Craft to return to whatever template render mode it was in before
        $view->setTemplateMode($templateMode);

        return $options ? $options : [];
    }


    // return all options as a "value => name" pair for a file path or an existing options array
    public static function options( $source = null ): object {
        $options = is_array( $source ) ? $source : ConfigHelper::load( $source );
        return collect( $options )
            ->filter(function ($item) {
                return array_key_exists('value', $item);
            })
            ->mapWithKeys( function ($item, $key) {
			    return [$item['value'] => $item['label']];
		    }
        );
    }


    // return a single option hash by key value
    public static function option( $source = null, $value = null ): mixed {
        $options = is_array( $source ) ? $source : ConfigHelper::load( $source );
        return array_filter($options, function ($item) use ($value) {
            return $item['value'] === $value;
        })[0] ?? null;
    }


    // get a list of possible json config files for autosuggest
    public static function findJsonFiles(): array {

        $configFiles = \craft\helpers\FileHelper::findFiles(
            Craft::getAlias("@templates"),
            [ 'only' => ['*.json', '*.json.twig']
        ]);

		return
            collect( $configFiles )
            ->map( function ($path) {
                $baseDir = Craft::getAlias("@templates") . DIRECTORY_SEPARATOR;
                return str_replace( $baseDir, '', $path );
            })
            ->all();
    }

    private static function getConfigInclude( string $filename ): string {
        $sanitized = trim( preg_replace('/[{}%]/', '', $filename, -1) );
        return "{% include '{$sanitized}' ignore missing %}";
    }


    public static function minimize( $element = null ): array {

        if( !$element ) { return []; }

        if( is_array( $element ) ) {
            return $element;
        }

        // find the overall owner of this field (if it's different from the immediate element)
        // i.e. is this field attached directly to an entry element or is it part of a matrix
        // or a super table field that is itself attached to an entry element?
        $owner = $element->primaryOwner ?? $element;

        $subname = $element->type->name   ?? $element->volume->name   ?? $element->site->name   ?? null;
        $subtype = $element->type->handle ?? $element->volume->handle ?? $element->site->handle ?? null;

        return [
            'name'    => $subname,
            'type'    => $subtype,
            'field'   => $element->field->handle ?? null,
            'element' => (string) get_class( $element ),
            'owner'   => [
                'site'    => $owner->site->handle    ?? null,
                'type'    => $owner->type->handle    ?? null,
                'level'   => $owner->level           ?? null,
                'section' => $owner->section->handle ?? null,
                'element' => (string) get_class( $owner ),
            ],
        ];
    }
}
