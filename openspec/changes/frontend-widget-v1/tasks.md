## 1. Build pipeline

- [x] 1.1 Update `vite.config.js` so the `widget` entry produces both JS and CSS bundles (Preact preset already in place from Session 0). Confirm `build.manifest = true` and the output writes to `dist/.vite/manifest.json`.
- [x] 1.2 Add a minimal `tsconfig.json` enabling JSX (`"jsx": "react-jsx"`, `"jsxImportSource": "preact"`) and `"strict": true`. The file is editor-facing; Vite handles transpile.

## 2. Widget source

- [x] 2.1 Create `resources/widget/types.ts` with TS interfaces for `PulsePressData`, `CountsResponse`, `ReactResponse`.
- [x] 2.2 Create `resources/widget/api.ts` exporting `fetchCounts(postId)` and `react(postId, type)` typed wrappers around `window.fetch`. The write call sends `X-WP-Nonce: PulsePressData.nonce` and `Content-Type: application/json`; both functions throw on non-2xx responses with the WP error code/message attached.
- [x] 2.3 Create `resources/widget/storage.ts` exporting `getStoredReaction(postId): string | null` and `setStoredReaction(postId, type | null)` wrapped in try/catch around `localStorage`. Key format: `pulsepress:reaction:{postId}`.
- [x] 2.4 Create `resources/widget/icons.ts` exporting six inline SVG strings (24×24, `currentColor` strokes) for love/insightful/funny/sad/surprised/angry. Each icon ≤ 400 bytes uncompressed.
- [x] 2.5 Create `resources/widget/components/ReactionBar.tsx` rendering the six-button row. Uses `useState` for `activeType`/`counts`/`error`, `useEffect` for the initial counts fetch. Click handler runs optimistic update → POST → server-confirm or rollback. Re-clicking the active reaction is a no-op.
- [x] 2.6 Create `resources/widget/widget.css` with the `.pulsepress` scope, CSS custom properties from design D8, button styles, focus-visible ring, active-state tint, and a 200ms transform transition. Total ≤ 3 KB minified.
- [x] 2.7 Create `resources/widget/index.ts` that on `DOMContentLoaded` reads `window.PulsePressData`, finds every `[data-pulsepress-widget]` element, and mounts a `<ReactionBar />` rooted at the element with `postId` overridden by the element's `data-pulsepress-post-id` attribute when present.

## 3. PHP provider

- [x] 3.1 Create `app/View/Manifest.php` with `final class Manifest`. Constructor takes the plugin file path. Method `resolve(string $entry): array{js: ?string, css: ?string}` reads `dist/.vite/manifest.json` (caching via the `pulsepress_vite_manifest_v1` transient keyed by `filemtime`). Returns plugin-URL-prefixed paths.
- [x] 3.2 Create `app/Providers/WidgetServiceProvider.php` extending `ServiceProvider`. In `register()` bind `Manifest` as a singleton. In `boot()` add `wp_enqueue_scripts` and `the_content` callbacks.
- [x] 3.3 The enqueue callback gates on `!is_admin() && (is_singular('post') || apply_filters('pulsepress_widget_enqueue', false))`, registers the JS and CSS handles via `wp_register_script`/`wp_register_style` against the manifest, calls `wp_enqueue_script`/`wp_enqueue_style`, and emits `wp_localize_script('pulsepress-widget', 'PulsePressData', apply_filters('pulsepress_widget_data', $payload))` where `$payload` includes `root`, `nonce`, `postId`, `reactions`, `i18n`.
- [x] 3.4 The content filter appends `'<div class="pulsepress" data-pulsepress-widget data-pulsepress-post-id="' . esc_attr($postId) . '"></div>'` to the content body when `is_singular()` is true and `apply_filters('pulsepress_widget_auto_insert', $default, $postType)` returns truthy. Default is `true` for `post` and `false` otherwise.
- [x] 3.5 Register `WidgetServiceProvider::class` in `app/bootstrap.php` after `RestServiceProvider::class`.

## 4. Test stubs and tests

- [x] 4.1 Extend `tests/Stubs/wp_functions.php` with namespaced shims for `PulsePress\View` and `PulsePress\Providers`: `wp_register_script`, `wp_register_style`, `wp_enqueue_script`, `wp_enqueue_style`, `wp_localize_script`, `is_admin`, `is_singular`, `is_singular`, `get_the_ID`, `rest_url`, `wp_create_nonce`, `esc_attr`, `esc_url`, `get_post_type`.
- [x] 4.2 Add `tests/Unit/ManifestTest.php` asserting: missing manifest returns `[null, null]`; present manifest resolves `js`/`css` URLs; transient cache hit avoids re-reading; mtime mismatch refreshes.
- [x] 4.3 Add `tests/Unit/WidgetServiceProviderTest.php` asserting the auto-insert filter is consulted with the post type, content is unchanged off singular contexts, and the widget container is appended on single post.
- [x] 4.4 Update `tests/Unit/BootstrapTest.php` to autoload-assert `Manifest`, `WidgetServiceProvider`.
- [x] 4.5 Run `composer test`; confirm all green.

## 5. Build and manual verification

- [x] 5.1 Run `npm install` if needed, then `npm run build`. Confirm `dist/js/widget.<hash>.js`, `dist/assets/widget.<hash>.css`, and `dist/.vite/manifest.json` exist.
- [x] 5.2 Compute `gzip -c dist/js/widget.*.js | wc -c` and confirm ≤ 15360. Repeat with `wc -c < dist/assets/widget.*.css` and confirm ≤ 3072.
- [x] 5.3 Load a single-post URL on `wp_lab.test` in a browser; confirm the widget renders below the content with six buttons.
- [x] 5.4 Click a reaction; confirm the count increments visually and `localStorage` records the choice.
- [x] 5.5 Reload the page; confirm the active state survives via `localStorage`.
- [x] 5.6 Tab into the widget; confirm focus rings render; press Enter; confirm the same flow runs.
- [x] 5.7 Load an archive page; confirm no widget script is enqueued (`view-source:` of the page contains no `pulsepress` script tag).

## 6. Final verification

- [x] 6.1 Run `find app pulsepress.php uninstall.php -name '*.php' -print0 | xargs -0 -n1 /opt/homebrew/opt/php@8.3/bin/php -l` and confirm "No syntax errors" on every file.
- [x] 6.2 Run `openspec validate frontend-widget-v1 --strict --no-interactive` and confirm clean.
- [x] 6.3 Confirm `wp-content/debug.log` has no new PulsePress entries after browser testing.
- [x] 6.4 Commit using the AGENTS.md PR-style body (no Co-Authored-By).
