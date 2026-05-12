## Why

Session 0 left a `pulsepress_db_version='0'` placeholder option on activation but no tables to back it. Every later session (reaction REST writes, count reads, email capture, aggregation, dashboard) reads or writes one of three custom tables. Without schema, nothing else can land — Session 2's reaction endpoint has nowhere to insert, the widget has nothing to count, and the dashboard has nothing to query.

This change creates the storage floor exactly once: three tables (`pulsepress_reactions`, `pulsepress_captures`, `pulsepress_daily_agg`), the indexes the obvious read patterns need, and a small migration runner that compares an in-code schema version against the stored `pulsepress_db_version` option. The runner is idempotent — running it twice in the same release does nothing the second time — so it is safe to call on every `plugins_loaded` after a guard, on activation, and from a future WP-CLI command. Free remains complete: the schema is enough to ship Session 2 through Session 10 (REST, widget, capture, settings, aggregation, dashboard, CSV) without another schema change.

## What Changes

- Create `pulsepress_reactions` storing one row per `(post_id, user_hash)`: `id` (BIGINT UNSIGNED PK), `post_id`, `reaction_type` (VARCHAR(32)), `user_hash` (CHAR(64) HMAC-SHA256 hex), `created_at`, `updated_at`. Unique key on `(post_id, user_hash)` enforces the replacement semantics confirmed in `docs/gap-questions-and-session-tasks.md` (gap 6).
- Create `pulsepress_captures` storing consented email captures: `id`, `post_id`, `email`, `reaction_type`, `consent` (TINYINT), `consent_text_version` (VARCHAR(32)), `consent_at` (DATETIME), `source` (VARCHAR(32)), `ip_hash` (CHAR(64) nullable), `user_agent_hash` (CHAR(64) nullable), `fraud_metadata_purge_at` (DATETIME) for the 30-day fraud-review purge committed in gap 3, plus `created_at`/`updated_at`. Unique key on `(email, post_id)`.
- Create `pulsepress_daily_agg` storing pre-aggregated counts: `id`, `agg_date` (DATE), `post_id`, `reaction_type`, `count` (INT UNSIGNED), `updated_at`. Unique key on `(agg_date, post_id, reaction_type)` so daily upserts replace rather than append.
- Add `PulsePress\Database\Schema` declaring all current CREATE TABLE SQL keyed by table name, with a top-level `Schema::VERSION` integer that the migration runner compares against `pulsepress_db_version`.
- Add `PulsePress\Database\Migrator` that walks `Schema::tables()`, runs each statement through `dbDelta`, and writes `Schema::VERSION` back to `pulsepress_db_version` only when every statement succeeds.
- Add `PulsePress\Providers\DatabaseServiceProvider` registered from `AppServiceProvider`, which runs `Migrator::migrate()` on `plugins_loaded` only when the stored version is below `Schema::VERSION` (cheap option read, no work after first request post-upgrade).
- Update `pulsepress.php` activation hook to invoke `Migrator::migrate()` after Composer autoload so fresh installs get schema immediately, not on the next request.
- Reserve two option keys with documented defaults but no settings UI yet: `pulsepress_delete_on_uninstall` (default `false`) and `pulsepress_retention_days` (default `0` meaning "indefinite"). Both will be exposed through the settings page in Session 6.
- Add `uninstall.php` at the plugin root that respects `pulsepress_delete_on_uninstall`: when `true`, drops the three tables and deletes plugin options; when `false`, deletes nothing. This is the WordPress.org-compliant uninstall path.
- **BREAKING**: none — there is no prior schema to migrate from.

## Capabilities

### New Capabilities

- `database-schema`: defines the three custom tables, their indexes, the migration version contract (`Schema::VERSION` vs `pulsepress_db_version` option), idempotency guarantees, retention/uninstall option contracts, and the activation-hook responsibility.

### Modified Capabilities

- `plugin-bootstrap`: the activation hook now runs `Migrator::migrate()` after seeding `pulsepress_db_version`, so first-activation gets tables in place atomically. This refines the Session 0 contract.

## Impact

- **New files**: `app/Database/Schema.php`, `app/Database/Migrator.php`, `app/Providers/DatabaseServiceProvider.php`, `uninstall.php`, `tests/Unit/SchemaTest.php`, `tests/Unit/MigratorTest.php`.
- **Modified files**: `pulsepress.php` (activation hook calls migrator), `app/bootstrap.php` (registers `DatabaseServiceProvider`).
- **Database changes**: three new tables, all using `$wpdb->prefix` and `$wpdb->get_charset_collate()`. No data inserted in this slice.
- **Option keys introduced**: `pulsepress_db_version` (already exists, now mutated by Migrator), `pulsepress_delete_on_uninstall`, `pulsepress_retention_days`.
- **Dependencies introduced**: none — `dbDelta` and `$wpdb` are core WordPress.
- **Privacy**: `pulsepress_captures.ip_hash` and `user_agent_hash` are hashes, not raw values; `fraud_metadata_purge_at` enforces the 30-day commitment. No PII written in this slice — only schema.
- **Performance**: indexes target the obvious read patterns (count-by-post, count-by-post-by-reaction, list-by-date for aggregation). The `plugins_loaded` guard is a single `get_option` call after the first request post-upgrade.
- **Free/Pro boundary**: not touched. The same schema serves Free and Pro; Pro adds tables in its own addon when needed.
