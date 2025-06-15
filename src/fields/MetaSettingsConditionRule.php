<?php

namespace simplicateca\metasettings\fields;

use craft\fields\data\MultiOptionsFieldData;
use craft\fields\data\SingleOptionFieldData;
use craft\base\conditions\BaseMultiSelectConditionRule;
use craft\fields\conditions\FieldConditionRuleTrait;
use craft\fields\conditions\FieldConditionRuleInterface;

use simplicateca\metasettings\helpers\ConfigHelper;

class MetaSettingsConditionRule extends BaseMultiSelectConditionRule implements FieldConditionRuleInterface
{
    use FieldConditionRuleTrait;

    protected function options(): array
    {
        $config = empty( $this->field()->configFile )
            ? $this->field()->configJson
            : $this->field()->configFile;

        return ConfigHelper::allowedValues( $config );
    }


    /**
     * @inheritdoc
     */
    protected function elementQueryParam(): ?array
    {
        return $this->paramValue();
    }


    /**
     * @inheritdoc
     */
    protected function matchFieldValue($value): bool
    {
        if ($value instanceof MultiOptionsFieldData) {
            /** @phpstan-ignore-next-line */
            $value = array_map(fn(OptionData $option) => $option->value, (array)$value);
        } elseif ($value instanceof SingleOptionFieldData) {
            $value = $value->value;
        }

        return $this->matchValue($value);
    }
}
