## ADDED Requirements

### Requirement: Plugin entry file defines version constants

The plugin entry file `moonfarmer-reactions-lead-capture.php` SHALL define `MOONFARMER_REACTIONS_LEAD_CAPTURE_VERSION`, `MOONFARMER_REACTIONS_LEAD_CAPTURE_FILE`, `MOONFARMER_REACTIONS_LEAD_CAPTURE_DIR`, and `MOONFARMER_REACTIONS_LEAD_CAPTURE_URL` constants as the only public globals exposed by the plugin. Every later module SHALL read these constants rather than recomputing paths.

#### Scenario: Constants exposed at bootstrap

- **WHEN** WordPress loads `moonfarmer-reactions-lead-capture.php`
- **THEN** `defined('MOONFARMER_REACTIONS_LEAD_CAPTURE_VERSION')`, `defined('MOONFARMER_REACTIONS_LEAD_CAPTURE_FILE')`, `defined('MOONFARMER_REACTIONS_LEAD_CAPTURE_DIR')`, and `defined('MOONFARMER_REACTIONS_LEAD_CAPTURE_URL')` all return `true`

#### Scenario: Version matches plugin header

- **WHEN** a developer reads `MOONFARMER_REACTIONS_LEAD_CAPTURE_VERSION`
- **THEN** its value matches the `Version:` field in the plugin header comment

### Requirement: PHP and WP version guard

The plugin SHALL check `PHP_VERSION_ID >= 80100` and `global $wp_version` against `>= 6.2` on every request. If either fails, the plugin SHALL deactivate itself on next admin page load and show an admin notice naming the required versions. The plugin MUST NOT fatal-error or crash WordPress when the floor is not met.

#### Scenario: PHP 8.0 host

- **WHEN** the plugin loads on PHP 8.0
- **THEN** no fatal error occurs, an admin notice tells the user "Moonfarmer Reactions Lead Capture requires PHP 8.1 or newer", and reactions/captures code does not register

#### Scenario: WP 6.1 host

- **WHEN** the plugin loads on WordPress 6.1
- **THEN** no fatal error occurs and an admin notice names the required WP version

#### Scenario: Compliant host

- **WHEN** the plugin loads on PHP 8.1+ and WP 6.2+
- **THEN** the bootstrap proceeds, the service provider is registered, and no notice is shown

### Requirement: Composer PSR-4 autoload under Moonfarmer Reactions Lead Capture namespace

The plugin SHALL ship a `composer.json` declaring PSR-4 autoload `"Moonfarmer\ReactionsLeadCapture\\\\": "app/"` and a files autoload entry for `app/Helpers/functions.php`. All plugin classes SHALL live under the `Moonfarmer Reactions Lead Capture` namespace. No file in the Moonfarmer Reactions Lead Capture codebase SHALL contain the strings `WPPluginMatrix`, `WP_PLUGIN_MATRIX`, or `wp-plugin-matrix-starter`.

#### Scenario: Autoloader resolves a core class

- **WHEN** PHP requests `Moonfarmer\ReactionsLeadCapture\Core\Application`
- **THEN** Composer's autoloader resolves it to `app/Core/Application.php`

#### Scenario: No starter strings remain

- **WHEN** running `grep -RE 'WPPluginMatrix|WP_PLUGIN_MATRIX|wp-plugin-matrix' --include='*.php' --include='*.json' --include='*.js' --include='*.txt' .` from the plugin root (excluding `node_modules`, `vendor`)
- **THEN** the command exits with no matches

### Requirement: Service-provider bootstrap with single AppServiceProvider

The bootstrap SHALL instantiate a single container, register `Moonfarmer\ReactionsLeadCapture\Providers\AppServiceProvider`, and call `register()` then `boot()` in that order. The bootstrap SHALL NOT contain any feature wiring inline — every feature must be added through a service provider.

#### Scenario: Provider boot order

- **WHEN** the plugin loads
- **THEN** `AppServiceProvider::register()` runs before `AppServiceProvider::boot()`, and both run before the `plugins_loaded` action fires

#### Scenario: Adding a feature later

- **WHEN** Session 1 introduces schema migrations
- **THEN** it adds a new `MigrationServiceProvider` registered from `AppServiceProvider::register()` rather than editing `moonfarmer-reactions-lead-capture.php`

### Requirement: Activation hook stores version placeholder

The activation hook SHALL call `update_option('moonfarmer_reactions_lead_capture_db_version', '0', false)` and do nothing else. No tables SHALL be created, no defaults written, and no scheduled events registered in this slice. The `'0'` value signals to the future migration service that no schema has been applied.

#### Scenario: First activation

- **WHEN** a site owner activates the plugin for the first time
- **THEN** `get_option('moonfarmer_reactions_lead_capture_db_version')` returns `'0'` and no custom tables exist

#### Scenario: Re-activation

- **WHEN** the plugin is deactivated and activated again
- **THEN** `moonfarmer_reactions_lead_capture_db_version` remains intact and is not overwritten unless its value is missing

### Requirement: Vite build with single widget entry

The build pipeline SHALL use Vite with exactly one entry point at `resources/widget/index.ts`. The Vite config SHALL output a manifest file to `dist/.vite/manifest.json` so a future asset registrar can resolve hashed filenames. No admin SPA entry, no Vue plugin, and no Tailwind/PostCSS pipeline SHALL be configured in this slice.

#### Scenario: Build produces widget bundle

- **WHEN** running `npm run build`
- **THEN** `dist/` contains a JS file produced from `resources/widget/index.ts` and `dist/.vite/manifest.json` exists

#### Scenario: No admin SPA entry

- **WHEN** inspecting `vite.config.js`
- **THEN** `build.rollupOptions.input` is a single string or single-key object pointing only at the widget entry

### Requirement: Tests scaffold with Pest

The plugin SHALL ship a Pest scaffold at `tests/Pest.php` and `tests/TestCase.php`, plus at least one passing smoke test that asserts `MOONFARMER_REACTIONS_LEAD_CAPTURE_VERSION` is defined when the plugin entry is loaded. `composer test` SHALL run this test suite.

#### Scenario: Smoke test passes

- **WHEN** running `composer test`
- **THEN** the suite reports at least one passing test and zero failures

### Requirement: Distribution ignore excludes development files

The plugin SHALL ship a `.distignore` that excludes `node_modules`, `tests`, `phpunit.xml`, `playwright.config.ts`, `vite.config.js`, `package.json`, `package-lock.json`, `openspec`, `docs`, `.github`, `.git`, `.gitignore`, and `.distignore` itself from any distribution zip. `vendor/` SHALL be included.

#### Scenario: Build a release zip

- **WHEN** a packaging tool that respects `.distignore` builds a release zip
- **THEN** the zip contains `moonfarmer-reactions-lead-capture.php`, `app/`, `vendor/`, `dist/`, `resources/`, and `readme.txt`, and does NOT contain `tests/`, `node_modules/`, `openspec/`, or `docs/`
