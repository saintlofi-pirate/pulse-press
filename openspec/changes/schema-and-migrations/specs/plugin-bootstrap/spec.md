## MODIFIED Requirements

### Requirement: Activation hook stores version placeholder

The activation hook SHALL call `update_option('pulsepress_db_version', '0', false)` only when the option does not already exist, then invoke `PulsePress\Database\Migrator::migrate()` to install or upgrade the schema. The hook SHALL NOT create tables directly — table creation is owned by the migrator. The legacy single-purpose hook from Session 0 is replaced by this two-step "seed-then-migrate" sequence.

#### Scenario: First activation

- **WHEN** a site owner activates the plugin for the first time on a database with no PulsePress tables
- **THEN** `get_option('pulsepress_db_version')` first returns `'0'`, then the migrator runs `dbDelta` for every declared table, then `get_option('pulsepress_db_version')` returns `'1'`

#### Scenario: Re-activation after a graceful deactivate

- **WHEN** the plugin is deactivated and activated again with no code or schema changes between
- **THEN** `pulsepress_db_version` remains intact, the migrator runs but performs no dbDelta calls, and the existing tables are untouched

#### Scenario: Activation right after a code upgrade

- **WHEN** the plugin code on disk has a higher `Schema::VERSION` than the stored option at the moment of activation
- **THEN** the migrator runs the pending dbDelta steps and updates `pulsepress_db_version` before the activation hook returns
