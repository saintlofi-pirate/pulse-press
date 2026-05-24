## 1. Settings schema and sanitiser

- [ ] 1.1 Create `app/Settings/Settings.php` with `final class` in namespace `Moonfarmer\ReactionsLeadCapture\Settings`. `public const SCHEMA_VERSION = 1`. `public const DEFAULTS` map with every setting key and default. `public const CHOICES` map for enumerable fields. Static `sanitise(array $input): array` that produces a complete sanitised settings array; unknown keys dropped; out-of-range clamped; invalid enums fall back to defaults.
- [ ] 1.2 Helper static methods inside `Settings`: `oneOf($value, array $allowed, $default)`, `intRange($value, int $min, int $max, int $default)`, `boolean($value, bool $default)`, `stringArray($value, array $allowed, array $default)`, `text($value, int $maxLength, string $default)`.

## 2. Repository

- [ ] 2.1 Create `app/Settings/SettingsRepository.php` with constructor `(int|null $schemaVersion = null)` (defaults to `Settings::SCHEMA_VERSION`). Properties: `private ?array $cached = null`.
- [ ] 2.2 `get(): array` reads `moonfarmer_reactions_lead_capture_settings`, falls back to defaults, merges, passes through `moonfarmer_reactions_lead_capture_settings` filter, memoises. Handles legacy keys (`moonfarmer_reactions_lead_capture_delete_on_uninstall`, `moonfarmer_reactions_lead_capture_retention_days`) when the new option is absent.
- [ ] 2.3 `save(array $partial): array` merges sanitised input over current, writes the option (with `_version`), updates the memo, fires `do_action('moonfarmer_reactions_lead_capture_settings_saved', $new, $previous)`, returns the new array.
- [ ] 2.4 `resetMemo(): void` for tests.

## 3. REST controller and provider

- [ ] 3.1 Create `app/Http/Controllers/SettingsController.php`. Constructor `(SettingsRepository $repository)`. Methods `read()` returning `{settings, defaults, choices}` and `update($request)` returning the merged result or `WP_Error` on sanitisation failure.
- [ ] 3.2 Create `app/Providers/SettingsServiceProvider.php`. Bindings in `register()`. In `boot()`:
  - Hook `rest_api_init` to register GET + POST `/settings` with `permission_callback => fn() => current_user_can('manage_options')`.
  - Hook `admin_menu` to register the submenu under Settings.
  - Hook `moonfarmer_reactions_lead_capture_positive_reactions` filter at priority 5 to seed from settings (lets user-registered filters at priority 10+ still override).
  - Hook `moonfarmer_reactions_lead_capture_widget_auto_insert` at priority 5 to honor `auto_insert_post_types`.
  - Hook `moonfarmer_reactions_lead_capture_consent_text_version` at priority 5 to read from settings.
- [ ] 3.3 Register `SettingsServiceProvider::class` in `app/bootstrap.php` BEFORE `RestServiceProvider` and `WidgetServiceProvider` so its filter handlers attach before downstream code reads.

## 4. Wire downstream consumers

- [ ] 4.1 `RestServiceProvider::boot()` — extend the react permission_callback: nonce check AND when `allow_guest_reactions` is false, also `is_user_logged_in()`. Return `WP_Error('moonfarmer_reactions_lead_capture_login_required', '…', ['status' => 401])` instead of `false` when guest reactions are off so the response code is meaningful.
- [ ] 4.2 `WidgetServiceProvider::enqueueAssets()` — read settings and add `iconStyle`, `themeMode`, `widgetDesign`, `countVisibility`, `countThreshold` to the localised payload. Always overrideable via `moonfarmer_reactions_lead_capture_widget_data` filter.
- [ ] 4.3 `Captures::consentTextVersion()` — try `SettingsRepository::get()['consent_text_version']` first; fall back to the existing filter+default chain.

## 5. Tests

- [ ] 5.1 `tests/Unit/SettingsTest.php`: sanitiser cases — every field type + out-of-range + unknown enum + unknown key drop + nested array filtering for positive_reactions.
- [ ] 5.2 `tests/Unit/SettingsRepositoryTest.php`: get returns defaults on empty; merge with stored; filter precedence; memoisation; save persists + fires action; sanitisation called from save.
- [ ] 5.3 Update `tests/Unit/BootstrapTest.php` autoload assertions for `Settings`, `SettingsRepository`, `SettingsController`, `SettingsServiceProvider`.
- [ ] 5.4 Run `composer test`; confirm green.

## 6. Manual verification

- [ ] 6.1 Reactivate plugin. `wp option get moonfarmer_reactions_lead_capture_settings` → absent (no first-save yet).
- [ ] 6.2 `curl -k -X GET -H "X-WP-Nonce: $NONCE" -u admin:admin "https://wp_lab.test/wp-json/moonfarmer-reactions-lead-capture/v1/settings"` (auth via cookies or app password) → confirm `{settings, defaults, choices}` returned with sensible defaults.
- [ ] 6.3 Unauthenticated GET → 403 with `rest_forbidden`.
- [ ] 6.4 POST `{"icon_style": "emoji"}` as admin → 200 + new settings showing the change.
- [ ] 6.5 POST `{"icon_style": "flat"}` → still 200, but the saved value is `classic` (sanitiser default) — confirm.
- [ ] 6.6 Reload single post view; `MoonfarmerReactionsLeadCaptureData.iconStyle === 'emoji'` in source view.
- [ ] 6.7 POST `{"allow_guest_reactions": false}`. Submit a reaction as anonymous → 401 `moonfarmer_reactions_lead_capture_login_required`.

## 7. Docs and final

- [ ] 7.1 Update `docs/hooks-and-filters.md`: add `moonfarmer_reactions_lead_capture_settings` filter, `moonfarmer_reactions_lead_capture_settings_default` filter, `moonfarmer_reactions_lead_capture_settings_saved` action.
- [ ] 7.2 Run `openspec validate settings-storage --strict --no-interactive` clean.
- [ ] 7.3 PHP lint clean.
- [ ] 7.4 Commit.
