## ADDED Requirements

### Requirement: Settings schema and defaults

`PulsePress\Settings\Settings` SHALL expose `public const DEFAULTS` mapping every settings key to its default value, and `public const SCHEMA_VERSION` as an integer that increments whenever a schema-breaking change ships. The default schema SHALL include at minimum: `count_visibility`, `count_threshold`, `widget_design`, `icon_style`, `theme_mode`, `auto_insert_post_types`, `auto_insert_position`, `positive_reactions`, `allow_guest_reactions`, `consent_text`, `consent_text_version`, `delete_on_uninstall`, `retention_days`.

#### Scenario: Defaults exposed as a constant

- **WHEN** a developer reads `\PulsePress\Settings\Settings::DEFAULTS`
- **THEN** the value is an associative array containing every declared settings key with its documented default

#### Scenario: Schema version constant is an integer

- **WHEN** a developer reads `Settings::SCHEMA_VERSION`
- **THEN** the value is an integer ≥ 1

### Requirement: SettingsRepository::get merges defaults, stored option, and filter

The repository SHALL return a complete settings array by starting from `Settings::DEFAULTS`, overlaying the stored `pulsepress_settings` option when present, and finally passing the merged result through `apply_filters('pulsepress_settings', $settings)`. Reads SHALL be memoised within a single PHP request so subsequent calls return the cached array.

#### Scenario: Fresh install with no option stored

- **WHEN** `get_option('pulsepress_settings')` returns `false` and the repository's `get()` is called
- **THEN** the result equals `Settings::DEFAULTS` after the filter pass

#### Scenario: Filter wins over stored value

- **WHEN** the stored option has `positive_reactions = ['love']` and a filter is registered with `add_filter('pulsepress_settings', fn($s) => array_merge($s, ['positive_reactions' => ['funny']]))`, then `get()` is called
- **THEN** the returned `positive_reactions` equals `['funny']`

#### Scenario: Memoisation avoids repeat DB hits

- **WHEN** `get()` is called twice in the same request
- **THEN** the `get_option` mock is invoked at most once

### Requirement: SettingsRepository::save sanitises, persists, fires action

The repository's `save(array $partial): array` SHALL pass the input through `Settings::sanitise` before merging it over the current settings, write the merged array (plus `_version => Settings::SCHEMA_VERSION`) into the `pulsepress_settings` option, update the in-request memo, and fire `do_action('pulsepress_settings_saved', $newSettings, $previousSettings)` exactly once after the write.

#### Scenario: Save persists and returns merged

- **WHEN** the repository saves `['icon_style' => 'emoji']` over an existing settings array
- **THEN** the stored option contains the previous values plus `icon_style => 'emoji'` and the returned array reflects the merge

#### Scenario: Action fires with both new and previous state

- **WHEN** a listener registers `add_action('pulsepress_settings_saved', $cb, 10, 2)` and a save runs
- **THEN** the callback is invoked once with `($new, $previous)` as a complete-state pair

#### Scenario: Unknown keys are dropped

- **WHEN** the input contains a key not in `Settings::DEFAULTS` (e.g. `evil_key => 'gotcha'`)
- **THEN** the saved option does not contain `evil_key` and the returned array does not contain it

### Requirement: Settings::sanitise enforces per-field validation

The sanitiser SHALL coerce out-of-range integers to the nearest valid value or default, reject string values not in the per-field allowlist (falling back to default), drop unknown keys, and ensure array-valued settings (`auto_insert_post_types`, `positive_reactions`) only contain expected string members.

#### Scenario: Out-of-range integer clamped

- **WHEN** input has `count_threshold = 99999`
- **THEN** the sanitised value is the field's documented maximum

#### Scenario: Negative integer falls back to default

- **WHEN** input has `count_threshold = -1`
- **THEN** the sanitised value equals `Settings::DEFAULTS['count_threshold']`

#### Scenario: Unknown enum value drops to default

- **WHEN** input has `icon_style = 'flat'`
- **THEN** the sanitised value equals `Settings::DEFAULTS['icon_style']`

#### Scenario: Positive reactions filtered to allowlist

- **WHEN** input has `positive_reactions = ['love', 'celebrate', 'angry']`
- **THEN** the sanitised value is `['love', 'angry']` (each member is checked against `Reactions::TYPES` after filter)

### Requirement: GET /pulsepress/v1/settings returns settings, defaults, and choices

The endpoint SHALL respond with a JSON object containing three top-level keys: `settings` (current effective settings array), `defaults` (`Settings::DEFAULTS`), and `choices` (an associative array mapping enumerable setting names to the array of allowed values). The endpoint SHALL require `current_user_can('manage_options')`.

#### Scenario: Admin reads settings

- **WHEN** an admin sends `GET /wp-json/pulsepress/v1/settings`
- **THEN** the response is `200` with `settings`, `defaults`, and `choices` keys; `choices.icon_style` equals `['classic', 'emoji']`

#### Scenario: Non-admin is rejected

- **WHEN** an unauthenticated visitor or non-admin user makes the same request
- **THEN** the response is `403` and no settings data is returned

### Requirement: POST /pulsepress/v1/settings sanitises and saves

The endpoint SHALL accept a JSON body containing a subset of settings keys, run them through `Settings::sanitise`, merge over current settings via `SettingsRepository::save`, and return `200` with the new merged settings. Sanitisation failures that throw SHALL be caught and converted to `422` `pulsepress_settings_invalid` with a message naming the offending field. The endpoint SHALL require `current_user_can('manage_options')` and a valid REST nonce.

#### Scenario: Admin saves a partial update

- **WHEN** an admin POSTs `{"icon_style": "emoji"}`
- **THEN** the response is `200` with the new settings showing `icon_style: "emoji"` and `widget_design` (unchanged) still at its prior value

#### Scenario: Non-admin is rejected

- **WHEN** a non-admin POSTs a settings update
- **THEN** the response is `403` and the option is not modified

### Requirement: WordPress admin menu page registered

The plugin SHALL register a WordPress submenu under "Settings → PulsePress" via `add_options_page('PulsePress', 'PulsePress', 'manage_options', 'pulsepress', $callback)`. The callback SHALL output `<div class="wrap"><div id="pulsepress-admin">Loading…</div></div>` so Session 6b's SPA can mount into `#pulsepress-admin`.

#### Scenario: Menu appears for admins

- **WHEN** an admin loads `/wp-admin/options-general.php`
- **THEN** a submenu item labelled "PulsePress" exists linking to `/wp-admin/options-general.php?page=pulsepress`

#### Scenario: Page renders the SPA mount node

- **WHEN** an admin loads `/wp-admin/options-general.php?page=pulsepress`
- **THEN** the HTML contains `<div id="pulsepress-admin">Loading…</div>`

#### Scenario: Page is not visible to non-admins

- **WHEN** a non-admin loads `/wp-admin/`
- **THEN** the "PulsePress" submenu item does not appear

## MODIFIED Requirements

### Requirement: GET /pulsepress/v1/counts/{post_id} returns public per-type counts

The plugin SHALL register `GET /wp-json/pulsepress/v1/counts/(?P<post_id>\\d+)` as a public endpoint (`permission_callback: __return_true`). It SHALL return `{post_id, counts, cached}` where `counts` is always a JSON object keyed by reaction type (never `[]` when empty). The endpoint SHALL serve from a `pulsepress_counts_{post_id}` transient when present; on miss it SHALL execute a single grouped SELECT against `<prefix>pulsepress_reactions` and write the result into the transient with a 300-second TTL before returning. This endpoint remains public regardless of the `allow_guest_reactions` setting; only the WRITE endpoint is gated.

#### Scenario: Empty counts serialise as JSON object

- **WHEN** post 42 has zero rows in the reactions table
- **THEN** the response body contains `"counts":{}` and not `"counts":[]`

#### Scenario: Public read works even when guest reactions are disabled

- **WHEN** `allow_guest_reactions` is `false` and an anonymous visitor requests counts
- **THEN** the response is `200` — the read endpoint is unaffected by the guest toggle
