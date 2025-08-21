<?php

namespace simplicateca\metasettings\fields\conditions;

use craft\fields\conditions\OptionsFieldConditionRule;

use simplicateca\metasettings\fields\MetaSettingsField;
use simplicateca\metasettings\fields\data\MetaSettingsDropdownData;


class MetaSettingsDropdownConditionRule extends OptionsFieldConditionRule
{
    /**
     * @inheritdoc
     */
    protected function matchFieldValue($value): bool
    {
        if (!$this->field() instanceof MetaSettingsField) {
            return true;
        }

        if ($value instanceof MetaSettingsDropdownData) {
            $value = $value->getValue();
        }

        return $this->matchValue($value);
    }
}
