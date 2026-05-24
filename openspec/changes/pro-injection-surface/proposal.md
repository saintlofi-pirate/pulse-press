## Why

The recently expanded `docs/moonfarmer-reactions-lead-capture-v1-plan.md §Pro Extension Seams` lists the hooks Pro is expected to attach through, but half of them have no implementation on Free's side. `moonfarmer_reactions_lead_capture_admin_tabs`, `moonfarmer_reactions_lead_capture_admin_metric_cards`, `moonfarmer_reactions_lead_capture_admin_analytics_panels` exist only as filter documentation; the SPA does not yet read them. Without this slice, a Pro plugin can register tabs and panels via PHP filter and *nothing happens* — the SPA hardcodes its tab list and panel layout. The injection surface needs to be real before Pro starts shipping.

Free remains generous: this slice only adds extension points, the existing UI keeps rendering identically when no Pro extension is registered. Privacy stays first-class: Pro-registered REST routes are gated independently; Free does not relay Pro data on the front-end widget. Hooks-first: the PHP filter side ships now; the JS renderer registry (`window.MoonfarmerReactionsLeadCaptureAdmin.registerTabRenderer`, `…CardRenderer`, `…PanelRenderer`) becomes the documented JS contract Pro mounts components against.

## What Changes

- `AdminServiceProvider::maybeEnqueueAssets` extends the `MoonfarmerReactionsLeadCaptureAdminData` payload with:
  - `tabs: list<{id, label, order}>` — Free's five tabs (display 10, analytics 20, reactions 30, capture 40, privacy 50) merged with anything returned by `apply_filters('moonfarmer_reactions_lead_capture_admin_tabs', $tabs)`. Sorted ascending by `order` before emission.
  - `metricCards: list<{id, title, value, helper, emphasis, renderJs?}>` — from `apply_filters('moonfarmer_reactions_lead_capture_admin_metric_cards', [])`. Free does not consume the filter itself; Pro fills it.
  - `analyticsPanels: list<{id, title, helper, data?, renderJs?}>` — from `apply_filters('moonfarmer_reactions_lead_capture_admin_analytics_panels', [])`.
- `resources/admin/index.tsx` exposes `window.MoonfarmerReactionsLeadCaptureAdmin` with three registration methods:
  - `registerTabRenderer(id: string, renderer: (root: HTMLElement, ctx: ExtensionContext) => void): void`
  - `registerCardRenderer(id: string, renderer: (root: HTMLElement, ctx: ExtensionContext) => void): void`
  - `registerPanelRenderer(id: string, renderer: (root: HTMLElement, ctx: ExtensionContext) => void): void`
- A `moonfarmer-reactions-lead-capture:extension-registered` `CustomEvent` fires on `window` after each registration so the SPA re-renders if Pro registers after Free has already mounted.
- Add `resources/admin/extensions/registry.ts` carrying typed `ExtensionRegistry`, `ExtensionContext` (carries `adminData`, the entry's id, and any `data` field set by PHP), and `useExtensionTick` Preact hook.
- Add `resources/admin/components/ExtensionMount.tsx` — props `{kind: 'tab'|'card'|'panel', id, fallback?, data?}`. Creates a div ref, looks up the renderer for that kind+id, invokes `renderer(div, ctx)` on mount and re-mount-on-rerender, and on unmount calls the renderer's optional returned cleanup function. Renders a calm fallback when no renderer is registered ("This section is provided by another plugin that didn't load. Try refreshing the page.").
- `resources/admin/App.tsx`:
  - Replaces hardcoded `TAB_IDS` with `adminData.tabs` (sorted by order; default tab is the first id in the sorted list, normally `display`).
  - For each tab, renders the matching Free section (`display`, `analytics`, `reactions`, `capture`, `privacy`) when the id is a known Free id; otherwise renders `<ExtensionMount kind="tab" id={tab.id} />`.
  - Hash routing honours every registered tab id, including Pro's.
- `resources/admin/sections/AnalyticsSection.tsx`:
  - After Free's four metric cards, appends one `<MetricCard>` per entry in `adminData.metricCards` that does not declare `renderJs`. Entries with `renderJs` render an `<ExtensionMount kind="card" id={card.id} data={card} />`.
  - After Free's top-posts table, renders one `<ExtensionMount kind="panel" id={panel.id} data={panel} />` per entry in `adminData.analyticsPanels`.
- Add CSS scaffolding for `.moonfarmer-reactions-lead-capture-extension-mount` (matches the surrounding visual rhythm) and `.moonfarmer-reactions-lead-capture-extension-fallback`.
- **BREAKING**: none. The existing UI renders the same when no extension is registered.

## Capabilities

### New Capabilities

- `pro-injection-surface`: defines the PHP filters (`moonfarmer_reactions_lead_capture_admin_tabs`, `moonfarmer_reactions_lead_capture_admin_metric_cards`, `moonfarmer_reactions_lead_capture_admin_analytics_panels`), the corresponding JS extension contract (`window.MoonfarmerReactionsLeadCaptureAdmin.register*Renderer`), the SPA loading flow, and the fallback UX for partial Pro activation.

### Modified Capabilities

- `admin-spa`: tab list is now dynamic (`adminData.tabs` instead of hardcoded `TAB_IDS`); Analytics tab renders Pro-injected cards + panels alongside Free's four cards.

## Impact

- **New files**: `resources/admin/extensions/registry.ts`, `resources/admin/components/ExtensionMount.tsx`, `tests/Unit/AdminInjectionPayloadTest.php`.
- **Modified files**: `app/Providers/AdminServiceProvider.php` (payload extension), `resources/admin/types.ts` (new payload fields + `MoonfarmerReactionsLeadCaptureAdmin` global), `resources/admin/index.tsx` (registry exposure), `resources/admin/App.tsx` (dynamic tabs), `resources/admin/sections/AnalyticsSection.tsx` (extra cards + panels), `resources/admin/styles/admin.css` (extension styling), `docs/hooks-and-filters.md` (promote `moonfarmer_reactions_lead_capture_admin_tabs` and `moonfarmer_reactions_lead_capture_admin_metric_cards` from "planned" to "shipped"; reinforce `moonfarmer_reactions_lead_capture_admin_analytics_panels` row).
- **REST API**: unchanged. Pro registers its own routes independently under `moonfarmer-reactions-lead-capture/v1/pro/*`.
- **Database**: unchanged.
- **Filters introduced**: `moonfarmer_reactions_lead_capture_admin_tabs`. Promoted to shipped: `moonfarmer_reactions_lead_capture_admin_metric_cards`, `moonfarmer_reactions_lead_capture_admin_analytics_panels` (already documented; SPA consumes them now).
- **JS contract introduced**: `window.MoonfarmerReactionsLeadCaptureAdmin` exposing `registerTabRenderer / registerCardRenderer / registerPanelRenderer`. The `moonfarmer-reactions-lead-capture:extension-registered` `CustomEvent` lets late registrations trigger a re-render.
- **Privacy**: no new data collected. Pro extensions decide their own privacy posture; Free relays whatever Pro returns through the filter verbatim (Pro is responsible for capability gates).
- **Performance**: SPA bundle gains ~1 KB gzipped (the registry + extension mount). PHP payload gains 3 extra arrays, empty by default — negligible.
- **Accessibility**: extension mounts are real `<div>` elements with `role="region"` and a labelled fallback. Pro renderers are responsible for the a11y inside their own content; the catalog will document the contract in a follow-up.
- **Free/Pro boundary**: this *is* the injection contract. Once shipped, Pro can build entirely against documented hooks. The plan's "if Pro can't be built against this, the gap is a missing Free hook" rule is now actionable.
