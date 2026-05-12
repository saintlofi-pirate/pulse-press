## ADDED Requirements

### Requirement: Schema declares a single integer version constant

`PulsePress\Database\Schema` SHALL expose a `public const VERSION` integer that the migration runner compares against the `pulsepress_db_version` option. The constant SHALL increase monotonically; once a value has shipped in a release, that value SHALL never describe a different schema in a later release.

#### Scenario: Constant reflects current schema state

- **WHEN** the plugin source is at the head of this change
- **THEN** `\PulsePress\Database\Schema::VERSION === 1`

#### Scenario: Reading schema version

- **WHEN** the migrator needs to decide whether to run
- **THEN** it reads `Schema::VERSION` directly and `get_option('pulsepress_db_version')` for the stored value, without consulting any other source

### Requirement: Three custom tables installed by the migrator

The migrator SHALL create three tables prefixed with the WordPress site's `$wpdb->prefix`: `<prefix>pulsepress_reactions`, `<prefix>pulsepress_captures`, and `<prefix>pulsepress_daily_agg`. Every CREATE statement SHALL include the result of `$wpdb->get_charset_collate()` and SHALL be passed through `dbDelta()`.

#### Scenario: Fresh install creates all tables

- **WHEN** the plugin is activated on a database with no PulsePress tables
- **THEN** `<prefix>pulsepress_reactions`, `<prefix>pulsepress_captures`, and `<prefix>pulsepress_daily_agg` all exist after activation completes

#### Scenario: Tables use the WordPress charset

- **WHEN** inspecting any of the three created tables
- **THEN** the table's character set matches `$wpdb->get_charset_collate()` for the site

### Requirement: Reactions table shape

The `<prefix>pulsepress_reactions` table SHALL have columns `id` (BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT), `post_id` (BIGINT UNSIGNED NOT NULL), `reaction_type` (VARCHAR(32) NOT NULL), `user_hash` (CHAR(64) NOT NULL), `created_at` (DATETIME NOT NULL), `updated_at` (DATETIME NOT NULL). It SHALL declare a UNIQUE KEY on `(post_id, user_hash)` and a secondary KEY on `(post_id, reaction_type)`.

#### Scenario: Replacement semantics enforced at storage

- **WHEN** an `INSERT` is attempted for an existing `(post_id, user_hash)` pair
- **THEN** the unique constraint causes a duplicate-key violation that the REST handler resolves with `INSERT ... ON DUPLICATE KEY UPDATE`

#### Scenario: Count-by-post-by-reaction read

- **WHEN** counting reactions of a given type for a given post
- **THEN** the secondary index `(post_id, reaction_type)` makes the query index-only over a narrow range

### Requirement: Captures table shape with consent and fraud-purge fields

The `<prefix>pulsepress_captures` table SHALL have columns `id` (BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT), `post_id` (BIGINT UNSIGNED NOT NULL), `email` (VARCHAR(190) NOT NULL), `reaction_type` (VARCHAR(32) NOT NULL), `consent` (TINYINT(1) NOT NULL DEFAULT 0), `consent_text_version` (VARCHAR(32) NOT NULL), `consent_at` (DATETIME NOT NULL), `source` (VARCHAR(32) NOT NULL), `ip_hash` (CHAR(64) NULL), `user_agent_hash` (CHAR(64) NULL), `fraud_metadata_purge_at` (DATETIME NOT NULL), `created_at` (DATETIME NOT NULL), `updated_at` (DATETIME NOT NULL). It SHALL declare a UNIQUE KEY on `(email, post_id)` and a secondary KEY on `fraud_metadata_purge_at` for the future purge job.

#### Scenario: Same email cannot capture twice on same post

- **WHEN** a second capture row is attempted for an existing `(email, post_id)` pair
- **THEN** the unique key prevents the duplicate

#### Scenario: Fraud-purge index supports the 30-day sweep

- **WHEN** a future purge job queries `WHERE fraud_metadata_purge_at < NOW()`
- **THEN** the secondary index on that column makes the scan index-only

### Requirement: Daily aggregate table shape

The `<prefix>pulsepress_daily_agg` table SHALL have columns `id` (BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT), `agg_date` (DATE NOT NULL), `post_id` (BIGINT UNSIGNED NOT NULL), `reaction_type` (VARCHAR(32) NOT NULL), `count` (INT UNSIGNED NOT NULL DEFAULT 0), `updated_at` (DATETIME NOT NULL). It SHALL declare a UNIQUE KEY on `(agg_date, post_id, reaction_type)` and a secondary KEY on `(post_id, agg_date)` for dashboard top-post queries.

#### Scenario: Aggregator can upsert by composite key

- **WHEN** the aggregator runs `INSERT ... ON DUPLICATE KEY UPDATE count = VALUES(count)`
- **THEN** re-running aggregation for the same date does not duplicate rows

#### Scenario: Dashboard top-posts read

- **WHEN** the dashboard queries rows for a post over the trailing 30 days
- **THEN** the `(post_id, agg_date)` index supports the lookup without a full scan

### Requirement: Migrator is idempotent and version-gated

`PulsePress\Database\Migrator::migrate()` SHALL only do work when the stored `pulsepress_db_version` is strictly less than `Schema::VERSION`. After successful migration, it SHALL update `pulsepress_db_version` to the new version exactly once. Running `migrate()` a second time within the same code version SHALL execute no `dbDelta` calls.

#### Scenario: First migration on fresh install

- **WHEN** `pulsepress_db_version` is `'0'` and `Schema::VERSION` is `1`, and `migrate()` is invoked
- **THEN** `dbDelta` is called once per declared table, the post-flight existence check passes, and `pulsepress_db_version` becomes `'1'`

#### Scenario: Second invocation in same release

- **WHEN** `pulsepress_db_version` is `'1'` and `Schema::VERSION` is `1`, and `migrate()` is invoked again
- **THEN** `migrate()` returns without calling `dbDelta` and without writing the option

### Requirement: Post-flight existence check before bumping version

After running `dbDelta` for every declared table, the migrator SHALL verify each table exists by querying `$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $tableName))`. If any table is missing, the migrator SHALL NOT update `pulsepress_db_version` and SHALL emit a `WP_Error`-style message via `error_log()` naming the missing table.

#### Scenario: dbDelta silently failed

- **WHEN** `dbDelta` returned without creating one of the tables
- **THEN** `pulsepress_db_version` remains at its previous value and the missing table name appears in PHP's error log

#### Scenario: All tables present after dbDelta

- **WHEN** every declared table exists after the dbDelta loop
- **THEN** `pulsepress_db_version` is updated to `(string) Schema::VERSION`

### Requirement: Retention and uninstall options reserved with documented defaults

The plugin SHALL reserve two option keys whose defaults preserve user data and require explicit opt-in for destructive behaviour: `pulsepress_delete_on_uninstall` (boolean stored as `'0'`/`'1'`, default `'0'`) and `pulsepress_retention_days` (integer-as-string, default `'0'` meaning "indefinite"). No code in this change SHALL act on either option; they are reserved for Session 6 (settings) and Session 8 (pruning) respectively.

#### Scenario: First activation seeds defaults

- **WHEN** the plugin is activated for the first time
- **THEN** `get_option('pulsepress_delete_on_uninstall', '0')` returns `'0'` and `get_option('pulsepress_retention_days', '0')` returns `'0'`

#### Scenario: Existing values are preserved across re-activation

- **WHEN** an admin has set `pulsepress_delete_on_uninstall = '1'` and the plugin is deactivated then reactivated
- **THEN** the value remains `'1'` after re-activation

### Requirement: Uninstall respects opt-in flag

`uninstall.php` at the plugin root SHALL be invoked by WordPress when the admin deletes the plugin. The script SHALL read `pulsepress_delete_on_uninstall`. When the value is `'1'`, it SHALL drop the three PulsePress tables and delete every option whose key begins with `pulsepress_`. When the value is anything else (including missing), it SHALL exit without touching the database.

#### Scenario: Default uninstall leaves data alone

- **WHEN** the admin deletes the plugin without ever changing `pulsepress_delete_on_uninstall`
- **THEN** the three custom tables remain intact and `pulsepress_db_version` is still readable after reinstall

#### Scenario: Opt-in uninstall wipes everything

- **WHEN** the admin has set `pulsepress_delete_on_uninstall = '1'` and deletes the plugin
- **THEN** the three custom tables are dropped and every option matching `pulsepress\_%` is removed

#### Scenario: Uninstall does not touch unrelated options

- **WHEN** opt-in uninstall runs on a site that also has `wp_options` rows with keys like `siteurl`, `admin_email`, or other plugins' settings
- **THEN** only options matching `pulsepress\_%` are deleted

### Requirement: DatabaseServiceProvider runs the upgrade guard on plugins_loaded

`PulsePress\Providers\DatabaseServiceProvider` SHALL register on the boot phase, hook into `plugins_loaded` at a priority that runs before any feature provider's REST or AJAX work, and call `Migrator::migrate()` only when `(int) get_option('pulsepress_db_version', '0') < Schema::VERSION`.

#### Scenario: Post-upgrade first request

- **WHEN** the plugin file is upgraded to a release whose `Schema::VERSION` is higher than the stored option
- **THEN** the first request after the upgrade triggers `Migrator::migrate()` from `plugins_loaded` and subsequent requests do not

#### Scenario: No-op steady-state request

- **WHEN** `pulsepress_db_version` already matches `Schema::VERSION`
- **THEN** `DatabaseServiceProvider::boot()` performs a single `get_option` call and does nothing else
