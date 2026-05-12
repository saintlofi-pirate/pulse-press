## Context

PulsePress has a scaffolded plugin (Session 0) but no storage. Three tables show up in every later session: `pulsepress_reactions` (writes from Session 2's REST endpoint, reads from Session 3's count fetch, source-of-truth for Session 8's aggregation), `pulsepress_captures` (writes from Session 4's capture endpoint, reads from Session 10's CSV export), and `pulsepress_daily_agg` (writes from Session 8's aggregation, reads from Session 9's dashboard). The data semantics that constrain the design are recorded in `docs/gap-questions-and-session-tasks.md`:

- **Gap 3 (retention)**: raw reactions indefinite by default with optional pruning; captures kept until manual delete/export; fraud-review IP metadata purged after 30 days; uninstall defaults to keep, with explicit opt-in to delete.
- **Gap 5 (hash)**: server soft deduplication uses HMAC-SHA256 over IP + UA with a server-side secret salt (not the WP nonce — nonce rotation would break dedup). The schema stores 64-char hex strings; the secret rotation policy lives outside the schema.
- **Gap 6 (replacement)**: duplicate reactions replace the prior reaction for that post/user; there is no historical event log in the free schema.
- **Gap 7 (timezone)**: daily aggregation uses the site's `wp_timezone()`. The schema stores `agg_date` as a SQL DATE; the aggregator (Session 8) is responsible for converting site-local day boundaries to UTC at read time.

The starter at `wp-plugin-matrx` uses a `wp_migrations` log table plus glob-loaded migration files keyed by timestamp. That pattern is fine for many migrations across many domains; for one plugin with a small, well-known schema, an in-code `Schema::VERSION` integer compared to a single option is simpler, easier to test, and harder to corrupt (no second table to keep consistent).

## Goals / Non-Goals

**Goals:**

- Three tables created on fresh activation with the indexes Sessions 2–10 will actually read by.
- Idempotent migrator: running `migrate()` twice in the same code version is a no-op (a `get_option('pulsepress_db_version')` read, then nothing).
- The activation hook installs schema atomically, so a freshly activated plugin can immediately accept REST writes once Session 2 lands.
- A `plugins_loaded` guard upgrades schema after a plugin update without requiring the site owner to re-activate.
- Uninstall is opt-in destructive: the default leaves data alone (the conservative WordPress.org choice), the opt-in drops all tables and options cleanly.
- Every public surface (Schema, Migrator, options, uninstall) is unit-testable without WordPress in the loop (we stub `$wpdb` / option helpers in tests).

**Non-Goals:**

- No down/rollback migrations. WordPress plugins rarely need them and they double the surface area for very little real-world value. Schema changes that can't be expressed via dbDelta will use one-off delta callbacks introduced as needed.
- No migration log table. The `pulsepress_db_version` integer is enough.
- No WP-CLI command for forcing migration. Activation + `plugins_loaded` covers fresh install and upgrade; WP-CLI can be added behind the same `Migrator` service in a later session if required.
- No data-pruning cron. Gap 3 commits to "optional pruning" — the option is reserved (`pulsepress_retention_days`) but the actual pruning job is Session 8 territory.
- No fraud-metadata purger job. Gap 3 commits to a 30-day purge — the column (`fraud_metadata_purge_at`) is reserved here and the cron is wired alongside the IP-hash writer in Session 4.
- No settings UI. Settings page is Session 6.
- No PII inserts. Schema-only slice.

## Decisions

### D1. `Schema::VERSION` integer, not migration files

A single integer constant, bumped manually for each schema change, drives migration. Compared to a glob of files, this:

- Makes the "current code-side schema version" obvious from the source tree.
- Removes timestamp parsing and class-name reflection from the runner.
- Trivially unit-testable: `Schema::VERSION` is a constant, `pulsepress_db_version` is an option.
- Survives squashed commits and reordered PRs; file-based migrations break in both.

**Alternative considered**: starter-style file-per-migration. Rejected — adds glob ordering, parser regex, and a `wp_migrations` log table for a domain with maybe ten lifetime migrations.

**Trade-off**: schema-as-of-version-N is not introspectable from history without `git checkout`. We accept this; the source tree is the source of truth.

### D2. `dbDelta` for every statement, including initial create

`dbDelta` is idempotent for CREATE TABLE and handles ALTER for new columns/indexes within bounds. Calling it on every migration step — even the first — means:

- The CREATE path is identical to the ALTER path; one branch to test.
- An admin who manually drops a table can recover by hitting the `plugins_loaded` guard (or re-activating).

`dbDelta`'s well-known gotchas (two spaces after PRIMARY KEY, KEY name required) live entirely inside `Schema`, where they can be diffed once and never thought about again.

### D3. Schema version stored as integer string in `pulsepress_db_version`

The option already exists from Session 0 with value `'0'`. We keep it a string for backwards-compat with the seed (no migration on the migration). Inside `Migrator` we cast to `(int)` for comparison and `(string)` for writes.

### D4. `(post_id, user_hash)` is the natural key on reactions

Gap 6's replacement rule maps directly to a `UNIQUE KEY uniq_post_user (post_id, user_hash)`. Session 2's REST handler will use `INSERT ... ON DUPLICATE KEY UPDATE reaction_type = VALUES(reaction_type), updated_at = VALUES(updated_at)`. The constraint enforces the rule at the storage layer, not just at application code — no race-induced duplicates.

We deliberately do NOT keep an event log. The trade-off is that hot-changing users lose history, but the plan commits to that semantic and aggregate counts are owned by `pulsepress_daily_agg`.

### D5. `pulsepress_captures` has `(email, post_id)` unique

A single email shouldn't capture twice for the same post (it would corrupt CSV export and inflate capture rate). Different posts may all capture the same email — that's intentional, since each capture has its own consent context.

`email` is stored case-insensitively at the application layer (Session 4 normalises before insert) and as VARCHAR(190) so the unique index fits in InnoDB's 767-byte legacy limit even on hosts that haven't enabled `innodb_large_prefix`. Alternative considered: VARCHAR(254) per RFC 5321. Rejected — 190 fits the universal limit; hosts that need longer emails are vanishingly rare and we can ALTER later.

### D6. `pulsepress_daily_agg` keyed by `(agg_date, post_id, reaction_type)`

Aggregation needs upsert semantics: re-running for the same day should replace the row, not duplicate. The composite unique index plus `INSERT ... ON DUPLICATE KEY UPDATE` is the cheapest implementation. `agg_date` is a SQL DATE because we never need sub-day granularity in the free product.

### D7. Activation-hook idempotency through `Migrator::migrate()`

The Session 0 activation hook seeded `pulsepress_db_version='0'`. We keep that seed (it makes the first-activation comparison work), then call `Migrator::migrate()` right after. On reactivation, the migrator sees `pulsepress_db_version >= Schema::VERSION` and exits without doing work. There is no second activation seed; the migrator owns version writes from now on.

### D8. `uninstall.php` defaults to non-destructive

WordPress invokes `uninstall.php` only when an admin clicks "Delete" on the plugin row. If `pulsepress_delete_on_uninstall` is `false` (the default), uninstall is a no-op — the admin keeps their data and can reinstall without loss. If `true`, the file:

1. `DROP TABLE` each of the three custom tables.
2. `delete_option()` for every `pulsepress_*` option key, including `pulsepress_db_version`.
3. Deletes nothing in `wp_options` outside the `pulsepress_` prefix.

WordPress.org review prefers this opt-in pattern; surprise data loss is a common rejection reason.

### D9. `plugins_loaded` priority for `DatabaseServiceProvider::boot()` is the default (10), but the guard runs before any other DB access

The provider's `boot()` reads `pulsepress_db_version` and short-circuits when current. Other providers register their hooks but do not touch the DB until later actions (`rest_api_init` for routes, `wp_enqueue_scripts` for assets), so even on a schema mismatch we're guaranteed to have run `migrate()` before any feature query happens.

### D10. PHP 8.1 `readonly` and named constants

`Schema` uses `final class` with `public const VERSION = 1;` and a static method `tables()` returning a typed associative array. Each migration is a constant CREATE TABLE statement built from `$wpdb->prefix` and `$wpdb->get_charset_collate()` — the only runtime variables. This makes `Schema` unit-testable by injecting a fake `$wpdb` shim.

## Risks / Trade-offs

- **Risk**: `dbDelta` silently swallowing a syntax error and leaving the table unchanged. → Mitigation: after `dbDelta` returns, `Migrator` queries `INFORMATION_SCHEMA.TABLES` for the existence of every declared table and aborts with a logged error if any are missing. We only write `pulsepress_db_version` after this post-flight check passes.
- **Risk**: An admin runs a manual DROP on `pulsepress_reactions`. → Mitigation: the `plugins_loaded` guard checks `pulsepress_db_version`, sees no mismatch, and does not recover. The admin must re-activate. Acceptable — manual DROPs are a "you broke it, you fix it" path. A future health-check action can detect missing tables and prompt re-activation.
- **Risk**: A site upgrades from PulsePress vN to vN+1 mid-request between when the option is read and when migrate runs. → Mitigation: irrelevant. WP plugin upgrades replace the filesystem before the next request; mid-request upgrades aren't a thing.
- **Risk**: dbDelta has known limitations around composite UNIQUE indexes and column reordering. → Mitigation: Schema constants are diffed by humans on every bump; the post-flight check verifies the tables exist; column-rename or unique-index-rename migrations will be expressed as raw `$wpdb->query()` deltas behind a numbered method on `Migrator` introduced when needed.
- **Trade-off**: No event log means we cannot reconstruct historical sentiment for users who change reactions. Daily aggregation captures the snapshot at end-of-day. Documented in gap decisions and accepted.
- **Trade-off**: VARCHAR(190) for `email` is shorter than RFC 5321 allows. Accepted per D5.
- **Trade-off**: `IP` and `UA` are hashed, not stored raw. We trade investigative depth for privacy; the 30-day purge applies to the hashes too, so no long-term identifiable fingerprint survives even with the salt leaked.

## Migration Plan

This is the first schema. There is no data to migrate. The deploy path on a fresh install:

1. Admin activates PulsePress → activation hook seeds `pulsepress_db_version='0'` (Session 0 behaviour, kept for the first-activation comparison) → activation hook calls `Migrator::migrate()` → all three tables created via `dbDelta` → `pulsepress_db_version='1'`.
2. Next request: `plugins_loaded` runs `DatabaseServiceProvider::boot()` → option is `1`, Schema::VERSION is `1`, no work performed.

The deploy path on a future schema bump (illustrative, not part of this change):

1. Code ships with `Schema::VERSION = 2`.
2. On the first request after the file upgrade: option is `1`, code is `2`, `Migrator::migrate()` runs the delta, writes `2`.
3. Subsequent requests: option is `2`, code is `2`, no work performed.

Rollback strategy: revert the commit and re-deploy. If the new version added a column, the column lingers harmlessly until a future down-migration is shipped. We commit to never silently dropping columns in a release.

## Open Questions

- **Q1**: Should the secret salt for `user_hash` be stored as a plugin option, generated on activation, or derived from `wp_salt()`? → **Decision deferred** to Session 2 (it's part of the REST contract surface, not the schema). For now, `user_hash` is just a CHAR(64); how it's computed is the REST handler's problem.
- **Q2**: Should `Schema::VERSION` start at `1` (Schema is now installed) or `0` is "no schema" and `1` means "v1 schema installed"? → Decided: `Schema::VERSION = 1`. The seed `'0'` written by Session 0 means "no schema installed" and naturally triggers the first migration. Both numbers stay strings on disk.
- **Q3**: Should `agg_date` be UTC or site-local on disk? → Decided: SQL DATE without timezone, interpreted as site-local. The aggregator (Session 8) is responsible for the timezone conversion at write time. This matches gap 7.
