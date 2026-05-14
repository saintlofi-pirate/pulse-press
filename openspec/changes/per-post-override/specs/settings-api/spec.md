## ADDED Requirements

### Requirement: hide_on_post_types setting

`Settings::DEFAULTS` SHALL include a `hide_on_post_types` key with default `[]`. The sanitiser SHALL accept a string array and (when possible) filter values to currently-registered public post-type slugs. The repository's `get()` SHALL surface the value in the merged settings array.

#### Scenario: Defaults include the empty list

- **WHEN** a fresh install reads `Settings::DEFAULTS['hide_on_post_types']`
- **THEN** it equals `[]`

#### Scenario: Sanitiser accepts a string array

- **WHEN** the sanitiser receives `['page', 'attachment']`
- **THEN** the result preserves those entries

#### Scenario: Sanitiser drops non-string members

- **WHEN** the sanitiser receives `['page', 123, false]`
- **THEN** the result is `['page']`

### Requirement: Settings response surfaces dynamic post types

`GET /pulsepress/v1/settings` response's `choices` SHALL include a `post_types` map (slug → label) produced by `get_post_types(['public' => true], 'objects')`. The admin SPA renders both "Auto-insert on" and "Never show on" against this map.

#### Scenario: Choices map present in response

- **WHEN** an admin reads the settings endpoint on a site that has registered a custom post type `recipe`
- **THEN** `choices.post_types.recipe` exists with a human-readable label

#### Scenario: Auto-insert validates against current public types

- **WHEN** the admin saves `auto_insert_post_types = ['recipe', 'banana']` where `banana` is not a public post type
- **THEN** the saved value contains `'recipe'` only

## MODIFIED Requirements

### Requirement: Settings schema and defaults

`PulsePress\Settings\Settings` SHALL expose `public const DEFAULTS` mapping every settings key to its default value, and `public const SCHEMA_VERSION` as an integer that increments whenever a schema-breaking change ships. The default schema SHALL include at minimum: `count_visibility`, `count_threshold`, `widget_design`, `icon_style`, `theme_mode`, `auto_insert_post_types`, `auto_insert_position`, `positive_reactions`, `allow_guest_reactions`, `consent_text`, `consent_text_version`, `delete_on_uninstall`, `retention_days`, **`hide_on_post_types`**.

#### Scenario: All keys exposed in defaults

- **WHEN** a developer reads `\PulsePress\Settings\Settings::DEFAULTS`
- **THEN** every documented key (including `hide_on_post_types`) is present
