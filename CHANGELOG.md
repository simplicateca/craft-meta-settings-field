# CHANGELOG

All notable changes to the Select Plus Custom Field Plugin for Craft CMS will be
documented in this file. This project adheres to [Semantic Versioning](http://semver.org/).

## [Version 5.1.0] - 2024-10-21

- Changed plugin name to 'MetaSettings' to better reflect its purpose vs. the field type it uses.

## [Version 5.0.5-beta] - 2024-08-20

- Fixed field namespaces bug
- Fixed bug where the virtual fields modal wasn't always properly updating internal fields.

## [Version 5.0.4-beta] - 2024-05-22

- Updated sample JSON files with more example fields
- Fixed Radio & Lightswitch fields reverting to default values under certain conditions.
- Fixed field naming error with Date, Time, and Money fields that was creating an illusion that the fields were reporting correct data.


## [Version 5.0.3-beta] - 2024-05-21

- Added 'Button Only' mode to present as a button instead of a Selectize dropdown (to still allow for hidden virtual input fields).
- Added the ability to toggle between Inputs & Help modals without first closing one (provided both exist).
- Added a sample Twig file to demonstrate dynamic *Help Modal* contents. Available using the path `_metasettings/help/sample.twig` for the `tooltips.helptwig` setting.
- Added help text to `templates/fields/settings.twig` that includes the paths to sample JSON config files which can be used for testing.
- Added a handful of additiona `fieldClass` widths for tweaking virtual field layouts.
- Added Markdown parsing within `tooltips` content (except `helpurl` and `helptwig` fields).
- Refactored sample JSON configs to include new field types, help settings and button-only mode.
- Refactored `...dist/js/MetaSettings.js` to make better use of Crafts Garnish library.
- Refactored `templates/fields/dropdown.twig` to streamline modal/tooltips/buttons.
- Fixed issue parsing current `json` values when evaluating transfer between primary field options.
- Fixed issue defaulting lightswitch fields to start 'on' (if not toggled off).
- Fixed issue maintaining proper thumbnail preview (between page reloads) for icon virtual fields.
- Removed requirement for `nystudio107/craft-emptycoalesce`.


## [Version 5.0.1-beta] - 2024-05-02

- Upgraded to Craft 5 support (from original unpublished Craft 4 version).
- Added new options for virtual input field: icon, time, date, money, color.