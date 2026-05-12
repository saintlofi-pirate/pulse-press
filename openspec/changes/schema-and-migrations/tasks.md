## 1. Schema definition

- [x] 1.1 Create `app/Database/Schema.php` declaring `final class Schema` in namespace `PulsePress\Database` with `public const VERSION = 1`.
- [x] 1.2 Add `Schema::tables(\wpdb $wpdb): array<string, string>` returning a map of un-prefixed table name → full CREATE TABLE SQL for `pulsepress_reactions`, `pulsepress_captures`, and `pulsepress_daily_agg`, each using `$wpdb->prefix` and `$wpdb->get_charset_collate()`. Each statement must follow `dbDelta` conventions (two spaces after `PRIMARY KEY`, KEY names on every index, trailing semicolon, no backticks on `PRIMARY KEY`).
- [x] 1.3 Add `Schema::tableName(\wpdb $wpdb, string $unprefixed): string` helper returning `$wpdb->prefix . $unprefixed` for tests and consumers.

## 2. Migrator

- [x] 2.1 Create `app/Database/Migrator.php` declaring `final class Migrator` in namespace `PulsePress\Database`. Constructor takes `(\wpdb $wpdb, Schema $schema)`.
- [x] 2.2 Implement `migrate(): bool` that: reads `(int) get_option('pulsepress_db_version', '0')`, short-circuits when `>= Schema::VERSION`, otherwise loads `wp-admin/includes/upgrade.php`, calls `dbDelta` for every table in `Schema::tables()`, runs the post-flight existence check, and updates `pulsepress_db_version` only when every table exists. Returns `true` when work was performed, `false` when no-op.
- [x] 2.3 Implement `currentVersion(): int` and `latestVersion(): int` accessors for tests and a future health-check action.
- [x] 2.4 Log missing tables via `error_log('[PulsePress] migration failed: missing table <name>')` and abort the version write. Do not throw — schema failure must not crash the request.

## 3. Provider wiring

- [x] 3.1 Create `app/Providers/DatabaseServiceProvider.php` extending `ServiceProvider`. In `register()`, bind `Schema::class` and `Migrator::class` as singletons (resolve `Schema` with no args, `Migrator` with `$GLOBALS['wpdb']` and the bound `Schema`).
- [x] 3.2 In `boot()`, attach a `plugins_loaded` callback at priority 5 that calls `Migrator::migrate()` only when `currentVersion() < latestVersion()`. The callback must not perform a migration twice per request.
- [x] 3.3 Append `DatabaseServiceProvider::class` to the providers array in `app/bootstrap.php`, after `AppServiceProvider::class`.

## 4. Activation + uninstall

- [x] 4.1 Update the activation closure in `pulsepress.php` so that, after the existing `update_option('pulsepress_db_version', '0', false)` seed, it requires `vendor/autoload.php` (if not already loaded), instantiates the migrator via `\PulsePress\Core\Application::boot(__FILE__)`, and calls the migrator. Activation must succeed even when WordPress has not finished loading other plugins.
- [x] 4.2 Also seed `pulsepress_delete_on_uninstall='0'` and `pulsepress_retention_days='0'` in the activation hook, using the same "only if missing" pattern so admin-set values survive re-activation.
- [x] 4.3 Create `uninstall.php` at the plugin root. Guard with `if (!defined('WP_UNINSTALL_PLUGIN')) { exit; }`. Read `pulsepress_delete_on_uninstall`; when `'1'`, drop the three tables via `$wpdb->query("DROP TABLE IF EXISTS ...")` and delete every option whose key matches `pulsepress\_%` (use `$wpdb->prepare` on the LIKE pattern).
- [x] 4.4 Verify that `uninstall.php` does NOT touch options outside the `pulsepress_` prefix and does NOT throw on a missing table.

## 5. Tests

- [x] 5.1 Add `tests/Unit/SchemaTest.php` asserting `Schema::VERSION === 1`, that `Schema::tables(...)` returns exactly the three expected keys, that each SQL string contains the un-prefixed table name and `$wpdb->prefix` placeholder, and that each contains the documented columns and the expected UNIQUE/KEY declarations. Use a stub `\wpdb` whose `prefix` is `'wp_'` and whose `get_charset_collate()` returns a deterministic string.
- [x] 5.2 Add `tests/Unit/MigratorTest.php` asserting: when `currentVersion()` equals `latestVersion()`, `migrate()` returns `false` and does not call `dbDelta`; when below, `migrate()` writes the version exactly once. Test doubles for `\wpdb` and the option store.
- [ ] 5.3 Add a test asserting that `uninstall.php` exits cleanly when `pulsepress_delete_on_uninstall` is `'0'` (load the file in a stubbed env, assert no DROP queries issued). **Deferred**: top-level `uninstall.php` is awkward to unit-test in isolation; covered by manual verification in 6.4/6.5. Extract to a `Uninstaller` class in a later session if the logic grows.
- [x] 5.4 Update `tests/Unit/BootstrapTest.php` to also assert `class_exists(\PulsePress\Database\Schema::class)` and `class_exists(\PulsePress\Database\Migrator::class)`.
- [x] 5.5 Run `composer test` and confirm green.

## 6. Verification

- [x] 6.1 Run `find app pulsepress.php uninstall.php -name '*.php' -print0 | xargs -0 -n1 /opt/homebrew/opt/php@8.3/bin/php -l` and confirm every file reports "No syntax errors".
- [ ] 6.2 Activate the plugin on a local WordPress (if available); inspect `SHOW TABLES LIKE 'wp_pulsepress_%'` and confirm three rows. **Pending local WP verification.**
- [ ] 6.3 Deactivate then reactivate; confirm `pulsepress_db_version` is `'1'` and tables remain untouched. **Pending local WP verification.**
- [ ] 6.4 Toggle `pulsepress_delete_on_uninstall='1'` and delete the plugin via the WP admin; confirm tables and options are gone. **Pending local WP verification.**
- [ ] 6.5 Re-install with the option missing/default; confirm uninstall is a no-op. **Pending local WP verification.**
- [x] 6.6 Run `openspec validate schema-and-migrations --strict --no-interactive` and confirm clean.
