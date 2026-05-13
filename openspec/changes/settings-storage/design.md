## Context

Up to Session 5, every PulsePress behaviour an admin might want to change lives behind a `apply_filters(...)` call. That's a power-user model. To match the WordPress.org distribution bar and the "best WP-ecosystem plugin" admin direction recently codified in the plan, we need a real settings page.

That settings page is a Preact SPA (Session 6b). This slice (6a) is everything underneath it: the option schema, the REST contract, the sanitiser, the read/write repository, and the wiring that lets the rest of the codebase consult settings without breaking the existing filter contract.

The dominant constraint here is **backward compatibility with the filter surface we already shipped**. A site with `add_filter('pulsepress_positive_reactions', ...)` in `functions.php` today must keep working after Session 6 lands. That means: settings are read first, then passed through the same filter, so a filter still wins. The filter is now layered on top of admin-driven defaults rather than replacing them.

## Goals / Non-Goals

**Goals:**

- One canonical settings array under the `pulsepress_settings` option.
- Server-side sanitisation that *never* trusts the client; the REST controller calls `Settings::sanitise` on every input.
- A versioned schema (`Settings::SCHEMA_VERSION` constant) so future changes can migrate forward.
- REST endpoints that the SPA consumes and that Pro can extend through the `pulsepress_settings` filter.
- Every legacy behaviour that was filter-driven continues to work; filters now sit *on top of* settings, not under them.
- Tests covering: sanitisation edge cases (out-of-range, unknown keys, wrong types), filter precedence, repository memoisation, action hook firing.

**Non-Goals:**

- No admin SPA — that's Session 6b. This slice ships only a placeholder page with the mount node.
- No migration of existing `pulsepress_delete_on_uninstall` / `pulsepress_retention_days` legacy keys in this session. They keep working independently; a later cleanup session removes them once we're confident no one reads them directly.
- No multi-site / network-wide settings. Single-site per-install is sufficient for v1.
- No import/export of settings (that's a Session 12 packaging concern).
- No live PostHog/analytics telemetry on settings changes.

## Decisions

### D1. One option, JSON-serialised array

`pulsepress_settings` is a single autoloaded option storing the full settings array. Reasons:

- Atomic reads (one DB query for everything).
- No `wp_options` proliferation — easier uninstall, easier backups.
- The shape is documented in code (`Settings::DEFAULTS`) rather than spread across N option names.

**Alternative considered**: one option per field. Rejected — adds DB roundtrips and makes "show me all settings" require a `wp_options` scan or a hardcoded list anyway.

### D2. `Settings::SCHEMA_VERSION` for future migrations

Single integer constant alongside DEFAULTS. Repository compares the stored `_version` field against `SCHEMA_VERSION` on every read; mismatch triggers `Settings::migrate($stored)` which today returns the input unchanged. A future schema change adds a switch case.

### D3. Sanitisation per field, declarative

```php
public static function sanitise(array $input): array {
    return [
        'count_visibility'      => self::oneOf($input['count_visibility'] ?? null, ['always', 'never', 'threshold'], 'always'),
        'count_threshold'       => self::intRange($input['count_threshold'] ?? null, 0, 1000, 5),
        'widget_design'         => self::oneOf($input['widget_design'] ?? null, ['minimal', 'expressive'], 'minimal'),
        'icon_style'            => self::oneOf($input['icon_style'] ?? null, ['classic', 'emoji'], 'classic'),
        // …
    ];
}
```

Each field has a sanitiser; unknown keys are silently dropped. The REST controller catches any thrown `InvalidArgumentException` (from `intRange` etc) and returns 422 with the offending field.

### D4. Filter sits ON TOP of settings, not under

```php
public function get(): array {
    if (isset($this->cached)) return $this->cached;
    $stored   = get_option('pulsepress_settings', null);
    $settings = is_array($stored) ? array_merge(self::DEFAULTS, $stored) : self::DEFAULTS;
    $settings = (array) apply_filters('pulsepress_settings', $settings);
    $this->cached = $settings;
    return $settings;
}
```

A site with `add_filter('pulsepress_settings', fn($s) => ['positive_reactions' => ['love']] + $s)` overrides the admin choice. This preserves filter-only sites' behaviour and gives Pro a clean extension seam.

### D5. Read-once memoisation

The repository caches its read inside one PHP request. Settings are read by ReactionController, CaptureController, WidgetServiceProvider, possibly multiple times. One option fetch is enough.

### D6. Settings save fires `pulsepress_settings_saved` after the write

```php
public function save(array $partial): array {
    $previous = $this->get();
    $merged   = array_merge($previous, Settings::sanitise($partial));
    update_option('pulsepress_settings', $merged + ['_version' => Settings::SCHEMA_VERSION], true);
    $this->cached = $merged;
    do_action('pulsepress_settings_saved', $merged, $previous);
    return $merged;
}
```

Pro and 3rd parties hook this for ESP credential sync, telemetry, etc. The action fires AFTER the write completes; exceptions in handlers do not roll back the save.

### D7. Single REST endpoint, two methods

`/wp-json/pulsepress/v1/settings` — `GET` returns `{settings, defaults, choices}`. `POST` (treated as upsert) accepts the partial settings to merge. `permission_callback => fn() => current_user_can('manage_options')`.

`choices` is included in the GET response so the SPA doesn't need a separate "what are valid values for icon_style" endpoint; it's just `{icon_style: ['classic', 'emoji'], widget_design: [...], theme_mode: [...]}`.

### D8. Front-end consumers read settings, then filter

- `Reactions::TYPES` stays as-is. The settings layer doesn't override the reaction allowlist; the filter does (Pro territory).
- `Reactions::DEFAULT_POSITIVE` stays as-is. `Settings::get()['positive_reactions']` is passed through `pulsepress_positive_reactions` so filter snippets still win.
- `WidgetServiceProvider::enqueueAssets` reads settings and passes `iconStyle`, `themeMode`, `widgetDesign`, `countVisibility`, `countThreshold` into `PulsePressData`.
- `ReactionController` permission_callback reads `allow_guest_reactions`; when `false`, also requires `is_user_logged_in()`.

### D9. Admin menu page is a one-line PHP stub for now

`add_options_page('PulsePress', 'PulsePress', 'manage_options', 'pulsepress', $callback)`. The callback outputs:

```php
<div class="wrap"><div id="pulsepress-admin">Loading…</div></div>
```

Session 6b enqueues the admin SPA bundle on this page only and mounts the Preact app into `#pulsepress-admin`. The "Loading…" text is the SR-friendly fallback for JS-disabled / failing.

### D10. Backward-compat shim for the two legacy option keys

`Settings::get()['delete_on_uninstall']` falls back to `get_option('pulsepress_delete_on_uninstall', '0') === '1'` when the new settings array doesn't have the key yet. Same for `retention_days`. On first save, the new settings array picks up these values and the shim becomes a no-op.

## Risks / Trade-offs

- **Risk**: a settings save during a long-running request leaves a stale memoised cache in the running process. → Mitigation: `save()` updates `$this->cached` in-place, so subsequent reads in the same request see the new state. Multi-process staleness is irrelevant (each request memoises its own).
- **Risk**: an admin saves bad JSON (broken extension). → Mitigation: REST validation rejects malformed bodies at the framework layer; sanitiser drops unknown keys; broken values fall back to defaults.
- **Risk**: an autoloaded option that grows large slows every WP request. → Mitigation: settings are bounded to ~2 KB by the schema's hard limits (consent_text is the largest at 2000 chars).
- **Risk**: filter-driven sites accidentally lose customisations when the settings page is saved with stale UI state. → Mitigation: filter still wins after the save; SPA reads via the same `get()` and shows the filter-modified values, so admins see what they actually have.
- **Risk**: `manage_options` admins on a multi-site network see settings of the site they're on. → Acceptable; multi-site-wide settings deferred.
- **Trade-off**: no per-post-type override matrix in this slice. Auto-insert is "checked post types" → boolean for each. Per-post-type position (above vs below per CPT) deferred until needed.

## Migration Plan

No data migration. First read returns DEFAULTS; first save writes the option. Existing `pulsepress_delete_on_uninstall` / `pulsepress_retention_days` are read through the shim until the new option exists.

Rollback is `git revert` + `delete_option('pulsepress_settings')` — the legacy keys are still in place from Session 1.

## Open Questions

- **Q1**: should we send the full settings array down to the front-end widget, or only the bits the widget needs? → **Decided: only the widget-relevant slice**, to keep the widget bundle's `PulsePressData` small and avoid leaking admin-only fields (consent text version, retention policy) to anonymous visitors.
- **Q2**: should the consent text be HTML-allowed? → **Decided: plain text only**. HTML is an XSS vector and the form is plain text anyway. Pro can layer rich consent in its own field if needed.
- **Q3**: should the REST endpoint also offer `DELETE /settings` to reset to defaults? → **Deferred to Session 6b**. The SPA's "Reset to defaults" button can POST the defaults — no separate verb needed.
