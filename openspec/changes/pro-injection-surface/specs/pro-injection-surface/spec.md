## ADDED Requirements

### Requirement: PHP filter for admin tabs

`AdminServiceProvider` SHALL emit an admin payload `tabs` array. The array SHALL be assembled by merging Free's five built-in tabs (`display`, `analytics`, `reactions`, `capture`, `privacy`, each with a numeric `order`) with whatever `apply_filters('pulsepress_admin_tabs', [])` returns. Entries SHALL be deduplicated by `id` (Free wins on conflict). The merged array SHALL be sorted ascending by `order`, ties broken by `id` alphabetical.

#### Scenario: Free tabs render unchanged when no extension registered
- **GIVEN** no plugin attaches to `pulsepress_admin_tabs`
- **WHEN** the admin page renders
- **THEN** the payload's `tabs` SHALL contain exactly five entries in the order `display, analytics, reactions, capture, privacy`

#### Scenario: Pro tab inserted by order
- **GIVEN** a plugin returns `[['id' => 'esp', 'label' => 'ESP', 'order' => 25]]` from `pulsepress_admin_tabs`
- **WHEN** the admin payload is built
- **THEN** the `tabs` array SHALL contain six entries with `esp` positioned between `analytics` (order 20) and `reactions` (order 30)

#### Scenario: Conflict on built-in id keeps Free's entry
- **GIVEN** a plugin returns `[['id' => 'analytics', 'label' => 'Hijacked', 'order' => 1]]`
- **WHEN** the admin payload is built
- **THEN** the `analytics` tab SHALL retain Free's label and order; the hijack attempt SHALL be silently dropped

### Requirement: PHP filter for admin metric cards

`AdminServiceProvider` SHALL emit `metricCards` in the admin payload as the result of `apply_filters('pulsepress_admin_metric_cards', [])`. Free SHALL NOT append its own cards to this array — Free's four built-in cards render via the SPA's static component composition. Pro-supplied entries SHALL be serialised as-is.

#### Scenario: No Pro cards by default
- **GIVEN** no plugin attaches to `pulsepress_admin_metric_cards`
- **WHEN** the admin payload is built
- **THEN** `metricCards` SHALL be an empty array

#### Scenario: Pro card carried through verbatim
- **GIVEN** a plugin returns `[['id' => 'compare', 'title' => 'vs prior', 'value' => '+12%', 'renderJs' => 'compare_card', 'data' => ['delta' => 0.12]]]`
- **WHEN** the admin payload is built
- **THEN** `metricCards[0]` SHALL contain all five fields exactly as supplied

### Requirement: PHP filter for analytics panels

`AdminServiceProvider` SHALL emit `analyticsPanels` in the admin payload as the result of `apply_filters('pulsepress_admin_analytics_panels', [])`. Order SHALL preserve filter return order.

#### Scenario: Default empty
- **GIVEN** no plugin attaches to `pulsepress_admin_analytics_panels`
- **WHEN** the admin payload is built
- **THEN** `analyticsPanels` SHALL be an empty array

### Requirement: JS renderer registry exposed on window

The admin SPA SHALL expose a `window.PulsePressAdmin` object with three registration methods: `registerTabRenderer(id, fn)`, `registerCardRenderer(id, fn)`, `registerPanelRenderer(id, fn)`. The registry SHALL be created before the SPA mounts so Pro scripts loaded after Free's bundle can call the methods synchronously.

#### Scenario: Pro registers a tab renderer
- **GIVEN** Free's admin bundle has loaded
- **WHEN** Pro calls `window.PulsePressAdmin.registerTabRenderer('esp', fn)`
- **THEN** `getRegistry().getTab('esp')` SHALL return `fn`

#### Scenario: Late registration triggers re-render
- **GIVEN** Free's SPA has mounted and `ExtensionMount` for tab id `esp` is showing the fallback
- **WHEN** Pro calls `window.PulsePressAdmin.registerTabRenderer('esp', fn)` after mount
- **THEN** the `pulsepress:extension-registered` `CustomEvent` SHALL fire on `window` and the `ExtensionMount` SHALL re-render with Pro's renderer

### Requirement: ExtensionMount mounts renderers and cleans up

The `ExtensionMount` component SHALL look up its renderer by `(kind, id)` and call it with `(rootEl, ctx)`. If the renderer returns a function, the SPA SHALL invoke that function on unmount. If the renderer throws, the SPA SHALL log to `console.error` and render the fallback UI without breaking Free's tree.

#### Scenario: Renderer returns cleanup
- **GIVEN** Pro registers a card renderer that returns `() => cleanup()`
- **WHEN** Free unmounts the `ExtensionMount`
- **THEN** the cleanup function SHALL be invoked once

#### Scenario: Renderer throws on mount
- **GIVEN** Pro registers a panel renderer that throws synchronously
- **WHEN** `ExtensionMount` calls it
- **THEN** the error SHALL be caught and logged; the fallback UI SHALL render in place of the panel

### Requirement: Graceful fallback when renderer missing

When `ExtensionMount` cannot find a renderer for its `(kind, id)`, it SHALL render an accessible fallback element with `role="status"` and the i18n string `extension.fallback` (default: "This section is provided by another plugin that didn't load. Try refreshing the page.").

#### Scenario: Pro PHP filter without Pro JS
- **GIVEN** Pro registers a tab via `pulsepress_admin_tabs` but its bundle fails to load
- **WHEN** the admin opens that tab
- **THEN** the SPA SHALL render the fallback UI with the localized copy, not a blank panel or error
