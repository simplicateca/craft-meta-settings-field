<?php

namespace simplicateca\metasettings\fields\data;

use Craft;
use craft\base\Serializable;
use craft\helpers\Json;

use yii\base\BaseObject;


class MetaSettingsDropdownData extends BaseObject implements Serializable
{
    private string $_value = '';
    private array  $_json = [];
    private ?string $_label = null;
    private bool $_valid = true;

    public function __construct( $data = '', $config = [] )
    {
        if (is_array($data)) {
            $json = $data['json'] ?? null;
            $json = \is_string($json) ? Json::decodeIfJson($json, true) : $json;

            $this->_value = (string) ($data['value'] ?? '');
            $this->_json  = \is_array( $json ) ? $json : [];
            $this->_label = isset($data['label']) ? (string) $data['label'] : null;
            $this->_valid = isset($data['valid']) ? (bool) $data['valid'] : true;
        } elseif (is_string($data)) {
            $this->_value = $data;
        }

        parent::__construct($config);
    }

    public function getValue(): string
    {
        return $this->_value;
    }

    public function getSettings(): ?array{
        return $this->getJson();
    }

    public function getJson(): ?array
    {
        return $this->_json;
    }

    public function getLabel(): ?string
    {
        return $this->_label;
    }

    public function getValid(): bool
    {
        return $this->_valid;
    }

	public function __toString(): string{
        return (string) $this->_value;
	}

    public function serialize(): mixed
    {
        return array_filter([
            'value' => $this->_value,
            'json'  => $this->_json,
        ]);
    }

    public function toArray(): array
    {
        return [
            'value' => $this->_value,
            'json'  => $this->_json,
            'label' => $this->_label,
            'valid' => $this->_valid,
        ];
    }

    public function __isset($name) {
        return isset($this->getJson()[$name]) || parent::__isset($name);
    }

    public function __get($name) {
        return $this->getJson()[$name] ?? parent::__get($name);
    }

    // public function __call($name, $args) {
    //     return $this->getJson()[$name] ?? parent::__call($name, $args);
    // }

    // public static function __callStatic($name, $args) {
    //     return $this->getJson()[$name] ?? parent::__callStatic($name, $args);
    // }



    // public function hasProperty($name, $checkVars = true) {
    //     return isset($this->getJson()[$name]) || parent::hasProperty($name, $checkVars);
    // }

    // public function canGetProperty($name, $checkVars = true): bool
    // {
    //     return isset($this->getJson()[$name]) || parent::canGetProperty($name, $checkVars);
    // }
}
