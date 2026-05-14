## Why

Captures land in the database (Session 4) but there is no way for admins to *get them out*. CSV export is the WordPress.org-distributable answer: it lets Free users move emails to whichever ESP they like without forcing PulsePress to ship a Mailchimp/ConvertKit/etc integration in the Free package. Pro will eventually push captures over the wire to ESPs directly; Free hands the admin a CSV.

Free remains generous: the export covers every captured email with consent metadata, no row limit, plain `text/csv` so any spreadsheet opens it. Privacy stays first-class: only `manage_options` users can run the export; the consent statement that was active at capture time travels with each row (versioned). Hooks-first: column set and rows are filterable so Pro can add `esp_sync_status`, `last_synced_at`, etc., without modifying Free.

## What Changes

- Add `PulsePress\Captures\CaptureExporter` (`final class`) with `stream(callable $emit, array $options = []): int`. `$emit` is called once per CSV line (header + rows). Returns the total row count. Uses `$wpdb->get_results` chunked to 500 rows at a time to keep memory bounded on big tables.
- Add `PulsePress\Http\Controllers\ExportController` with `download(\WP_REST_Request $request): WP_REST_Response | WP_Error`. Validates capability + nonce. Streams the CSV via `header_remove`/`header` calls + `echo` lines, then calls `wp_die('', '', ['response' => 200])` to avoid REST JSON wrapping. This is the standard "force a download from a REST route" pattern; the response object is constructed only on error paths.
- Register `GET /wp-json/pulsepress/v1/captures.csv` in `CaptureServiceProvider::boot()`. `permission_callback => current_user_can('manage_options')`.
- Default column order: `consent_at, email, post_id, post_title, reaction_type, consent_text_version, source, created_at`. Filterable via `pulsepress_export_columns` (returns array of `{key, label, render: callable}`).
- Add a clear "Export captures" affordance to the admin SPA's Capture tab — a `<button type="button">` that performs a `fetch(url, {credentials: 'same-origin', headers: {'X-WP-Nonce': nonce}})`, reads the response as a Blob, and triggers a browser download via a temporary `<a download>` element. The button shows "Preparing…" while in flight and "Download started" briefly on success.
- Add a single `pulsepress_before_export` action right before streaming starts so Pro can short-circuit (rate limit, audit log) by throwing `RestException`.
- Hide the export button when there are zero captures (empty state ships with explanatory copy).
- **BREAKING**: none.

## Capabilities

### New Capabilities

- `capture-export`: defines the REST contract — endpoint, response headers, row shape, default columns, filter for column extension, capability gate, nonce requirement, and the streaming behaviour for large tables.

### Modified Capabilities

- `admin-spa`: Capture tab gains an "Export captures" action region with the download button + last-export hint.

## Impact

- **New files**: `app/Captures/CaptureExporter.php`, `app/Http/Controllers/ExportController.php`, `tests/Unit/CaptureExporterTest.php`, `resources/admin/components/CaptureExportButton.tsx`.
- **Modified files**: `app/Providers/CaptureServiceProvider.php` (binds + registers the route), `resources/admin/sections/CaptureSection.tsx` (mounts the button), `resources/admin/api.ts` (adds `downloadCsv` helper), `resources/admin/types.ts` (adds capture export i18n strings), `app/Providers/AdminServiceProvider.php` (i18n strings), `docs/hooks-and-filters.md` (new filter + action).
- **REST API**: one new endpoint, `GET /wp-json/pulsepress/v1/captures.csv`. Requires `manage_options`. Response is `text/csv; charset=utf-8` with `Content-Disposition: attachment; filename=pulsepress-captures-YYYYMMDDHHMMSS.csv`.
- **Database changes**: none. The exporter reads from `pulsepress_captures` only.
- **Filters introduced**: `pulsepress_export_columns` (per-row column overrides).
- **Actions introduced**: `pulsepress_before_export` (short-circuit hook).
- **Privacy**: emails leave the database only on an admin-initiated download. IP/UA hashes do NOT appear in the default export.
- **Performance**: chunked 500-row reads. A 100k-row table exports in seconds with bounded memory. Streaming avoids loading the whole table into PHP memory.
- **Accessibility**: button has `aria-busy` while downloading; status announcements via `role="status"`; respects `prefers-reduced-motion` for the success pulse.
- **Free/Pro boundary**: Free ships CSV. Pro layers ESP sync over the same `pulsepress_after_capture` action (Session 4) and may add an `esp_sync_status` column via `pulsepress_export_columns`.
