## 1. Repository hygiene

- [x] 1.1 Add `.gitignore` excluding `vendor/`, `node_modules/`, `dist/`, `.idea/`, `.vscode/`, `.DS_Store`, `.phpunit.result.cache`, `coverage/`, `test-results/`, `playwright-report/`.
- [x] 1.2 Add `index.php` silence file at plugin root with `<?php // Silence is golden.`

## 2. Composer and PHP namespace

- [x] 2.1 Write `composer.json` with `require: { "php": ">=8.1" }`, `require-dev: { "pestphp/pest": "^2.34" }`, PSR-4 autoload `"Moonfarmer\ReactionsLeadCapture\\\\": "app/"`, files autoload `app/Helpers/functions.php`, `autoload-dev` for `Tests\\`, and a `scripts.test` entry running `pest`.
- [x] 2.2 Run `composer install` and confirm `vendor/autoload.php` exists.
- [x] 2.3 Verify autoload by running `composer dump-autoload --optimize` and checking exit code is 0.

## 3. Plugin entry and constants

- [x] 3.1 Create `moonfarmer-reactions-lead-capture.php` with WordPress plugin header (`Plugin Name`, `Description`, `Version: 0.1.0`, `Author`, `Text Domain: moonfarmer-reactions-lead-capture`, `Requires at least: 6.2`, `Requires PHP: 8.1`).
- [x] 3.2 Define `MOONFARMER_REACTIONS_LEAD_CAPTURE_VERSION`, `MOONFARMER_REACTIONS_LEAD_CAPTURE_FILE`, `MOONFARMER_REACTIONS_LEAD_CAPTURE_DIR`, `MOONFARMER_REACTIONS_LEAD_CAPTURE_URL` constants.
- [x] 3.3 Add PHP/WP version guard: if `PHP_VERSION_ID < 80100` or `$wp_version < '6.2'`, register an `admin_notices` callback and `return` from the entry without booting.
- [x] 3.4 Register `register_activation_hook(__FILE__, ...)` that calls `update_option('moonfarmer_reactions_lead_capture_db_version', '0', false)`.
- [x] 3.5 Register `register_deactivation_hook(__FILE__, ...)` as a no-op stub for now.
- [x] 3.6 On `plugins_loaded` (priority 5), require `vendor/autoload.php` and call `\Moonfarmer\ReactionsLeadCapture\Core\Application::boot(__FILE__)`.

## 4. Core skeleton (`app/`)

- [x] 4.1 Create `app/Core/Application.php` with `boot(string $pluginFile): self`, lazy singleton, a constructor that stores the plugin file path, and `register()`/`boot()` methods that walk a hardcoded providers array.
- [x] 4.2 Create `app/Core/Container.php` — a minimal PSR-11-like container with `bind`, `singleton`, `make`. Adapt the starter's version but strip unused features.
- [x] 4.3 Create `app/Core/ServiceProvider.php` as an abstract class with empty `register()` and `boot()` methods and a protected `$app` reference.
- [x] 4.4 Create `app/Core/Hook.php` — thin wrapper around `add_action`/`add_filter` (copy from starter, rename namespace).
- [x] 4.5 Create `app/Core/Asset.php` — thin wrapper around `wp_register_script`/`wp_register_style` (copy and rename). Mark as unused for now; widget will use it in Session 3.
- [x] 4.6 Create `app/Core/Config.php` — array-backed config loader (copy and rename).
- [x] 4.7 Create `app/Core/Router.php` — REST/AJAX route helper (copy and rename). Mark as unused for now; Session 2 will wire routes.
- [x] 4.8 Create `app/Core/View.php` only if the starter's version is under ~40 lines; otherwise skip and write later when admin templates land.
- [x] 4.9 Do NOT copy `app/Core/Cache.php`, `Logger.php`, `Security.php`, or `Facade.php` from the starter. Do NOT copy `app/Facades/` directory.
- [x] 4.10 Create `app/Helpers/functions.php` containing only a single `moonfarmer_reactions_lead_capture_container()` helper that returns the application container.

## 5. Service provider and bootstrap files

- [x] 5.1 Create `app/Providers/AppServiceProvider.php` with empty `register()` and `boot()` methods and a comment listing extension points (`// Add new providers here in later sessions.`).
- [x] 5.2 Create `app/bootstrap.php` that returns an array `['providers' => [AppServiceProvider::class]]` — the single source of truth for provider order.
- [x] 5.3 Create `app/routes.php` and `app/hooks.php` as empty PHP files with a one-line comment indicating their purpose. Both stay empty for this session.
- [x] 5.4 Delete `app/autoloader.php` from the copy plan — Composer's autoloader replaces it.

## 6. Frontend build

- [x] 6.1 Write `package.json` with name `moonfarmer-reactions-lead-capture`, devDependencies `vite ^5`, `@preact/preset-vite`, `@playwright/test`, dependencies `preact`. Scripts: `dev: vite`, `build: vite build`, `test:e2e: playwright test`.
- [x] 6.2 Write `vite.config.js` with `@preact/preset-vite`, `build.outDir: 'dist'`, `build.manifest: true`, `build.rollupOptions.input: 'resources/widget/index.ts'`.
- [x] 6.3 Create `resources/widget/index.ts` with a single comment placeholder: `// Moonfarmer Reactions Lead Capture widget entry — Session 3 will populate.` and an empty `export {}`.
- [x] 6.4 Run `npm install` and confirm `npm run build` produces `dist/.vite/manifest.json`.

## 7. Tests

- [x] 7.1 Create `phpunit.xml` minimal config pointing at `tests/`.
- [x] 7.2 Create `tests/Pest.php` with `uses(Tests\TestCase::class)->in(__DIR__)`.
- [x] 7.3 Create `tests/TestCase.php` extending `PHPUnit\Framework\TestCase`.
- [x] 7.4 Create `tests/Unit/BootstrapTest.php` with one test: `it('defines MOONFARMER_REACTIONS_LEAD_CAPTURE_VERSION when plugin file loads')` that requires `moonfarmer-reactions-lead-capture.php` in isolation (mocking `register_activation_hook` and friends as needed) and asserts `defined('MOONFARMER_REACTIONS_LEAD_CAPTURE_VERSION') === true`.
- [x] 7.5 Run `composer test` and confirm at least one passing test.

## 8. Packaging files

- [x] 8.1 Write `.distignore` excluding `node_modules`, `tests`, `phpunit.xml`, `playwright.config.ts`, `vite.config.js`, `package.json`, `package-lock.json`, `openspec`, `docs`, `.github`, `.git`, `.gitignore`, `.distignore`, `composer.lock`, `resources/widget`.
- [x] 8.2 Write `readme.txt` WordPress.org stub with `=== Moonfarmer Reactions Lead Capture ===`, `Tags: reactions, email, analytics`, `Requires at least: 6.2`, `Tested up to: 6.6`, `Requires PHP: 8.1`, `Stable tag: 0.1.0`, `License: GPLv2 or later`, and a one-paragraph short description.

## 9. Verification

- [x] 9.1 Run `find . -path ./node_modules -prune -o -path ./vendor -prune -o -type f \( -name '*.php' -o -name '*.json' -o -name '*.js' -o -name '*.ts' -o -name '*.txt' \) -print | xargs grep -lE 'WPPluginMatrix|WP_PLUGIN_MATRIX|wp-plugin-matrix' 2>/dev/null` and confirm no output.
- [x] 9.2 Run `find app moonfarmer-reactions-lead-capture.php -name '*.php' -print0 | xargs -0 -n1 php -l` and confirm every file reports "No syntax errors".
- [x] 9.3 Run `composer dump-autoload --optimize` and confirm exit code 0.
- [x] 9.4 Run `npm run build` and confirm `dist/.vite/manifest.json` exists.
- [x] 9.5 Run `composer test` and confirm green.
- [x] 9.6 Activate the plugin on a local WordPress (if available) and confirm no PHP notices, warnings, or errors in `wp-content/debug.log`.
- [x] 9.7 Run `openspec validate bootstrap-plugin --strict --no-interactive` and confirm clean.
