# MetaSettings - Craft CMS Custom Field

MetaSettings is a JSON configured, custom field type plugin for Craft CMS 5+. It field presents like a standard Craft CMS [Dropdown Field](https://craftcms.com/docs/5.x/reference/field-types/dropdown.html) which itself makes use of the [Selectize JS library](https://selectize.dev/).

Hiding under the hood of MetaSettings are a bunch of features aimed at improving the overall content [Author Experience (AX)](https://www.amazon.com/Author-Experience-Bridging-technology-management/dp/1937434427) and developer templating experience in Twig.

![](https://i.imgur.com/YUtManY.png)


    **This is a Beta Release!**


## Overview

- Configurable **Inline Tooptips** and **Help/Documentation Modal** for improving author experience.
- Definable **Virtual Input Fields** that are only displayed via the `Settings` button next to the Dropdown field. Progressive disclosure FTW!
   - Supported field types include: Plain Text, Number, Radio Group, Lightswitch, Dropdown/Select, Money, Color, Icon, Date, Time
- Each field option can have separate or shared virtual input fields.
- Each option can also have shared or unique **Settings Tokens** that are made accessible in the Twig templates in addition to the selected field values.
- Configure fields via `.json` files stored in the Craft CMS `templates` folder.
- Since the config files are still technically parsed as Twig files, you can get ~~silly~~ creative with advanced configurations.


![](https://i.imgur.com/Gi409Qe.png)

![](https://i.imgur.com/GtnbOPi.png)


## Installation

This early release version is not currently available on the Craft Plugin store, and can only be installed via Composer.

**Requires Craft CMS 5+**

Run `composer require "simplicateca/metasettings:^5.1" -w && php craft plugin/install metasettings` and then enable the plugin from "Settings > Plugins"


composer require "vaersaagod/seomate:^3.1.1" -w && php craft plugin/install seomate


## Configuration

Options for each MetaSettings field are stored in a JSON file within the Craft CMS `templates` folder rather than requiring them to be input via the Craft CMS Control Panel.

That means, when you want to add, change, or remove options from a Dropdown, you can do so by editing a JSON file in your code editor, rather than having to go into the Control Panel and edit or re-save individual fields.

This is particularly useful for fields that are used across many Matrix Block types, reducing the need to update the same options in many places, and reducing the risk of human error.


### Sample `.json` Files

Sample JSON config files can be found in the plugins ['src/templates/samples' folder](https://github.com/simplicateca/craft-meta-settings-field/tree/main/src/templates/samples/config).

Additionally, each file in that folder can be used in dev mode to demonstrate the fields capabilities and functionality:

 - `_metasettings/config/simple.json`
 - `_metasettings/config/all-inputs.json`
 - `_metasettings/config/button-only.json`


## Twig Templating

Each option in a MetaSettings field allows for additional data to be associated with it that you can use in place of creating logic in Twig to match the value of a dropdown field.

Within the JSON configuration for a field, you can have an attribute called `settings` that allows for any number of key-value pairs (strings, arrays, etc) to be passed along into Twig.

For example, if you had a MetaSettings field `fieldName` with this configuration:

```
{
    "label": "Option One",
    "value": "one",
    "settings": {
        "usefulText": "This is some useful text"
    }
}
```

You could then reference the **settings.usefulText** in your Twig templates like: `{{ entry.fieldName.usefulText }}`

This of course would only work if you "Option One" was selected in the `fieldName` field. If a different option were selected, the settings variables and their contents might be different (that's up to you!).

Why would this ever be useful? Plenty of reasons!

    Explain With Examples...


### Instant Updates

Because changes to the JSON file are immediately reflected in all instances of the field, you can use this to easily tweak any number of settings across many templates or entires without having to go into the Control Panel and edit/resave individual records.


## Inline Documentation

While Craft CMS allows for us to associate instructions with each field, these only apply to the field as a whole, and not to individual option selected within a Dropdown.

MetaSettings fields allow for the creation of inline documentation for each option, which can be accessed via tooltips, modals, or links to external resources.

Modal content is loaded from a Twig file of your choice, and the Twig template can make use of
dynamic information about the field and selected option to decide what kind of documentation to
display.

    TODO: Outline Different documentation settings in the JSON config


## Virtual Input Fields

Like a regular Dropdown field, the MetaSettings field stores the value of the `<option>` selected by the user. However, it can also be used to store multiple additional inputs fields associated with each option.

This can be useful for situations where the selected option has fine-tuning "follow-up questions", especially if the extra inputs are presentational rather than content model in nature, and likely don't warrant their own [Custom Field](https://craftcms.com/docs/5.x/system/fields.html).

Nobody likes a cluttered content model, and this can help keep things tidy.

### Field Configuration

The configuration structure used for the virtual field config is identical to the one used by the Craft core `_includes/forms.twig` file. In fact, most of the fields are directly generated by that.

### Available Field Types

Available input fields include:

- Plain Text
- Number
- Radio Group
- Lightswitch
- Dropdown/Select
- Money
- Color
- Icon
- Date
- Time

See [all-inputs.json](https://github.com/simplicateca/craft-meta-settings-field/blob/main/src/templates/samples/config/all-inputs.json) for an example of how to configure each of these fields.


## Dynamic Configuration

MetaSettings only suggests `.json` files when attempting to autocomplete the file path within the field configuration screen.

However, MetaSettings it is happy to accept any file type that can be parsed as a Twig template provided:

1) You enter the file path manually without the aid of autocomplete (the horror!)
2) The file outputs a valid JSON array of options

MetaSettings provides an `element` variable which can be accessed in any Twig tags or commands from within any MetaSettings config file (regardless of file extension).

This `element` variable contains information about the element that the field is attached to, as well as that elements' owner (if applicable as in cases of matrix fields).

These are the fields that are available on the `element` variable:

`element.section` - The handle for the [Section](https://craftcms.com/docs/5.x/reference/element-types/entries.html#sections) associated with the direct element that the field is attached to (if applicable).

`element.type` - The handle for the [Entry Type](https://craftcms.com/docs/5.x/reference/element-types/entries.html#entry-types) associated with the direct element that the field is attached to (if applicable).

Additionally, for cases where the MetaSettings field is attached to a Matrix block, the `element` variable will also contain an `owner` variable which contains the same fields as the `element` variable, but for the owner of the Matrix block.

This means that instead of configuring a MetaSettings field with a static `.json` file, you may consider some of the following techniques.


### Configuration with `.twig` instead of `.json`

**field-config.twig**

```
{% set fieldOptions = [
    {
        "label": "Option One",
        "value": "one",
        "settings": {
            "template" : "_layouts/basic.twig",
            "fizz": true
        }
    },{
        "label": "Option Two",
        "value": "two",
        "settings": {
            "template" : "_layouts/complicated.twig",
            "fizz": false
        }
    }] %}

{{ fieldOptions | json_encode }}
```

### Configuration with Twig inside `.json`

e.g. **field-config.json**

```
[
    {
        "label": "Option One",
        "value": "one",
        "settings": {
            "template" : "_layouts/basic.twig",
            "fizz": true
        }
    }
    {% if element.section != 'blog' %}
        ,{
            "label": "Option Two",
            "value": "two",
            "settings": {
                "template" : "_layouts/complicated.twig",
                "fizz": false
            }
        }
    {% endif %}
]
```

### Other ~~less sane~~ adventureous methods of configuration

Using `.twig` as a router that points to regular static `.json` files using the Twig [source function](https://twig.symfony.com/doc/3.x/functions/source.html)

e.g. **layout-field-router.twig**

```
{% set section = element.owner.section ?? element.section ?? 'default' %}
{{ source("_config/layout-#{section}.json") }}
```

----

Alternatively, you could use a `.json` file that includes common elements from a `.twig` file using the Twig [block function](https://twig.symfony.com/doc/3.x/functions/block.html)

e.g. **field-config.json**

```
[
    {
        "label": "Option One",
        "value": "one",
        "virtuals": [
            {{ block( 'align', '_config/field-inputs.twig' ) }},
            {{ block( 'size',  '_config/field-inputs.twig' ) }},
        ]
    }
   ,{
        "label": "Option Two",
        "value": "two",
        "virtuals": [
            {{ block( 'align', '_config/field-inputs.twig' ) }},
            {{ block( 'size',  '_config/field-inputs.twig' ) }},
        ]
    }
]
```

.. and **field-inputs.twig**

```
{% block align %}
    {
        "label": "Alignment",
        "name" : "align",
        "type" : "select",
        "value": "text-left",
        "options": [
            "text-left"  : "Left",
            "text-center": "Center",
            "text-right" : "Right"
        ]
    }
{% endblock %}

{% block size %}
    {
        "label": "Font Size",
        "name" : "fontSize",
        "type" : "select",
        "value": "text-base",
        "options": [
            "text-xs"  : "Extra Small",
            "text-sm"  : "Small",
            "text-base": "Normal",
            "text-lg"  : "Large",
            "text-xl"  : "Extra Large"
        ]
    }
{% endblock %}
```

## Element Variables

```
    'name'    => $element->type->name    ?? null,
    'type'    => $element->type->handle  ?? null,
    'field'   => $element->field->handle ?? null,
    'element' => $element->elementType   ?? null,
    'owner'   => [
        'site'    => $owner->site->handle    ?? null,
        'type'    => $owner->type->handle    ?? null,
        'level'   => $owner->level           ?? null,
        'section' => $owner->section->handle ?? null,
        'element' => $owner->elementType     ?? null,
    ],
```



## Button-Only mode

It is also possible to tell a MetaSettings field to operate in **Button Only** mode where if can be configured with a single "option" with multiple virtual input fields which can be accessed by clicking on a button.

The button uses the `label` from the single `<option>` as its text.

Additionally, to differentiate between regular Select fields with one option and button-only fields `"type":"button"` must be included in the JSON config for the field like so:

```
[{
    "label": "Button Only Field Name",
    "value": "anything-really",
    "type" : "button",
    "tooltips": {...},
    "settings": {...},
    "virtuals": [{...}, {...}, {...}],
}]
```

It is not necessary to include a value for the `type` key for non-button-only fields.


## Credits

Brought to you by  [simplicate.ca](https://simplicate.ca)

Thanks to the [Design Tokens](https://plugins.craftcms.com/designtokens?craft5) field by Trendy Minds and the [Matrix Field Preview](https://plugins.craftcms.com/matrix-field-preview?craft5) plugin by Feral for inspiration.

[Bug reports welcome](https://github.com/simplicateca/craft-meta-settings-field/issues).
