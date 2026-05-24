## 1. Schema definition

- [x] 1.1 Create `app/Database/Schema.php` declaring `final class Schema` in namespace `Moonfarmer\ReactionsLeadCapture\Database` with `public const VERSION = 1`.
- [x] 1.2 Add `Schema::tables(\wpdb $wpdb): array<string, string>` returning a map of un-prefixed table name → full CREATE TABLE SQL for `moonfarmer_reactions_lead_capture_reactions`, `moonfarmer_reactions_lead_capture_captures`, and `moonfarmer_reactions_lead_capture_daily_agg`, each using `$wpdb->prefix` and `$wpdb->get_charset_collate()`. Each statement must follow `dbDelta` conventions (two spaces after `PRIMARY KEY`, KEY names on every index, trailing semicolon, no backticks on `PRIMARY KEY`).
- [x] 1.3 Add `Schema::tableName(\wpdb $wpdb, string $unprefixed): string` helper returning `$wpdb->prefix . $unprefixed` for tests and consumers.

## 2. Migrator

- [x] 2.1 Create `app/Database/Migrator.php` declaring `final class Migrator` in namespace `Moonfarmer\ReactionsLeadCapture\Database`. Constructor takes `(\wpdb $wpdb, Schema $schema)`.
- [x] 2.2 Implement `migrate(): bool` that: reads `(int) get_option('moonfarmer_reactions_lead_capture_db_version', '0')`, short-circuits when `>= Schema::VERSION`, otherwise loads `wp-admin/includes/upgrade.php`, calls `dbDelta` for every table in `Schema::tables()`, runs the post-flight existence check, and updates `moonfarmer_reactions_lead_capture_db_version` only when every table exists. Returns `true` when work was performed, `false` when no-op.
- [x] 2.3 Implement `currentVersion(): int` and `latestVersion(): int` accessors for tests and a future health-check action.
- [x] 2.4 Log missing tables via `error_log('[Moonfarmer Reactions Lead Capture] migration failed: missing table <name>')` and abort the version write. Do not throw — schema failure must not crash the request.

## 3. Provider wiring

- [x] 3.1 Create `app/Providers/DatabaseServiceProvider.php` extending `ServiceProvider`. In `register()`, bind `Schema::class` and `Migrator::class` as singletons (resolve `Schema` with no args, `Migrator` with `$GLOBALS['wpdb']` and the bound `Schema`).
- [x] 3.2 In `boot()`, attach a `plugins_loaded` callback at priority 5 that calls `Migrator::migrate()` only when `currentVersion() < latestVersion()`. The callback must not perform a migration twice per request.
- [x] 3.3 Append `DatabaseServiceProvider::class` to the providers array in `app/bootstrap.php`, after `AppServiceProvider::class`.

## 4. Activation + uninstall

- [x] 4.1 Update the activation closure in `moonfarmer-reactions-lead-capture.php` so that, after the existing `update_option('moonfarmer_reactions_lead_capture_db_version', '0', false)` seed, it requires `vendor/autoload.php` (if not already loaded), instantiates the migrator via `\Moonfarmer\ReactionsLeadCapture\Core\Application::boot(__FILE__)`, and calls the migrator. Activation must succeed even when WordPress has not finished loading other plugins.
- [x] 4.2 Also seed `moonfarmer_reactions_lead_capture_delete_on_uninstall='0'` and `moonfarmer_reactions_lead_capture_retention_days='0'` in the activation hook, using the same "only if missing" pattern so admin-set values survive re-activation.
- [x] 4.3 Create `uninstall.php` at the plugin root. Guard with `if (!defined('WP_UNINSTALL_PLUGIN')) { exit; }`. Read `moonfarmer_reactions_lead_capture_delete_on_uninstall`; when `'1'`, drop the three tables via `$wpdb->query("DROP TABLE IF EXISTS ...")` and delete every option whose key matches `moonfarmer-reactions-lead-capture\_%` (use `$wpdb->prepare` on the LIKE pattern).
- [x] 4.4 Verify that `uninstall.php` does NOT touch options outside the `moonfarmer_reactions_lead_capture_` prefix and does NOT throw on a missing table.

## 5. Tests

- [x] 5.1 Add `tests/Unit/SchemaTest.php` asserting `Schema::VERSION === 1`, that `Schema::tables(...)` returns exactly the three expected keys, that each SQL string contains the un-prefixed table name and `$wpdb->prefix` placeholder, and that each contains the documented columns and the expected UNIQUE/KEY declarations. Use a stub `\wpdb` whose `prefix` is `'wp_'` and whose `get_charset_collate()` returns a deterministic string.
- [x] 5.2 Add `tests/Unit/MigratorTest.php` asserting: when `currentVersion()` equals `latestVersion()`, `migrate()` returns `false` and does not call `dbDelta`; when below, `migrate()` writes the version exactly once. Test doubles for `\wpdb` and the option store.
- [ ] 5.3 Add a test asserting that `uninstall.php` exits cleanly when `moonfarmer_reactions_lead_capture_delete_on_uninstall` is `'0'` (load the file in a stubbed env, assert no DROP queries issued). **Deferred**: top-level `uninstall.php` is awkward to unit-test in isolation; covered by manual verification in 6.4/6.5. Extract to a `Uninstaller` class in a later session if the logic grows.
- [x] 5.4 Update `tests/Unit/BootstrapTest.php` to also assert `class_exists(\Moonfarmer\ReactionsLeadCapture\Database\Schema::class)` and `class_exists(\Moonfarmer\ReactionsLeadCapture\Database\Migrator::class)`.
- [x] 5.5 Run `composer test` and confirm green.

## 6. Verification

- [x] 6.1 Run `find app moonfarmer-reactions-lead-capture.php uninstall.php -name '*.php' -print0 | xargs -0 -n1 /opt/homebrew/opt/php@8.3/bin/php -l` and confirm every file reports "No syntax errors".
- [x] 6.2 Activate the plugin on a local WordPress (if available); inspect `SHOW TABLES LIKE 'wp_moonfarmer_reactions_lead_capture_%'` and confirm three rows. **Verified 2026-05-13** on `wp_lab.test` (WP 6.x, PHP 8.3.30): three tables created, schema matches spec.
- [x] 6.3 Deactivate then reactivate; confirm `moonfarmer_reactions_lead_capture_db_version` is `'1'` and tables remain untouched. **Verified 2026-05-13**: db_version stayed at `'1'` and the three tables were untouched.
- [x] 6.4 Toggle `moonfarmer_reactions_lead_capture_delete_on_uninstall='1'` and delete the plugin via the WP admin; confirm tables and options are gone. **Verified 2026-05-13** via `wp plugin uninstall moonfarmer-reactions-lead-capture --skip-delete`: all three tables dropped, all `moonfarmer_reactions_lead_capture_*` options removed, no other options touched.
- [x] 6.5 Re-install with the option missing/default; confirm uninstall is a no-op. **Verified 2026-05-13**: with `moonfarmer_reactions_lead_capture_delete_on_uninstall='0'`, `wp plugin uninstall --skip-delete` left tables and options intact.
- [x] 6.6 Run `openspec validate schema-and-migrations --strict --no-interactive` and confirm clean.
