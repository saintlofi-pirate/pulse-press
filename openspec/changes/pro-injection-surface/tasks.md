## 1. PHP injection surface

- [x] 1.1 Extend `AdminServiceProvider::adminData()` (or equivalent payload builder) to include `tabs`, `metricCards`, `analyticsPanels`.
- [x] 1.2 Implement `publicTabs()` private method: build base array of five Free tabs with explicit `order` values, merge with `apply_filters('pulsepress_admin_tabs', [])`, dedupe by `id` (Free wins), sort by `order` ascending then `id` ascending.
- [x] 1.3 Apply `apply_filters('pulsepress_admin_metric_cards', [])` and pass through as `metricCards`.
- [x] 1.4 Apply `apply_filters('pulsepress_admin_analytics_panels', [])` and pass through as `analyticsPanels`.
- [x] 1.5 Add an `i18n.extension` block with `fallback` and `extensionTitle` strings to the payload.

## 2. SPA registry + ExtensionMount

- [x] 2.1 Create `resources/admin/extensions/registry.ts` with `ExtensionRegistry` class, `getRegistry()` accessor, `ExtensionContext` type, `useExtensionTick` Preact hook.
- [x] 2.2 Update `resources/admin/index.tsx` to instantiate the registry and expose `window.PulsePressAdmin = { registerTabRenderer, registerCardRenderer, registerPanelRenderer }` BEFORE calling `render()`.
- [x] 2.3 Create `resources/admin/components/ExtensionMount.tsx` implementing the spec (lookup, mount, cleanup, try/catch, fallback).
- [x] 2.4 Update `resources/admin/types.ts` to add `ExtensionTab`, `ExtensionMetricCard`, `ExtensionAnalyticsPanel` types and extend `PulsePressAdminData` with `tabs`, `metricCards`, `analyticsPanels`, and an `i18n.extension` block. Add `window.PulsePressAdmin` declaration.

## 3. SPA wiring

- [x] 3.1 Replace hardcoded `TAB_IDS` in `App.tsx` with `adminData.tabs`. Read sorted list, compute `KNOWN` section map, render `<Known ... />` when id matches, otherwise `<ExtensionMount kind="tab" id={…} />`.
- [x] 3.2 Update `hashToTab()` to accept dynamic tab ids.
- [x] 3.3 Extend `AnalyticsSection.tsx` to render Pro metric cards (after Free's four) and Pro analytics panels (after top-posts table).
- [x] 3.4 Add CSS rules for `.pulsepress-extension-mount`, `.pulsepress-extension-fallback` in `resources/admin/styles/admin.css`.

## 4. Tests

- [x] 4.1 `tests/Unit/AdminInjectionPayloadTest.php`: cover payload assembly — empty filters yield empty `metricCards`/`analyticsPanels` and five Free tabs; Pro tab inserts by order; conflict on `analytics` id is ignored; filters' return values are passed verbatim.
- [x] 4.2 Confirm full Pest suite still passes (`./vendor/bin/pest`).

## 5. Manual verification

- [x] 5.1 Reload Settings → PulsePress with no mock plugin → confirm SPA renders identically to baseline.
- [x] 5.2 Drop a temporary `wp-content/mu-plugins/pulsepress-pro-mock.php` that registers a tab + metric card + panel via the three filters; load a small JS that calls `window.PulsePressAdmin.registerTabRenderer/CardRenderer/PanelRenderer`. Confirm extra tab appears at the expected position, extension card and panel render with mock content.
- [x] 5.3 Remove the mock JS but leave the PHP filters active → confirm fallback UI renders for the tab and the panel (and the card if `renderJs` was set).
- [x] 5.4 Delete the mock mu-plugin → confirm baseline UI returns.

## 6. Docs

- [x] 6.1 Update `docs/hooks-and-filters.md`: promote `pulsepress_admin_metric_cards` and `pulsepress_admin_analytics_panels` from "planned" to "shipped"; add `pulsepress_admin_tabs` row.
- [x] 6.2 Add a short section to `docs/pulsepress-v1-plan.md` (or a new `docs/pro-extension-api.md`) describing the JS contract: `window.PulsePressAdmin.register*Renderer` signature, `ExtensionContext` shape, cleanup convention, fallback behaviour.

## 7. Commit

- [x] 7.1 `git add` the change files only after all tasks above are checked off and tests pass; commit with message `Session 9.6: Pro injection surface (PHP filters + JS renderer registry)`.
