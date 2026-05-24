## ADDED Requirements

### Requirement: Free package describes current shipped Free functionality

The WordPress.org `readme.txt` SHALL describe the current Free plugin surface: reactions, inline email capture, CSV export, 30-day analytics, Gutenberg block, shortcode, settings, per-post overrides, privacy controls, and extensibility hooks. It SHALL NOT claim unavailable Pro features are included in Free.

#### Scenario: Readme is current

- **WHEN** a reviewer reads `readme.txt`
- **THEN** the installation and description sections match the plugin's current UI and no section says the configuration UI ships in a future release

### Requirement: Distribution zip excludes repo-only files

The release zip SHALL include runtime files needed by WordPress and SHALL exclude development files. It SHALL include `moonfarmer-reactions-lead-capture.php`, `app/`, `blocks/`, `dist/`, `vendor/`, `readme.txt`, `license.txt`, `index.php`, and `uninstall.php`. It SHALL exclude `docs/`, `openspec/`, `tests/`, `node_modules/`, `resources/`, `.git*`, `.distignore`, `AGENTS.md`, `package*.json`, `composer.json`, `composer.lock`, `phpunit.xml`, `vite.config.js`, and local build scripts.

#### Scenario: Zip inspection

- **WHEN** the release builder creates `build/moonfarmer-reactions-lead-capture-0.1.0.zip`
- **THEN** `unzip -l` shows runtime files and does not show excluded repo-only files

### Requirement: Release builder is deterministic from a clean checkout

The repository SHALL provide a local release builder that installs Node dependencies, builds Vite assets, installs Composer production dependencies with an optimized autoloader, applies `.distignore`, and emits a versioned zip under `build/`.

#### Scenario: Build artifact exists

- **WHEN** running `scripts/build-release.sh`
- **THEN** `build/moonfarmer-reactions-lead-capture-0.1.0.zip` exists and contains a top-level `moonfarmer-reactions-lead-capture/` directory

### Requirement: Runtime supports PHP 7.4 through 8.4

The plugin header, Composer runtime requirement, and WordPress.org readme SHALL declare PHP 7.4 as the minimum supported version. Runtime PHP files SHALL avoid PHP 8-only syntax and SHALL lint cleanly on PHP 7.4, 8.0, 8.1, 8.2, 8.3, and 8.4.

#### Scenario: PHP version matrix lint

- **WHEN** running PHP lint on `app/`, `moonfarmer-reactions-lead-capture.php`, and `uninstall.php` with PHP 7.4, 8.0, 8.1, 8.2, 8.3, and 8.4
- **THEN** every runtime PHP file reports no syntax errors

### Requirement: License and attribution are reviewable

The package SHALL include GPL license text and the repository SHALL document package dependency attribution and license compatibility for shipped third-party code.

#### Scenario: License files present

- **WHEN** inspecting the repository
- **THEN** `license.txt` exists and `docs/package-attribution.md` lists shipped dependencies and their license posture

### Requirement: WordPress.org visual assets are explicitly queued

The repository SHALL include a checklist for WordPress.org icons, banners, screenshots, captions, and final submission notes.

#### Scenario: Asset checklist exists

- **WHEN** opening `docs/wordpress-org-assets.md`
- **THEN** it names required asset dimensions, proposed screenshot sequence, and what still requires final brand approval
