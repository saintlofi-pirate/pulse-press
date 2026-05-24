## Why

Sessions 0–5 give us a working free product with reactions, captures, and an inline form — but the only way to tune any of it today is to write a PHP filter snippet. That's fine for developer audiences and unacceptable for site owners. Session 6 introduces a real settings page; this slice (6a) is the storage and REST contract that page sits on. Without it, the admin SPA (6b) has nothing to read or write.

Free remains generous: every setting that today defaults via filter (positive reactions, auto-insert post types, widget enqueue gating, consent text version) becomes admin-editable with sensible defaults. The hook surface stays unchanged — filters still win, so existing PHP customisations keep working. Privacy stays first-class: every option key is namespaced `moonfarmer_reactions_lead_capture_*`, sanitisation rejects unexpected keys, and the REST endpoint requires `manage_options`.

## What Changes

- Add `Moonfarmer\ReactionsLeadCapture\Settings\Settings` final class holding the canonical schema: a public `const DEFAULTS` map keyed by setting name, plus per-setting sanitiser closures in `Settings::sanitise(array $input): array`. Setting keys:
  - `count_visibility` (string, one of `always` / `never` / `threshold`, default `always`)
  - `count_threshold` (int 0–1000, default 5)
  - `widget_design` (string, one of `minimal` / `expressive`, default `minimal`)
  - `icon_style` (string, one of `classic` / `emoji`, default `classic`)
  - `theme_mode` (string, one of `light` / `dark` / `auto`, default `auto`)
  - `auto_insert_post_types` (string[], default `['post']`)
  - `auto_insert_position` (string, one of `above` / `below` / `both`, default `below`)
  - `positive_reactions` (string[], subset of Reactions::TYPES, default `Reactions::DEFAULT_POSITIVE`)
  - `allow_guest_reactions` (bool, default `true`)
  - `consent_text` (string, max 2000 chars, default English copy from Session 5 i18n)
  - `consent_text_version` (string, max 32 chars, default `v1`)
  - `delete_on_uninstall` (bool, default `false` — mirrors existing option from Session 1)
  - `retention_days` (int 0–3650, default `0` — `0` means indefinite)
- Add `Moonfarmer\ReactionsLeadCapture\Settings\SettingsRepository` with `get(): array` (merging stored option over `Settings::DEFAULTS` after passing through `apply_filters('moonfarmer_reactions_lead_capture_settings', $settings)`) and `save(array $partial): array` (sanitises, merges over current, writes the option, fires `do_action('moonfarmer_reactions_lead_capture_settings_saved', $newSettings, $previousSettings)`, returns the new full settings).
- Add `Moonfarmer\ReactionsLeadCapture\Http\Controllers\SettingsController` with `read(\WP_REST_Request $request)` (GET, returns `{settings, defaults, choices}` so the SPA knows the allowed values for every selector) and `update(\WP_REST_Request $request)` (PUT/PATCH, sanitises the body via `Settings::sanitise`, calls `$repository->save($input)`, returns the merged result).
- Add `Moonfarmer\ReactionsLeadCapture\Providers\SettingsServiceProvider` that registers DI bindings and registers `/wp-json/moonfarmer-reactions-lead-capture/v1/settings` (GET + POST) on `rest_api_init` with `permission_callback => current_user_can('manage_options')` and a JSON-Schema `args` for every input field. Provider also registers `admin_menu` action to add the WordPress submenu under "Settings → Moonfarmer Reactions Lead Capture" — Session 6b will mount the SPA into the registered page, this session ships the placeholder page that simply outputs `<div id="moonfarmer-reactions-lead-capture-admin"></div>` plus a "Loading…" notice.
- Add `moonfarmer_reactions_lead_capture_settings_default` filter (runs once on first `get()` if no option exists) and `moonfarmer_reactions_lead_capture_settings_saved` action (after every successful write).
- Wire existing front-end paths to read from `SettingsRepository`:
  - `moonfarmer_reactions_lead_capture_positive_reactions` filter now returns the saved `positive_reactions` (filter still applies on top for code-level overrides).
  - `moonfarmer_reactions_lead_capture_widget_auto_insert` filter now consults `auto_insert_post_types`.
  - `WidgetServiceProvider::enqueueAssets` reads `icon_style` and `theme_mode` and surfaces them into `MoonfarmerReactionsLeadCaptureData`.
  - `ReactionController::react` permission_callback consults `allow_guest_reactions` — when false AND the request is unauthenticated, returns 401 `moonfarmer_reactions_lead_capture_login_required`.
  - `Captures::consentTextVersion` reads the stored version unless the filter overrides.
- **BREAKING**: none. Filters still take precedence; settings are read-then-filtered. A site relying purely on filter snippets continues to work.

## Capabilities

### New Capabilities

- `settings-api`: defines the REST contract — schema, defaults, allowed values, sanitisation behaviour, permission gating, the save action hook, and the WordPress admin menu registration that Session 6b's SPA will mount onto.

### Modified Capabilities

- `reaction-api`: the `/react` permission_callback now also consults `allow_guest_reactions` and rejects unauthenticated requests with 401 when the toggle is off.
- `reaction-widget`: the localised `MoonfarmerReactionsLeadCaptureData` payload gains `iconStyle` and `themeMode` derived from settings. The auto-insert decision and positive-reactions list draw from settings before the filter pass.

## Impact

- **New files**: `app/Settings/Settings.php`, `app/Settings/SettingsRepository.php`, `app/Http/Controllers/SettingsController.php`, `app/Providers/SettingsServiceProvider.php`, `tests/Unit/SettingsTest.php`, `tests/Unit/SettingsRepositoryTest.php`.
- **Modified files**: `app/Providers/RestServiceProvider.php` (react permission_callback consults guest-reactions toggle), `app/Providers/WidgetServiceProvider.php` (payload reads settings), `app/Reactions/Reactions.php` (no change to constants — the settings layer reads them), `app/Captures/Captures.php` (consentTextVersion checks settings before its default-then-filter chain), `app/bootstrap.php` (registers `SettingsServiceProvider`), `docs/hooks-and-filters.md` (adds `moonfarmer_reactions_lead_capture_settings`, `moonfarmer_reactions_lead_capture_settings_default`, `moonfarmer_reactions_lead_capture_settings_saved`).
- **Option keys introduced**: `moonfarmer_reactions_lead_capture_settings` (autoloaded). Existing `moonfarmer_reactions_lead_capture_delete_on_uninstall` and `moonfarmer_reactions_lead_capture_retention_days` are mirrored under the new settings array; the legacy keys keep working for backward compat and migrate automatically on first save.
- **REST endpoints**: `/wp-json/moonfarmer-reactions-lead-capture/v1/settings` — `GET` (read) + `POST` (update). Requires `manage_options`.
- **Filters introduced**: `moonfarmer_reactions_lead_capture_settings`, `moonfarmer_reactions_lead_capture_settings_default`.
- **Actions introduced**: `moonfarmer_reactions_lead_capture_settings_saved`.
- **Database changes**: none — single option row.
- **Privacy**: settings include privacy-related fields (consent text, retention days, guest toggle); only admins with `manage_options` can read or write them.
- **Performance**: settings read is one autoloaded option fetch; the repository memoises within a request so the price is paid once per request.
- **Free/Pro boundary**: settings reads pass through `apply_filters('moonfarmer_reactions_lead_capture_settings', ...)` so Pro can layer additional fields. Pro never modifies Settings::DEFAULTS directly.
