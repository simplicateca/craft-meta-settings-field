<?php

namespace simplicateca\metasettings\fields\data;

use Craft;
use craft\helpers\Json;
use craft\base\ElementInterface;

class MetaSettingsConfig
{
    private ?array $error = null;

    private array $_options = [];

    public function __construct(?string $source, ElementInterface|array|null $element = null, array $context = [])
    {
        if( empty($source) ) {
            $this->setError('Config source is empty');
            return;
        }

        try {
            $this->load($source, array_merge(['element' => $element], $context));
        } catch (\Throwable $e) {
            $this->setError(
                'Unable to create MetaSettings Config',
                $e->getMessage()
            );
        }
    }


    public function error(): ?array
    {
        return $this->error;
    }


    private function setError( string $message = '', mixed $details = [] ): void
    {
        if( !empty($message) ) {
            $error = [ "error" => $message ];
            $error = ( is_array($details) )
                ? $error + $details
                : $error + [ "message" => $details ?? '' ];

            $this->error[] = $error;
        }
    }


    public function list(): array
    {
        return $this->_options;
    }


    public function option( string $value ): ?array
    {
        return collect($this->_options)
            ->first(fn($item) => isset($item['value']) && $item['value'] === $value) ?: null;
    }


    public function defaultValue(): array
    {
        return collect($this->_options)
            ->first(fn($item) => isset($item['default']) && $item['default'] === true)
            ?: collect($this->_options)->first() ?: [];
    }


    public function options(): array {
        $options = collect($this->_options)
            ->filter(fn($item) => isset($item['label'], $item['value']))
            ->map(fn($item) => [
                'label' => $item['label'],
                'value' => $item['value'],
            ])->all();

        return $this->mergeDuplicateValues($options);
    }

    private function mergeDuplicateValues(array $options): array {
        $merged = [];

        foreach ($options as $option) {
            $value = $option['value'];
            $label = $option['label'];

            if (isset($merged[$value])) {
                $merged[$value]['dupe'] = true;
                $merged[$value]['label'] .= ', ' . $label;
            } else {
                $merged[$value] = $option;
            }
        }

        foreach ($merged as &$row) {
            if (isset($row['dupe']) && $row['dupe'] === true) {
                $row['label'] = ucfirst($row['value']) . ' (' . $row['label'] . ')';
                unset($row['dupe']);
            }
        }

        return array_values($merged);
    }

    private function setOptions( ?array $options = [] ): void
    {
        $this->_options = array_map(
            fn($opt) => array_merge($opt, [
                'settings' => self::fields($opt['settings'] ?? null)
            ]),
            self::unrollOptions( $options )
        );
    }

    private function looksLikeJson( string $source ): bool
    {
        return (strpos($source, '"') !== false || strpos($source, '{') !== false);
    }

    private function load( string $source, $context = [] ): void
    {
        $json = $this->looksLikeJson($source) ? Json::decodeIfJson($source, true) : null;

        if( !is_array($json) )
        {
            $path = Craft::$app->getView()->resolveTemplate($source, Craft::$app->getView()::TEMPLATE_MODE_SITE);
            $twig = $path ? '{{ include( "' . $source . '", ignore_missing = true ) | trim }}' : $source;

            try {
                $output = (string) Craft::$app->getView()->renderString( $twig, $context, Craft::$app->getView()::TEMPLATE_MODE_SITE);
            } catch (\Throwable $e) {
                $this->setError('Unable to render Twig Config', [ "message" => $e->getMessage(), "twig" => $twig, "path" => $path ] );
            }

            $json = Json::decodeIfJson( $output ?? null, true );

            if( is_string($json) ) {
                $this->setError( 'Unable to Parse JSON Config', [ "message" => json_last_error_msg(), "json" => $json, "output" => $output, "path" => $path, "source" => $source ] );
            }
        }

        if( is_array( $json ) ) {
            $this->setOptions( $json );
        } else {
            $this->setError( 'Unable to Parse Config', [ "message" => json_last_error_msg(), "source" => "decodeIfJson", "json" => $json ] );
        }
    }


    /**
     * Returns the fields in a standard format.
     *
     * @param array|null $fields The fields to process, can be associative or indexed.
     * @return array The processed fields in the format [ { type: "text", options: [], ... }, ... ]
     */
    public static function fields( ?array $fields = [] ): array
    {
        $fields = self::unrollFields( $fields );

        return array_map(
            fn($field) => [
                'type'    => self::fieldType($field),
                'options' => self::unrollOptions( $field['options'] ?? $field['select'] ?? $field['radiogroup'] ?? null ),
            ] + $field,
            $fields
        );
    }


    /**
     *  Determines the type of field based on its properties.
     */
    public static function fieldType( $field ): string {
        $types = [
            'text', 'textarea', 'number', 'email', 'url', 'lightswitch',
            'icon', 'select', 'multiselect', 'radiogroup', 'money', 'color', 'date', 'time'
        ];

        $value = $field['value'] ?? null;
        $type  = $field['type'] ?? null;

        $type  = is_string($type) && !empty($type) ? strtolower( $type ) : null;

        if( !empty($value) && ( is_string($value) || empty($type) ) ) {
            return 'hidden';
        }

        if( !empty($type) && in_array( $type, $types) ) {
            return $type;
        }

        foreach( $types as $type ) {
            if( isset($field[$type]) && !empty($field[$type]) ) {
                return strtolower( $type );
            }
        }

        return 'text';
    }


    /**
     * Unrolls a list of options into a standard format.
     *
     * @param array|null $list The list to unroll, can be associative or indexed.
     * @param string $keyName The name of the key to use for the value in the output array.
     * @return array The unrolled list in the format [ { value: "opt1", label: "Opt1" }, ... ]
     */
    // Transforms either of these formats:
    //   { opt1: { label: "Opt1" }, opt2: { label: "Opt2" }, ... }
    //   { opt1: "Opt1", opt2: "Opt2", ... }
    // into:
    //   [ { value: "opt1", label: "Opt1" }, { value: "opt2", label: "Opt2" }, ... ]
    public static function unrollOptions( ?array $list = [] ): array
    {
        $unroll = $list ?? [];

        $unroll = array_is_list($unroll)
            ? $unroll
            : array_map(
                fn($key, $value) => is_array($value)
                    ? ['value' => $key] + $value
                    : ['value' => $key, 'label' => $value],
                array_keys($unroll),
                $unroll
            );

        // remove any items that do not have a value
        $unroll = array_filter($unroll, fn($x) => isset($x['value']));

        // Ensure each item has a 'label' key, defaulting to 'value' if not present
        $unroll = array_map(fn($x) => ['label' => $x['label'] ?? $x['value']] + $x, $unroll);

        // Deduplicate the array by value
        $dedupe = [];
        foreach ($unroll as $i) {
            $dedupe[$i['value']] = $i;
        }

        return array_values($dedupe);
    }


    public static function unrollFields( ?array $list = [] ): array
    {
        $unroll = $list ?? [];
        $unroll = array_is_list($unroll)
            ? $unroll
            : array_map(
                fn($key, $value) => is_array($value)
                    ? ['name' => $key] + $value
                    : ['name' => $key, 'value' => $value],
                array_keys($unroll),
                $unroll
            );

        // remove any items that do not have a 'name' key
        $unroll = array_filter($unroll, fn($x) => isset($x['name']));

        // Ensure each item has a 'label' key, defaulting to $keyName if not present
        $unroll = array_map(fn($x) => ['label' => $x['label'] ?? $x['name']] + $x, $unroll);

        // Deduplicate the array by value
        $dedupe = [];
        foreach ($unroll as $i) {
            $dedupe[$i['name']] = $i;
        }

        return array_values($dedupe);
    }


    /**
     * Verifies and cleans the provided data against the configuration schema.
     *
     * @param array $data The data to verify, should contain 'value' and 'json' keys.
     * @return array The cleaned data with 'value', 'json', 'label', and 'valid' keys.
     */
    public function verify( array $data = [] ): array
    {
        if (isset($data['json']) && \is_string($data['json'])) {
            $data['json'] = Json::decodeIfJson($data['json'], true);
        }

        $clean = [
            'value' => $data['value'] ?? '',
            'valid' => true
        ];

        $schema = $this->option( $clean['value'] ?? '' ) ?? null;

        if( $schema ) {
            $clean['label'] = $schema['label'] ?? '';
        } else {
            $schema = $this->defaultValue();
            $clean['value'] = $schema['value'] ?? '';
            $clean['json']  = $schema['json']  ?? [];
            $clean['label'] = $schema['label'] ?? '';
            $clean['valid'] = false;
        }

        // Ensure the constants are always set
        foreach( $schema['constants'] ?? [] as $name => $value )
        {
            if( empty($name) ) { continue; }
            if( !isset($clean['json'][$name]) ) {
                $clean['json'][$name] = $value;
            }
        }

        foreach( $schema['settings'] ?? [] as $field )
        {
            $name = $field['name'] ?? null;
            $type = $field['type'] ?? null;

            if( empty($name) ) { continue; }

            if( $field['value'] ?? null ) {
                $clean['json'][$name] = $field['value'];
                continue;
            }

            // if "freeform" input type
            if( in_array( $type, ['text', 'textarea', 'email', 'url', 'icon', 'money', 'date', 'time'] ) ) {
                $clean['json'][$name] = $data['json'][$name] ?? $field['default'] ?? $field[$field['type']]['default'] ?? '';
            }

            // multiselect types
            if ($type === 'multiselect' && !empty($field['options'] ?? [])) {
                $selected = $data['json'][$name] ?? $field['default'] ?? [];
                $selected = is_array($selected) ? $selected : [$selected];

                $validSelections = collect($field['options'])
                    ->whereIn('value', $selected)
                    ->pluck('value')
                    ->all();

                if (count($validSelections) !== count($selected)) {
                    $clean['valid'] = false;
                }

                $clean['json'][$name] = $validSelections;

                // Merge additional properties from matched options
                foreach ($validSelections as $value) {
                    $matched = collect($field['options'])->firstWhere('value', $value);
                    if ($matched) {
                        $extras = collect($matched)->except(['value', 'default', 'label'])->all();
                        if (!empty($extras)) {
                            $clean['json'] = array_merge($clean['json'], $extras);
                        }
                    }
                }
            }

            // enum types
            if( in_array( $type, ['select', 'radiogroup'] ) && !empty($field['options'] ?? []) ) {

                $selected = $data['json'][$name] ?? $field['default'] ?? '';
                $matched  = collect( $field['options'] )->firstWhere( 'value', $selected ) ?? null;

                if( !$matched ) {
                    $matched = $field['options'][0];
                    $clean['valid'] = false;
                }

                if( $matched ) {
                    $clean['json'][$name] = $matched['value'];
                    $extras = collect( $matched )->except(['value', 'default', 'label'])->all();
                    if( !empty($extras) ) {
                        $clean['json'] = array_merge($clean['json'], $extras);
                    }
                }
            }

            // lightswitch types
            if( $type == 'lightswitch' ) {
                $state = $data['json'][$name] ?? $field['default'] ?? $field['lightswitch']['default'] ?? false;
                $clean['json'][$name] = (bool) $state;
            }

            // color types
            if( $type == 'color' ) {
                $color = $data['json'][$name] ?? $field['default'] ?? $field['color']['default'] ?? '';

                // Ensure the value is a valid HTML hex color code
                $clean['json'][$name] = preg_match('/^#([0-9a-fA-F]{6}|[0-9a-fA-F]{3})$/', $color)
                    ? $color
                    : '#000000'; // Default to black if invalid
            }

            // number types
            if( $type == 'number' ) {
                $min = $field['min'] ?? $field['number']['min'] ?? null;
                $max = $field['max'] ?? $field['number']['max'] ?? null;

                $num = $data['json'][$name] ?? $field['default'] ?? 0;

                $clean['json'][$name] = is_numeric($num)
                    ? ( $min && $num < $min ? $min : ( $max && $num > $max ? $max : $num ) )
                    : ( is_numeric($min) ? $min : ( is_numeric($max) ? $max : $field['default'] ?? 0 ) );
            }
        }

        $clean['json'] = JSON::encode( $clean['json'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
        return $clean;
    }
}
