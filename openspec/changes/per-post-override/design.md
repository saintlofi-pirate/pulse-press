## Context

The widget today decides whether to render based on a single check in `WidgetServiceProvider::maybeAppendWidget`: `is_singular()` + `pulsepress_widget_auto_insert($default, $postType)`. Block and shortcode render unconditionally as long as the post is public. There is no per-post escape hatch and no "Pages should never see the widget, even via shortcode" hard rule.

The precedence rule from gap 8 D3 is:

1. Post-level meta wins (`'on'` always shows, `'off'` always hides).
2. Hide-on list beats everything except an explicit `'on'`.
3. Auto-insert list grants permission to auto-insert, not to render via block/shortcode (block/shortcode work everywhere unless excluded by 1 or 2).
4. Default mode is `'auto'` — follow the above chain.

The cleanest way to enforce this consistently is a single resolver that every render path consults.

## Goals / Non-Goals

**Goals:**

- One method, `VisibilityResolver::shouldRender($postId, $context): bool`, called by every render path.
- Three contexts so the resolver can apply slightly different rules per call site: `'auto'` (auto-insert), `'block'`, `'shortcode'`.
- Post meta box accessible via classic editor + block editor (via `register_post_meta` with `show_in_rest`).
- Dynamic post-type list everywhere — no more hardcoded `[Posts, Pages]`.
- "Never show on" setting that genuinely suppresses every render path (auto, block, shortcode) on those post types.

**Non-Goals:**

- No category/tag-level overrides. Pro feature.
- No scheduled visibility ("show from date X to Y"). Pro feature.
- No A/B-test cohorting per visitor. Pro feature.
- No bulk-edit UI in the post list table. Future polish.

## Decisions

### D1. Precedence rules, explicit

```
mode = get_post_meta($postId, '_pulsepress_widget_state', true) ?: 'auto';
mode = apply_filters('pulsepress_visibility_mode', $mode, $postId, $context);

if ($mode === 'off') return false;
if ($mode === 'on')  return true;  // explicit on beats hide-on list
// $mode === 'auto'

if (in_array($postType, $settings['hide_on_post_types'], true)) return false;

return match ($context) {
    'block', 'shortcode' => true,    // manual placement is the admin's intent
    'auto'               => in_array($postType, $settings['auto_insert_post_types'], true),
};
```

**Rationale**: explicit on beats hide list. An admin who explicitly toggles "Always show" on a post intends to override site-wide rules. The hide list catches the "don't accidentally render via shortcode" case while still respecting per-post overrides.

### D2. Meta key naming and storage

`_pulsepress_widget_state` (leading underscore so WordPress hides it from the default Custom Fields meta box). Value is a string: `'auto'`, `'on'`, or `'off'`. Missing/empty is treated as `'auto'`.

Registered via `register_post_meta` for every public post type with:

- `single: true`
- `type: 'string'`
- `default: 'auto'`
- `show_in_rest: true`
- `auth_callback: fn($allowed, $meta_key, $object_id) => current_user_can('edit_post', $object_id)`
- `sanitize_callback: VisibilityResolver::sanitiseMode`

### D3. Single resolver class, container-bound

`VisibilityResolver` is constructed with a `SettingsRepository` so it can read `hide_on_post_types` / `auto_insert_post_types` once per request (the repository memoises). The resolver itself stays stateless beyond that dependency.

Bound as a singleton in `AdminServiceProvider::register()` (or a new tiny provider — but adding to AdminServiceProvider keeps the count down). All consumers (block, shortcode, widget service provider) resolve from the container.

### D4. Meta box renders on every public post type

The meta box appears on every `get_post_types(['public' => true])` post type by default. The `pulsepress_meta_box_post_types` filter lets a site narrow or extend the list (e.g., exclude `attachment`).

### D5. Dynamic post-type choices for the admin SPA

`AdminServiceProvider::maybeEnqueueAssets` payload gets `choices.post_types` shaped as `{slug: label}` from `get_post_types(['public' => true], 'objects')`. The admin SPA's DisplaySection renders both "Auto-insert on" and "Never show on" as CheckboxListField components using this map.

The settings sanitiser (`Settings::stringArray`) now validates `auto_insert_post_types` and `hide_on_post_types` against `array_keys(get_post_types(['public' => true]))`, dropping any value that isn't a current public post type.

### D6. WidgetMarkup::container guards too

Even when the block or shortcode handler skips the resolver check (e.g. a buggy third-party caller), `WidgetMarkup::container` does its own resolver check and returns empty string when the answer is no. Defense-in-depth — one location enforces the rule.

### D7. Block + shortcode hide gracefully

When the resolver returns false, the block and shortcode return an empty string. The block's editor preview shows the post's actual state (so an admin who flips the meta to "off" sees nothing in the preview either). This is by design — preview-truth-is-render-truth keeps surprises minimal.

### D8. SettingsRepository sanitisation does not depend on WP runtime

`hide_on_post_types` sanitisation passes the raw incoming list through the settings sanitiser, which today accepts string arrays without per-value validation. Validation against current public post types runs inside `Settings::sanitise` only when `get_post_types` is available; in test env, the field is treated as opaque string array. We accept this trade-off — admin UI is the input source, and the SPA only offers values from the dynamic choices map.

## Risks / Trade-offs

- **Risk**: A post is saved with `'on'` for a post type that's later added to "Never show on" — the per-post override still wins. → Mitigation: this matches D1 by design. The admin can fix by editing the post meta.
- **Risk**: `register_post_meta` for every public post type bloats the REST schema. → Mitigation: each registration is a tiny array entry; even 50 post types is < 5 KB of schema.
- **Risk**: The meta box renders in the post editor sidebar but a Gutenberg-block-editor user uses the document-side panel for meta. → Mitigation: standard `add_meta_box` is rendered by Gutenberg in the right sidebar under "Block" → no special handling needed.
- **Risk**: Filter `pulsepress_visibility_mode` lets Pro/3rd parties override the decision, including overriding the admin's explicit `'on'`/`'off'`. → Mitigation: documented. The filter is intended as a last-mile policy hook; admins should know that hooks beat their UI choices.
- **Trade-off**: No bulk edit in the post list. Acceptable — meta-box per-post is enough for v1; bulk edit is a future polish.

## Migration Plan

No data migration. Existing posts have no meta — resolver treats them as `'auto'`. Existing `auto_insert_post_types` setting still controls auto-insert behaviour.

Rollback: `git revert` — meta keys remain harmlessly in the DB.

## Open Questions

- **Q1**: should "Always hide" suppress the inline capture form too? → **Decided yes** — the form is part of the widget; if the widget is hidden, the form is hidden.
- **Q2**: should the meta box show in the post list table as a column? → **Deferred to polish.** Just-in-time editing via the meta box is enough for v1.
