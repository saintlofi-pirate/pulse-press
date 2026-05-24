## Context

Free's admin SPA is a single Preact app. Pro is a separate plugin that may or may not be installed. Pro's job, by the v1 plan, is to attach via PHP/JS hooks without modifying Free's tree.

Two parts to a working injection surface:

1. **PHP-side data filters** so Pro can declare "I have a Compare-Windows panel, here's its id and data" without touching Free's controllers. Pro returns shapes from filters; Free serialises them into the localized admin payload.
2. **JS-side renderer registry** so Pro can attach DOM-render logic to those declared shapes. Pro mounts its own components (its own Preact/React tree, its own state) into divs Free creates.

The key constraint: **Free's SPA must not care what Pro renders**. Pro brings its own JS bundle, its own components, its own state. Free's responsibility is to *create the slot* and call Pro's renderer with the slot element + context. That's it.

A secondary constraint: **partial activation must degrade gracefully**. If Pro declares a tab via PHP filter but its JS bundle fails to load (network error, missing dep), Free should render a calm fallback rather than a broken tab.

## Goals / Non-Goals

**Goals:**

- One filter per injection point: `moonfarmer_reactions_lead_capture_admin_tabs`, `moonfarmer_reactions_lead_capture_admin_metric_cards`, `moonfarmer_reactions_lead_capture_admin_analytics_panels`. Free reads each, serialises into the admin payload, and the SPA loops them.
- One JS registry per injection point: `registerTabRenderer / registerCardRenderer / registerPanelRenderer`. Pro registers its renderers synchronously when its bundle loads.
- The registry is reactive: if Pro registers after Free has mounted, Free re-renders via a custom-event listener.
- Defensive fallback: when a slot exists in the payload but no renderer is registered, the SPA renders a calm "This section is provided by another plugin that didn't load. Try refreshing the page." copy.
- No coupling to a specific Pro framework. Pro can use Preact, React, vanilla DOM, Vue — the renderer signature is `(root: HTMLElement, ctx) => void | (() => void)` where the optional return is a cleanup callback.

**Non-Goals:**

- No `moonfarmer_reactions_lead_capture_admin_settings_panels` filter. Settings fields rely on Free's form primitives (ToggleField, RadioField, etc.); the data plumbing for that is heavier than a render slot. Pro's settings fields are deferred — Pro can ship a custom tab via `moonfarmer_reactions_lead_capture_admin_tabs` and render whatever it wants inside.
- No bidirectional data flow. Free emits initial state via the payload; updates from Pro back into Free's state are out of scope (Pro communicates via its own REST endpoints).
- No security boundary inside the SPA. Extension renderers run with the same privileges as Free's code — they're Pro's own code anyway, loaded by Pro's enqueue. Capability gating happens at Pro's REST routes.
- No "extension marketplace". Each Pro plugin enqueues its own JS; there is no in-product discovery.

## Decisions

### D1. Extension shapes are plain JSON-serialisable arrays

Every extension entry is an associative array PHP returns:

```php
// Tab
['id' => 'esp', 'label' => 'ESP sync', 'order' => 50]

// Metric card without custom renderer (Pro doesn't need JS)
['id' => 'esp_synced', 'title' => 'ESP synced', 'value' => '247', 'helper' => '…', 'emphasis' => false]

// Metric card WITH custom renderer (Pro renders inside the slot)
['id' => 'compare_card', 'title' => 'vs previous 30 days', 'value' => '', 'helper' => '', 'renderJs' => 'compare_card', 'data' => ['previous' => 1234, 'current' => 1567]]

// Analytics panel (always custom-rendered)
['id' => 'compare_windows', 'title' => 'Window comparison', 'helper' => '…', 'data' => […], 'renderJs' => 'compare_windows']
```

The `renderJs` field is the **id** the JS registry will look up — Pro calls `registerCardRenderer('compare_card', fn)` to claim the slot. (Renaming from "renderJs as a function name" to "renderJs as a registry key" decouples the PHP from the JS module shape.)

### D2. JS registry is a thin singleton

```ts
type Renderer = (root: HTMLElement, ctx: ExtensionContext) => void | (() => void);

class ExtensionRegistry {
  private tabs   = new Map<string, Renderer>();
  private cards  = new Map<string, Renderer>();
  private panels = new Map<string, Renderer>();

  registerTabRenderer   = (id: string, fn: Renderer) => { this.tabs.set(id, fn);   this.notify(); };
  registerCardRenderer  = (id: string, fn: Renderer) => { this.cards.set(id, fn);  this.notify(); };
  registerPanelRenderer = (id: string, fn: Renderer) => { this.panels.set(id, fn); this.notify(); };

  getTab   = (id: string) => this.tabs.get(id);
  getCard  = (id: string) => this.cards.get(id);
  getPanel = (id: string) => this.panels.get(id);

  private notify = () => {
    window.dispatchEvent(new CustomEvent('moonfarmer-reactions-lead-capture:extension-registered'));
  };
}

// At the top of index.tsx, before mounting:
const registry = new ExtensionRegistry();
window.MoonfarmerReactionsLeadCaptureAdmin = {
  registerTabRenderer:   registry.registerTabRenderer,
  registerCardRenderer:  registry.registerCardRenderer,
  registerPanelRenderer: registry.registerPanelRenderer,
};
```

The internal `registry` instance is consumed by `ExtensionMount` via a module-level export (`getRegistry()`). The public API on `window` exposes only the registration methods — Pro can't snoop other plugins' renderers.

### D3. `ExtensionMount` component is the universal slot

```tsx
interface Props {
  kind: 'tab' | 'card' | 'panel';
  id: string;
  data?: unknown;
  fallback?: string;
}

export function ExtensionMount({ kind, id, data, fallback }: Props) {
  const ref = useRef<HTMLDivElement | null>(null);
  const tick = useExtensionTick(); // re-render on registration
  const renderer = useMemo(() => getRegistry().getRenderer(kind, id), [kind, id, tick]);

  useEffect(() => {
    if (!ref.current || !renderer) return;
    const cleanup = renderer(ref.current, { id, kind, data, adminData: getAdminData() });
    return () => { if (typeof cleanup === 'function') cleanup(); };
  }, [renderer, id, data]);

  if (!renderer) {
    return <div class="moonfarmer-reactions-lead-capture-extension-fallback" role="status">{fallback ?? defaultFallback()}</div>;
  }
  return <div ref={ref} class="moonfarmer-reactions-lead-capture-extension-mount" data-extension-id={id} data-extension-kind={kind} />;
}
```

`useExtensionTick` is a Preact hook that increments on every `moonfarmer-reactions-lead-capture:extension-registered` event, forcing components to re-evaluate `getRenderer`.

### D4. Tab order is `order` field, sorted ascending

Free's tabs ship with explicit orders: display 10, analytics 20, reactions 30, capture 40, privacy 50. Pro picks any number to insert; e.g. ESP at 25 puts it between Analytics and Reactions. The SPA sorts ascending, breaks ties by id alphabetically.

### D5. Hash routing honours every registered tab id

`hashToTab()` previously hardcoded `TAB_IDS`. After this slice it reads from the dynamic tabs list. If someone visits `#esp` and the ESP tab is registered, that tab activates. If the tab is gone (Pro deactivated), default falls back to the first tab.

### D6. Renderers receive ExtensionContext, not raw `adminData`

```ts
interface ExtensionContext {
  id: string;
  kind: 'tab' | 'card' | 'panel';
  data: unknown; // the entry's `data` field from PHP (renderer-defined shape)
  adminData: MoonfarmerReactionsLeadCaptureAdminData; // full admin context (read-only — Pro shouldn't mutate)
}
```

This standard context lets Pro renderers be portable across slot types and avoids each call site rebuilding a context object.

### D7. Defensive fallback copy is i18n-driven

`adminData.i18n.extension.fallback` carries the default fallback string ("This section is provided by another plugin that didn't load. Try refreshing the page."). The `<ExtensionMount fallback="…">` prop overrides per-slot when context allows a more specific message.

### D8. PHP payload filtering preserves order and dedups by id

```php
private function publicTabs(): array
{
    $base = [
        ['id' => 'display',   'label' => $this->i18n()['tabs']['display'],   'order' => 10],
        ['id' => 'analytics', 'label' => $this->i18n()['tabs']['analytics'], 'order' => 20],
        ['id' => 'reactions', 'label' => $this->i18n()['tabs']['reactions'], 'order' => 30],
        ['id' => 'capture',   'label' => $this->i18n()['tabs']['capture'],   'order' => 40],
        ['id' => 'privacy',   'label' => $this->i18n()['tabs']['privacy'],   'order' => 50],
    ];
    $extra = (array) apply_filters('moonfarmer_reactions_lead_capture_admin_tabs', []);
    $all   = $this->mergeById(array_merge($base, $extra));
    usort($all, fn ($a, $b) => $a['order'] <=> $b['order']);
    return $all;
}
```

`mergeById` deduplicates: if Pro returns a tab with `id => 'analytics'`, Free's existing entry wins (Pro is additive, not destructive). Extra fields on extension entries (like `data`, `renderJs`) survive the merge.

### D9. SPA pre-existing sections still render via id check

`App.tsx`'s `renderPanel`:

```tsx
const KNOWN: Record<string, (props: SectionProps) => JSX.Element> = {
  display:   DisplaySection,
  analytics: AnalyticsSection,
  reactions: ReactionsSection,
  capture:   CaptureSection,
  privacy:   PrivacySection,
};

function renderPanel(activeTab: string) {
  const Known = KNOWN[activeTab];
  if (Known) return <Known {...sectionProps} />;
  return <ExtensionMount kind="tab" id={activeTab} fallback={i18n.extension.fallback} />;
}
```

Free's hardcoded sections stay first-class. Pro's tabs go through the extension path.

### D10. AnalyticsSection mounts Pro additions in stable positions

After Free's four metric cards: extension metric cards (in the order PHP returned them).

After Free's top-posts table: extension analytics panels.

The sentiment insight callout and the daily chart stay between Free's cards and Free's top-posts table (unchanged ordering); Pro panels go below the table so Pro doesn't accidentally break Free's narrative flow.

## Risks / Trade-offs

- **Risk**: Pro renderer throws → crashes Free's React tree. → Mitigation: ExtensionMount wraps the renderer call in a try/catch and logs to `console.error`. Falls back to the same "didn't load" copy. Pro's error doesn't take down Free.
- **Risk**: Pro renders a memory-leaky component that doesn't clean up. → Mitigation: renderer protocol allows a cleanup return value; the example docs recommend Pro return its unmount function. Free invokes it on `useEffect` cleanup. Pro that doesn't cleanly unmount eventually shows up in profiles; documented gotcha.
- **Risk**: Pro registers a card with the same `id` as a Free metric card. → Mitigation: Free's cards use names not in any Pro namespace (`total_reactions`, `total_captures`, `sentiment_rate`, `capture_rate`). Pro is expected to namespace (e.g. `moonfarmer-reactions-lead-capturepro_compare`). No hard enforcement; would only mis-render Pro's card.
- **Risk**: A Pro tab is registered via filter but never registers its JS renderer (Pro bundle failed). → Mitigation: that's exactly what the fallback UI is for. Admin sees "didn't load" and refreshes; if persistent, deactivates Pro.
- **Risk**: Extension contexts grow over time (carrying more `adminData` fields than necessary). → Mitigation: `adminData` is already shallow; we don't deep-clone; Pro shouldn't mutate. Documented.
- **Trade-off**: No settings-panels injection in this slice. Pro that wants to add settings fields ships its own tab. Acceptable for v1 Pro.

## Migration Plan

No data migration. Defaults preserve existing UI. Rollback is `git revert`.

For deployment safety:

1. Land the change.
2. Visit Settings → Moonfarmer Reactions Lead Capture with no Pro plugin → confirm the SPA renders identically to today.
3. Drop a hand-coded `mu-plugins/moonfarmer-reactions-lead-capture-pro-mock.php` that registers an extra tab + metric card + analytics panel via the filters; load a small JS in the same file that calls `window.MoonfarmerReactionsLeadCaptureAdmin.registerTabRenderer('test_tab', ...)`. Confirm the extra tab + card + panel render correctly.
4. Deactivate the mock file → confirm the SPA returns to its baseline.

## Open Questions

- **Q1**: should `ExtensionContext` include a `dispatch(event, payload)` channel back to Free? → **Decided no for v1.** Pro talks to Free through PHP filters; if Pro needs to push state to Free, it does so via Pro's own REST endpoint that Pro's own JS calls.
- **Q2**: should we also expose `unregister*Renderer` so Pro can hot-swap implementations? → **Decided no.** Renderer registration happens once per page load. Replacing a renderer at runtime is a Pro-internal concern (Pro's renderer can read from a module-level variable that Pro updates).
- **Q3**: should the fallback copy be configurable by Pro (so Pro can say "ESP sync didn't load")? → **Decided yes** via the `fallback` prop on `ExtensionMount`; we'll expose this in the rendering path so PHP can override per-slot via the entry's `fallback` field. Not a v1 blocker; falls out naturally from the prop existing.
