## Context

The Moonfarmer Reactions Lead Capture directory currently contains only `AGENTS.md`, `docs/`, and `openspec/`. There is no plugin entry, no autoloader, no build pipeline, no tests. Session 0 of the v1 plan is to scaffold a clean skeleton from the `wp-plugin-matrix` starter (revision `5ec965b`) and stop — schema, REST, widget, and admin UI come in later sessions.

The starter is well-suited as a source but ships a Vue + Element Plus + Chart.js + Moment + Quill demo admin stack we explicitly do not want. It also depends on `vlucas/phpdotenv`, which leaks `.env` patterns into a plugin that should be configured through `wp_options`. The job is to harvest what helps (plugin bootstrap, PSR-4 layout, service-provider container, Hook/Asset/Router helpers, Pest/Playwright wiring, packaging files) and reject what doesn't.

Local revisions:

- Starter HEAD: `5ec965b`. Composer namespace: `WPPluginMatrix\\` → `app/`. PHP floor: `>=8.0`. Pest 2.34.
- Moonfarmer Reactions Lead Capture target namespace: `Moonfarmer\ReactionsLeadCapture\\` → `app/`. PHP floor: `>=8.1` (matches WP 6.4+ recommendation, drops PHP 8.0 maintenance surface a year before EOL).

Renames required across every copied file:

- Namespace `WPPluginMatrix` → `Moonfarmer Reactions Lead Capture`
- Constants `WP_PLUGIN_MATRIX_*` → `MOONFARMER_REACTIONS_LEAD_CAPTURE_*`
- Slug `wp-plugin-matrix` / `wp-plugin-matrix-starter` → `moonfarmer-reactions-lead-capture`
- Text domain → `moonfarmer-reactions-lead-capture`

## Goals / Non-Goals

**Goals:**

- Stand up a plugin that activates cleanly on a local WordPress with no fatal errors and no admin notices.
- PSR-4 autoload via Composer, Pest smoke test passes, Vite build produces a (currently empty) widget bundle.
- A single `AppServiceProvider` is the only registration surface — every later session adds providers, never edits the bootstrap entry.
- Leave clear extension points (`app/routes.php`, `app/hooks.php`, `app/Providers/`) so subsequent sessions are local edits, not architectural changes.
- Zero starter strings remain in Moonfarmer Reactions Lead Capture-owned files (`grep -RE 'WPPluginMatrix|WP_PLUGIN_MATRIX|wp-plugin-matrix' .` returns nothing outside `node_modules`, `vendor`, and the original starter directory).

**Non-Goals:**

- No database tables, no migrations runtime, no `dbDelta` — Session 1 owns schema.
- No REST routes registered — Session 2 owns the reaction contract.
- No frontend behavior, no Preact components — Session 3 owns the widget.
- No admin pages, no settings — Session 6 owns settings.
- No `@wordpress/components`, no `@wordpress/scripts` — added when the admin actually needs them.
- No Tailwind. The widget will use a small handwritten CSS layer with CSS custom properties (Session 3 decision).
- No Action Scheduler dependency — Session 8 introduces the queue scheduler abstraction.

## Decisions

### D1. PHP floor at 8.1, not 8.0

The starter requires `>=8.0`. WP recommends 7.4 minimum and 8.x for new sites; 8.0 reaches security EOL late 2025. Picking 8.1 lets us use enums, readonly properties, and `never` return types from day one without locking out a meaningful slice of WP hosts (8.1+ adoption was >55% of WP installs by early 2025).

Alternative considered: match starter at 8.0. Rejected — the starter sets a hosting floor it doesn't enforce, and we should make our own deliberate choice.

### D2. Keep service-provider/container pattern, drop facades

The starter ships `app/Facades/` and `app/Core/Facade.php` to expose globals like `App::make(...)`. Moonfarmer Reactions Lead Capture will use constructor injection through the container directly. Facades hide the dependency graph and complicate static analysis; we have one plugin worth of code, not a framework.

Alternative considered: keep facades to reduce friction in handlers. Rejected — the rule in `AGENTS.md` is to avoid premature abstractions, and the audit value of seeing `(Container $c)` in handler signatures is higher than the typing convenience.

### D3. Drop `Logger`, `Cache`, `Security` core helpers from the copy

These are starter-provided helpers we don't need yet. `Cache` duplicates WordPress transients; `Logger` duplicates `error_log`/`WP_DEBUG_LOG`; `Security` wraps `wp_verify_nonce` and friends with no value-add. Add them back when a concrete session needs them, not speculatively.

### D4. Single Preact entry, no admin SPA entry

Vite config will define exactly one entry point: `resources/widget/index.ts`. The admin will use server-rendered pages with native WordPress components when Session 6 lands. This keeps the build graph small and the bundle audit honest.

Alternative considered: dual entry (`widget` + `admin`). Rejected — we have no admin code yet, and adding an empty entry encourages someone to fill it with a Vue/React SPA later.

### D5. No Dotenv, no `.env`

The starter requires `vlucas/phpdotenv`. WordPress plugins should be configured via `wp_options` and constants, not `.env` files. Dropping it removes one dependency and avoids confusing site owners who don't expect `.env` files in a plugin.

### D6. Pest as the only test framework

The starter wires both Pest and a `phpunit.xml`. We will keep `phpunit.xml` (Pest needs it) but write tests in Pest syntax. WordPress test integration (`yoast/wp-test-utils` or a custom `TestCase`) is deferred until Session 1 needs to test migrations.

### D7. Activation hook writes only a version option

The activation callback in `moonfarmer-reactions-lead-capture.php` will call `update_option('moonfarmer_reactions_lead_capture_db_version', '0')` and nothing else. Schema creation belongs to Session 1's migration service. A `'0'` value signals "no schema yet" to that future code.

### D8. `.distignore` defaults to excluding everything not needed at runtime

Exclude `node_modules`, `tests`, `phpunit.xml`, `playwright.config.ts`, `vite.config.js`, `package.json`, `package-lock.json`, `postcss.config.js`, `tailwind.config.js` (if accidentally added), `openspec`, `docs`, `.github`, `.git*`, `.distignore` itself, and `composer.lock`. Keep `vendor/` after `composer install --no-dev` runs in the packaging step (Session 12).

## Risks / Trade-offs

- **Risk**: Copying starter files line-by-line drags in subtle starter idioms. → Mitigation: a final `grep` step in tasks.md verifies zero starter strings remain, and the smoke test asserts `MOONFARMER_REACTIONS_LEAD_CAPTURE_VERSION` is defined.
- **Risk**: PHP 8.1 floor excludes some legacy WP hosts. → Mitigation: the plugin entry checks `PHP_VERSION_ID >= 80100` and shows an admin notice with the upgrade message instead of fatal-erroring. Site owners can downgrade-plan without losing the site.
- **Risk**: Vite with a single entry produces an awkward `dist/` shape for `wp_register_script`. → Mitigation: Session 3 will introduce a Vite manifest reader in the asset registrar; for now the build just produces files and we accept the dist layout.
- **Trade-off**: Choosing Preact over native DOM means a ~3 KB gzipped runtime cost before any widget code. We accept this for ergonomic reasons (the v1 plan committed to Preact, and the 15 KB budget already assumes it).
- **Trade-off**: Pest-only testing means contributors familiar with PHPUnit-style class tests need to adapt. Acceptable for a small team.

## Migration Plan

This is a greenfield scaffold — no existing Moonfarmer Reactions Lead Capture users, no data, no rollback concern. The "deploy" path is:

1. Land this change on the `main` branch (or a feature branch if the user prefers).
2. Run `composer install` and `npm install` locally.
3. Activate the plugin on a local WordPress and confirm no errors.
4. Move to Session 1 (`schema-and-migrations`).

Rollback is `git revert` or `git reset` — nothing leaves the working directory.

## Open Questions

- **Q1**: Should `composer.json` declare WordPress as a dev dependency for IDE/Pest support, or keep it out entirely and rely on a globally-loaded local WP? → Defer to Session 1; not blocking for an empty bootstrap.
- **Q2**: Vite version — pin to `^5` or match starter's `^4`? Leaning Vite 5 for modern Rollup; will pin at scaffold time.
- **Q3**: Do we want a `moonfarmer-reactions-lead-capture-pro` repo stub created in this session? → No. The pro plugin gets its own bootstrap session under the Pro Boundary Plan (Session 13).
