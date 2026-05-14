# Hooks And Filters

Catalog of every PulsePress action and filter, kept in sync with the code as Sessions land. New code MUST add an entry here when introducing a hook.

## Filters

| Hook | Args | Default | Introduced | Purpose |
| --- | --- | --- | --- | --- |
| `pulsepress_reaction_types` | `(array $types)` | `['love', 'insightful', 'funny', 'sad', 'surprised', 'angry']` | Session 2 | Override the reaction allowlist. Must return an array; non-array falls back to default. |
| `pulsepress_client_ip` | `(string $remoteAddr, WP_REST_Request $request)` | `$_SERVER['REMOTE_ADDR']` | Session 2 | CDN/proxy override for the IP used in the dedup hash. |
| `pulsepress_widget_enqueue` | `(bool $force)` | `false` | Session 3 | Force-enqueue the widget assets on non-singular-post views. |
| `pulsepress_widget_auto_insert` | `(bool $default, string $postType)` | `true` for `post`, `false` otherwise | Session 3 | Auto-append the widget container to `the_content` for a given post type. |
| `pulsepress_widget_data` | `(array $payload)` | `{root, nonce, postId, reactions, i18n}` | Session 3 | Adjust the `window.PulsePressData` payload before `wp_localize_script`. |
| `pulsepress_widget_icons` | `(array $iconMap, string $preset)` | Classic SVG map | Session 6.5 (planned) | Override the icon set for a preset; lets Pro add presets without code. |
| `pulsepress_capture_sources` | `(string[] $sources)` | `['inline', 'block', 'shortcode']` | Session 4 | Extend or restrict the allowed `source` values on `POST /capture`. |
| `pulsepress_consent_text_version` | `(string $version)` | `'v1'` | Session 4 | The consent-text version stamp written into every new capture row. Existing rows are not retroactively updated. |
| `pulsepress_capture_email` | `(string $normalisedEmail, WP_REST_Request $request)` | already lowercased + trimmed input | Session 4 | Transform the email before validation/storage (e.g. strip `+tag` aliases). Receives the already-normalised email. |
| `pulsepress_positive_reactions` | `(string[] $types)` | `['love', 'insightful', 'funny']` | Session 5 | Which reaction types trigger the inline capture form on the front end. Empty array disables inline capture entirely. |
| `pulsepress_settings` | `(array $settings)` | `Settings::DEFAULTS` merged with stored option | Session 6 | Final filter pass on the full settings array. Pro can layer extra fields here; admin-saved values still take precedence over defaults but lose to this filter. |
| `pulsepress_settings_default` | `(array $defaults)` | `Settings::DEFAULTS` | Session 6 | Override the defaults map (e.g., to pre-seed a different positive set on first install). |
| `pulsepress_admin_data` | `(array $payload)` | `{restRoot, nonce, settings, defaults, choices, schemaVersion, reactions, version, i18n}` | Session 6b | Adjust the `window.PulsePressAdminData` payload on the admin settings page. Pro layers license-key context, etc. |
| `pulsepress_widget_container_attrs` | `(array $attrs, int $postId)` | `{'class' => 'pulsepress', 'data-pulsepress-widget' => '', 'data-pulsepress-post-id' => '{id}'}` | Session 7 | Adjust the HTML attributes on the widget container `<div>` before it is serialised. Empty-string values render as bare attributes. |
| `pulsepress_aggregation_date` | `(DateTimeImmutable $date)` | yesterday in `wp_timezone()` | Session 8 | Override the date the daily aggregator processes on each cron tick. Useful for one-off rebuild scripts. |
| `pulsepress_aggregation_timezone` | `(DateTimeZone $tz)` | `wp_timezone()` | Session 8 | Override the timezone used to compute site-local day boundaries during aggregation. Rarely needed. |
| `pulsepress_analytics_window` | `(array $window, WP_REST_Request $request)` | `{'from' => DateTimeImmutable (UTC), 'to' => DateTimeImmutable (UTC), 'clamped' => bool}` | Session 9 | Final hook on the analytics window bounds. Pro can rewrite the window before the metrics calculator runs. |
| `pulsepress_analytics_max_days` | `(int $maxDays)` | `730` | Session 9.5 | Performance ceiling for one synchronous analytics request. Same value for every install — not a Free/Pro gate. Pro raises it via pre-aggregated rollups (which sidestep the limit entirely). |
| `pulsepress_admin_analytics_panels` | `(array $panels)` | `[]` | Session 9 (Pro extension seam) | Pro registers extra Analytics panels (compare-windows, A/B winner, per-segment) alongside the Free four. SPA renders any panel with `{id, title, data, render_js}`. |
| `pulsepress_admin_metric_cards` | `(array $cards)` | `[]` | Session 9.5 (Pro extension seam) | Pro appends additional metric cards to the Analytics grid (e.g. "vs previous 30 days"). |
| `pulsepress_export_columns` | `(array $columns)` | 8-column default map | Session 10 | Pro adds export columns (ESP sync status, last synced at). Invalid entries skipped with a debug log. |

## Actions

| Hook | Args | Introduced | Purpose |
| --- | --- | --- | --- |
| `pulsepress_before_react` | `(int $postId, string $reactionType, string $userHash, WP_REST_Request $request)` | Session 2 | Pre-write extension point. A handler MAY throw `PulsePress\Http\RestException` to abort the write with a `WP_Error`. |
| `pulsepress_after_react` | `(int $postId, string $reactionType, string $userHash, string $status)` | Session 2 | Post-write extension point. `$status` is `'inserted'` or `'updated'`. Aggregators, webhooks, ESP sync attach here. |
| `pulsepress_before_capture` | `(int $postId, string $email, string $reactionType, WP_REST_Request $request)` | Session 4 | Pre-store hook for the capture endpoint. Throw `RestException` to short-circuit with a `WP_Error`. |
| `pulsepress_after_capture` | `(int $captureId, int $postId, string $email, string $reactionType, string $consentVersion)` | Session 4 | Post-store hook. Fires only on `'inserted'` (not on `'already_exists'`). ESP sync, double opt-in mail, webhooks attach here. |
| `pulsepress_purge_fraud_metadata` | `()` | Session 4 (WP-Cron event) | Daily cron event that runs `FraudPurger::run()` to null hashes whose `fraud_metadata_purge_at` has passed. Hookable, but the default handler is registered in `CaptureServiceProvider::boot()`. |
| `pulsepress_settings_saved` | `(array $new, array $previous)` | Session 6 | Fires after the settings repository persists changes. ESP credential sync, telemetry, cache invalidation attach here. |
| `pulsepress_after_aggregate` | `(AggregationResult $result)` | Session 8 | Fires after every successful daily aggregation (including zero-row days). Pro hooks attach for "top post" notifications, ESP digests, etc. Skipped when aggregation fails. |
| `pulsepress_aggregate_reactions` | `()` | Session 8 (WP-Cron event) | Daily cron that computes yesterday's site-local date and runs the aggregator. The default handler lives in `AnalyticsServiceProvider::boot()`. |

## Naming Conventions

- Filters: `pulsepress_<thing>` returning a value. Always include enough context args to be useful without a follow-up query.
- Actions: `pulsepress_<noun>_<verb>` for past-tense events; `pulsepress_before_<noun>` / `pulsepress_after_<noun>` when a write surrounds an extension point.
- Prefix every hook with `pulsepress_` — no exceptions, no abbreviations.

## Stability

- Once a hook ships in a released version, its name, arg list, and arg order are **stable**. Adding a new arg at the end is non-breaking; reordering or renaming is a major version bump.
- Removing a hook requires a minimum of one minor-version deprecation cycle with a `_doing_it_wrong` notice.
- Pro and 3rd-party integrations rely on this catalog as the contract.
