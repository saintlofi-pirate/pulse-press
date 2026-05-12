## Why

PulsePress has a v1 plan and a clear product direction, but the working directory holds only docs — no plugin code, no Composer autoloader, no build pipeline. Before any reaction, capture, or analytics work can begin, we need a clean, lean plugin skeleton. The `wp-plugin-matrix` starter at `/Volumes/Projects/work/wp_lab/wp-content/plugins/wp-plugin-matrx` (revision `5ec965b`) gives us a tested plugin entry, Composer PSR-4 layout, service-provider pattern, migration helpers, Vite config, route helpers, and a Pest/Playwright test scaffold — selectively reusing it saves days versus building from scratch, and avoids inheriting the starter's heavy demo admin stack (Vue, Element Plus, Chart.js, Moment, Quill, Dotenv).

This change is bootstrap only — no product behavior, no schema, no REST endpoints, no admin UI. It establishes the foundation every later session (schema, REST, widget, capture, settings, analytics) builds on. Free remains complete in spirit because nothing is being gated or locked yet; we are simply making the floor on which the free experience will stand.

## What Changes

- Create `pulsepress.php` plugin entry with `PULSEPRESS_VERSION`, `PULSEPRESS_FILE`, `PULSEPRESS_DIR`, `PULSEPRESS_URL` constants and a minimum WP/PHP guard.
- Add `composer.json` with PSR-4 namespace `PulsePress\\` → `app/` and a `app/Helpers/functions.php` autoload entry. Require PHP `>=8.1`. Keep Pest as the only dev dependency.
- Copy the starter's slim core files (`Application`, `Container`, `ServiceProvider`, `Hook`, `Asset`, `Router`, `Config`, `View`) into `app/Core/` with the renamed namespace, dropping `Cache`, `Facade`, `Logger`, `Security` until a later session actually needs them.
- Add a single `app/Providers/AppServiceProvider.php` that wires the bootstrap, with empty `register()`/`boot()` so the next sessions have a clear extension point.
- Carry over `app/routes.php`, `app/hooks.php`, `app/bootstrap.php`, `app/autoloader.php` shapes — renamed and emptied of starter demo registrations.
- Add `package.json` with only what v1 needs: `vite`, `@preact/preset-vite` or equivalent, `preact`. Drop `vue`, `vue-router`, `element-plus`, `chart.js`, `moment`, `quill`, `tailwindcss`, `sass`/`sass-loader`, `vue-loader`, `resolve-url-loader`, `postcss-loader`, `@wordpress/hooks`. Keep `@playwright/test` in dev.
- Add `vite.config.js` targeting `resources/widget/index.ts` as the widget entry (no admin SPA entry yet).
- Add activation/deactivation hook stubs in the plugin entry that write a `pulsepress_db_version` option placeholder (no tables yet — Session 1 owns schema).
- Add a Pest scaffold (`tests/Pest.php`, `tests/TestCase.php`, one passing smoke test) and `phpunit.xml`.
- Add `.gitignore`, `.distignore`, `readme.txt` (WordPress.org stub), `index.php` silence file.
- **BREAKING**: none — there is no prior code to break.

## Capabilities

### New Capabilities

- `plugin-bootstrap`: defines the plugin's entry contract — version constants, activation/deactivation hooks, autoloader wiring, service-provider boot order, and the minimum WP/PHP guard. Every later capability depends on this.

### Modified Capabilities

None — no specs exist yet.

## Impact

- **New files**: `pulsepress.php`, `composer.json`, `package.json`, `vite.config.js`, `phpunit.xml`, `.gitignore`, `.distignore`, `readme.txt`, `index.php`, `app/**` skeleton, `resources/widget/index.ts` stub, `tests/Pest.php`, `tests/TestCase.php`, one smoke test.
- **No database changes** — Session 1 owns schema.
- **No REST/AJAX endpoints** — Session 2 owns the reaction contract.
- **No frontend behavior** — Session 3 owns the widget.
- **Dependencies introduced**: Composer (`pestphp/pest`), npm (`preact`, `vite`, `@playwright/test`).
- **Dependencies explicitly NOT introduced**: `vlucas/phpdotenv`, `vue`, `element-plus`, `chart.js`, `moment`, `quill`, `tailwindcss`.
- **Privacy**: no data collection in this slice.
- **Performance**: no frontend assets ship enabled by default until Session 3 wires the widget.
- **Free/Pro boundary**: not touched. Pro will be a separate addon plugin (Session 13).
