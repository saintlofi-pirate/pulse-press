## Why

Sessions 6 and 7 give admins coarse control: "auto-insert on these post types" and "place the widget here via block/shortcode." What they don't give is granular control — "skip this specific post" or "never let the widget appear on Pages, even by shortcode." Gap 8 D3 in `docs/gap-questions-and-session-tasks.md` codified the precedence rule (post-meta > global) but the implementation was deferred. This slice fills the gap and turns the settings page's hardcoded `[Posts, Pages]` checklist into a dynamic list of every public post type registered on the site.

Free remains generous: every public post type is a candidate for the widget; per-post overrides are an opt-in nicety, not a gate. Privacy stays first-class — no new data collected; meta keys are namespaced under `_moonfarmer_reactions_lead_capture_`. Accessibility lives in the post-editor meta box: real radio inputs with a visible legend and helper text, keyboard accessible. Hooks-first: every decision point is filterable.

## What Changes

- Add `Moonfarmer\ReactionsLeadCapture\Visibility\VisibilityResolver` with `shouldRender(int $postId, string $context): bool` and `mode(int $postId): string` (`'auto'|'on'|'off'`). Becomes the single source of truth for "should the widget appear on this post?". Consulted by:
  - `WidgetServiceProvider::maybeAppendWidget` (auto-insert path)
  - `Moonfarmer\ReactionsLeadCapture\Blocks\ReactionsBlock::render`
  - `Moonfarmer\ReactionsLeadCapture\Blocks\Shortcode::render`
  - `Moonfarmer\ReactionsLeadCapture\Blocks\WidgetMarkup::container` returns empty string when resolver says no
- Add post meta key `_moonfarmer_reactions_lead_capture_widget_state` (string, one of `'auto'|'on'|'off'`, default missing-means-`'auto'`). Saved via the standard post-save flow + REST `register_post_meta` so block-editor saves work alongside classic editor.
- Add `Moonfarmer\ReactionsLeadCapture\Admin\WidgetStateMetaBox` registered on every public post type via the `moonfarmer_reactions_lead_capture_meta_box_post_types` filter (default = `get_post_types(['public' => true])`). Renders a three-radio fieldset (Auto / Always show / Always hide) with helper copy.
- Extend `Settings`: add `hide_on_post_types` field (string[], default `[]`). Sanitised against the public post-type list.
- Extend `AdminServiceProvider`'s localized payload: `choices.post_types` is now a `{slug: label}` map produced by `get_post_types(['public' => true], 'objects')` so the SPA renders an accurate checklist regardless of which CPTs the site has.
- Extend Display section of the admin SPA:
  - "Auto-insert on" CheckboxListField uses the dynamic post-type list.
  - New "Never show on" CheckboxListField (separate from auto-insert) for hard-suppression — widget never renders on these post types even via shortcode/block.
- Update tests for the new precedence path and dynamic post-type rendering.
- **BREAKING**: none. Existing sites without the meta default to `auto` and follow current global settings. Existing `auto_insert_post_types` continues to work.

## Capabilities

### New Capabilities

- `visibility-precedence`: defines the resolver contract — input (post id, context), inputs consulted (post meta, hide list, auto-insert list), output, precedence rules (post-meta beats both globals; hide list beats auto-insert; default `auto` = follow auto-insert list).

### Modified Capabilities

- `reaction-widget`: auto-insert now consults the resolver instead of inlining the post-type check.
- `block-shortcode-placement`: block + shortcode return empty string when the resolver says no.
- `settings-api`: new `hide_on_post_types` field; `choices.post_types` now part of the GET response so the SPA renders a dynamic post-type list.
- `admin-spa`: Display section gains a "Never show on" CheckboxListField and the existing "Auto-insert on" uses the dynamic post-type list instead of the hardcoded `[Posts, Pages]`.

## Impact

- **New files**: `app/Visibility/VisibilityResolver.php`, `app/Admin/WidgetStateMetaBox.php`, `tests/Unit/VisibilityResolverTest.php`.
- **Modified files**: `app/Settings/Settings.php` (adds `hide_on_post_types`), `app/Providers/AdminServiceProvider.php` (dynamic post types + meta-box registration), `app/Providers/SettingsServiceProvider.php` (REST register_post_meta), `app/Providers/WidgetServiceProvider.php` (calls resolver), `app/Blocks/ReactionsBlock.php`, `app/Blocks/Shortcode.php`, `app/Blocks/WidgetMarkup.php`, `resources/admin/sections/DisplaySection.tsx` (dynamic + new field), `resources/admin/types.ts` (extend SettingsState + SettingsChoices), `app/bootstrap.php`, `docs/hooks-and-filters.md`.
- **REST API**: `GET /moonfarmer-reactions-lead-capture/v1/settings` response now includes `choices.post_types`. Post meta `_moonfarmer_reactions_lead_capture_widget_state` becomes REST-accessible per-post via `register_post_meta`.
- **Database changes**: no schema. New post meta keys.
- **Filters introduced**: `moonfarmer_reactions_lead_capture_meta_box_post_types` (default `get_post_types(['public' => true])`), `moonfarmer_reactions_lead_capture_visibility_mode` (allows last-mile override of the resolver's decision).
- **Privacy**: no new data collected. Meta is admin-set per post; nothing exposed publicly.
- **Performance**: one `get_post_meta` call per render — negligible. The settings page payload gets `choices.post_types` once on load.
- **Accessibility**: meta box uses native `<fieldset><legend><input type="radio">` with helper text — keyboard and screen-reader friendly out of the box.
- **Free/Pro boundary**: untouched. Pro can hook `moonfarmer_reactions_lead_capture_visibility_mode` for license-based behaviour (e.g., per-category gating) without modifying Free.
