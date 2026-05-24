## Context

The widget is mounted by `resources/widget/index.ts::mountAll()` which scans for every `[data-moonfarmer-reactions-lead-capture-widget]` element on `DOMContentLoaded`. That selector-based approach means we can put the container anywhere in the DOM — the front-end JS doesn't care whether it came from `the_content` auto-insert, the Gutenberg block, the shortcode, or hand-written markup. Session 7's whole job is to give authors more ways to insert that container.

Two product principles drive every choice here:

1. **One source of truth for the container HTML.** A `WidgetMarkup::container()` helper produces the markup. Auto-insert calls it. The block render_callback calls it. The shortcode callback calls it. Updates land in one place.
2. **No editor build step.** Pulling Webpack + `@wordpress/scripts` into the plugin to compile a single block-edit.js is high-cost low-value for a static block. PHP-rendered dynamic block + the editor's automatic server-side preview is enough.

Gap decision 8 D3 spelled out the precedence rule: shortcode > block > post-meta > global, with auto-insert skipping posts that already have either marker. Session 7 implements the skip-rule; per-post override comes in a later slice when the post meta box lands.

## Goals / Non-Goals

**Goals:**

- One place in code that emits the widget container.
- Block insertable via slash menu in the post/page editor.
- Shortcode usable in any context that runs `do_shortcode()` (post content, widgets, page builders).
- Auto-insert skips a post that already has the block or shortcode — visitors never see two widgets stacked.
- Both placements render server-side and integrate with the existing Preact mount.

**Non-Goals:**

- No block-edit JavaScript. The editor uses the dynamic render output as preview.
- No `block-styles` registration for design presets. Settings (Session 6) already drive presets globally; per-block design overrides are a Pro feature.
- No inspector controls in the editor. The block has zero settings in this slice; future iterations may add controls for the design preset / icon style override.
- No shortcode-attributes parser for design overrides. Same as above.
- No support for the `[moonfarmer-reactions-lead-capture]` shortcode inside admin-side WordPress emails — `do_shortcode` runs in admin, but that's outside our scope.

## Decisions

### D1. `block.json` + dynamic render_callback, no edit JS

```json
{
  "$schema": "https://schemas.wp.org/trunk/block.json",
  "apiVersion": 3,
  "name": "moonfarmer-reactions-lead-capture/reactions",
  "title": "Moonfarmer Reactions Lead Capture reactions",
  "category": "widgets",
  "icon": "thumbs-up",
  "description": "Insert the Moonfarmer Reactions Lead Capture reaction widget at this location.",
  "supports": { "html": false, "align": ["wide", "full"] },
  "attributes": {
    "postId": { "type": "integer" }
  },
  "render": "file:./render.php"
}
```

Setting `render` to a `file:` reference lets WordPress 6.1+ load the PHP render file directly. The file calls our `ReactionsBlock::render()` static method. The block has no `script`, `viewScript`, `editorScript`, or `style` — the widget script + style come from `WidgetServiceProvider` on the front end, and the editor sees the rendered HTML through the standard dynamic-block preview mechanism.

### D2. `WidgetMarkup::container(int $postId): string` is the single helper

Returns:

```html
<div class="moonfarmer-reactions-lead-capture" data-moonfarmer-reactions-lead-capture-widget data-moonfarmer-reactions-lead-capture-post-id="42"></div>
```

The HTML is built via:

```php
$attrs = apply_filters('moonfarmer_reactions_lead_capture_widget_container_attrs', [
    'class' => 'moonfarmer-reactions-lead-capture',
    'data-moonfarmer-reactions-lead-capture-widget' => '',
    'data-moonfarmer-reactions-lead-capture-post-id' => (string) $postId,
], $postId);
return sprintf('<div%s></div>', self::attrString($attrs));
```

Pro hooks the filter to add `data-moonfarmer-reactions-lead-capture-variant="a"`, etc. The filter receives the post id for context.

### D3. Duplicate-detect is a simple substring match

```php
if (str_contains($content, 'data-moonfarmer-reactions-lead-capture-widget')) {
    return $content; // already present
}
```

Cheap, correct, no parsing. Catches both block-rendered output and shortcode-rendered output because both go through `WidgetMarkup::container()`. False positives are extremely unlikely (the attribute string is plugin-specific).

### D4. Shortcode normalisation

`[moonfarmer-reactions-lead-capture]` with no attributes — current post id.
`[moonfarmer-reactions-lead-capture post_id="123"]` — explicit post id; validates the post exists and is publicly viewable (same gate as the REST endpoints).

Invalid post id → empty string (silent fail). The reasoning: shortcode rendering is content-time; throwing or printing an error would break the page. Failing silently is more author-friendly.

### D5. Front-end JS keeps the multi-mount logic

`resources/widget/index.ts::mountAll()` already does `document.querySelectorAll('[data-moonfarmer-reactions-lead-capture-widget]')`. We don't change anything. Each container with a different `data-moonfarmer-reactions-lead-capture-post-id` mounts its own `<ReactionBar>` with that id.

### D6. Block registers on `init`, not `plugins_loaded`

`register_block_type` requires the block-supports infrastructure which loads on `init`. The `BlockServiceProvider::boot()` hooks `add_action('init', [$this, 'registerBlock'])`. Same hook for `add_shortcode`.

### D7. Block category is `widgets`, not custom

Using a built-in category keeps the block discoverable without adding a custom `block-category` filter. `widgets` is the closest fit — alongside Tag Cloud, Categories List, etc.

### D8. Block `supports.html: false`

Disables the "Edit as HTML" affordance in the editor. The dynamic render is the only authoritative output; allowing HTML editing would let an author drift from the rendered shape and break the duplicate-detect.

## Risks / Trade-offs

- **Risk**: a theme renders content twice (e.g., header + footer excerpt), causing the auto-insert to double-append. → Mitigation: duplicate-detect catches it on second pass. The first pass adds; the second pass sees the marker and skips. Acceptable.
- **Risk**: a custom page builder strips data attributes from rendered blocks. → Mitigation: the front-end JS just won't mount on a stripped container. Documented gotcha; no easy fix without coupling to specific builders.
- **Risk**: a user inserts the block in a reusable template part rendered N times → N widgets stacked. → Mitigation: duplicate-detect inside one `the_content` call catches them. Across template parts a developer can hook `moonfarmer_reactions_lead_capture_widget_container_attrs` to namespace by parent context.
- **Trade-off**: no inspector controls in the editor. Acceptable — the global settings page (Session 6) drives everything; per-block override is a Pro/future feature.
- **Trade-off**: no block-edit JS means the editor preview lags real WP user expectations (no "Block: Moonfarmer Reactions Lead Capture reactions" sidebar with options). Acceptable for v1; the rendered preview is informative enough.

## Migration Plan

No data migration. The block becomes available in the editor immediately after activation. Authors with existing posts using `the_content` auto-insert see no change.

## Open Questions

- **Q1**: should the block also register a "preview placeholder" via `editorScript` so authors see a stable visual even when the dynamic render fails? → **Decided no for v1.** The render only fails if the post id is invalid, which can't happen in the editor (the block resolves to the current post).
- **Q2**: should the shortcode support `[moonfarmer-reactions-lead-capture design="expressive"]` to override the global design preset? → **Deferred to Pro.** Global settings drive design in Free.
