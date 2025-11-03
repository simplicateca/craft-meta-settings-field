<?php

namespace simplicateca\metasettings\fields\data;

use Craft;
use craft\base\Serializable;
use craft\helpers\Json;

use yii\base\BaseObject;


class MetaSettingsDropdownData extends BaseObject implements Serializable
{
    private string $_value = '';
    private array $_json = [];
    private ?string $_label = null;
    private bool $_valid = true;

    public function __construct($data = '', $config = [])
    {
        if (is_array($data)) {
            $json = $data['json'] ?? null;
            $json = is_string($json) ? Json::decodeIfJson($json, true) : $json;

            $this->_value = (string)($data['value'] ?? '');
            $this->_json  = is_array($json) ? $json : [];
            $this->_label = $data['label'] ?? null;
            $this->_valid = isset($data['valid']) ? (bool)$data['valid'] : true;
        } elseif (is_string($data)) {
            $this->_value = $data;
        }

        parent::__construct($config);
    }

    // --- Standard Getters ---
    public function getValue(): string { return $this->_value; }
    public function getJson(): array { return $this->_json; }
    public function getLabel(): ?string { return $this->_label; }
    public function getValid(): bool { return $this->_valid; }
    public function getSettings(): array { return $this->getJson(); }

    // --- Twig/PHP proxy methods (value(), json(), etc.) ---
    public function value(): string { return $this->_value; }
    public function json(): array { return $this->_json; }
    public function label(): ?string { return $this->_label; }
    public function valid(): bool { return $this->_valid; }

    // --- Magic + serialization ---
    public function __toString(): string { return $this->_value; }

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

    public function __isset($name)
    {
        return isset($this->_json[$name]) || parent::__isset($name);
    }

    public function __get($name)
    {
        return $this->_json[$name] ?? parent::__get($name);
    }

    public function hasProperty($name, $checkVars = true)
    {
        return array_key_exists($name, $this->_json) || parent::hasProperty($name, $checkVars);
    }

    public function canGetProperty($name, $checkVars = true): bool
    {
        return array_key_exists($name, $this->_json) || parent::canGetProperty($name, $checkVars);
    }
}
