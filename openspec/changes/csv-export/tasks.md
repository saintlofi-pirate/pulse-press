## 1. Exporter

- [ ] 1.1 Create `app/Captures/CaptureExporter.php` (`final class`). Constructor `(\wpdb $wpdb)`. Method `stream(callable $emit, array $options = []): int`. Resolves columns via `pulsepress_export_columns` filter from the canonical default map. Fires `pulsepress_before_export` inside try/catch for `RestException`. Reads in 500-row chunks ordered by id. Emits header line first; then one CSV-escaped line per row. Returns total rows emitted.
- [ ] 1.2 RFC 4180 escaping helper `csvEscape(string $value): string`. Title resolution helper `resolveTitle(int $postId, array &$cache): string` with in-run memoisation.

## 2. Controller + REST route

- [ ] 2.1 Create `app/Http/Controllers/ExportController.php`. Constructor `(CaptureExporter $exporter)`. Method `download(\WP_REST_Request $request)`. Validates `manage_options`; computes filename with site-tz timestamp; calls `nocache_headers()`, `header_remove('Content-Type')`, then sets `Content-Type: text/csv; charset=utf-8` and `Content-Disposition: attachment; filename=…`. Echoes BOM. Streams via `$exporter->stream(fn($line) => echo $line)`. Calls `wp_die('', '', ['response' => 200])` to terminate.
- [ ] 2.2 In `CaptureServiceProvider::register()` bind the exporter and the controller. In `boot()` register `GET /pulsepress/v1/captures.csv` with `permission_callback => fn() => current_user_can('manage_options')`.

## 3. Admin SPA — Export button

- [ ] 3.1 Update `resources/admin/types.ts` with `i18n.captureExport.{label,helper,preparing,downloadStarted,error,retry}` strings.
- [ ] 3.2 Add `downloadCaptureCsv(restRoot, nonce)` helper in `resources/admin/api.ts` that fetches the CSV endpoint, returns the Blob + filename parsed from `Content-Disposition`.
- [ ] 3.3 Create `resources/admin/components/CaptureExportButton.tsx` rendering a `<button type="button">` with loading/success/error states. Uses the helper, creates a temp `<a download>`, revokes the object URL after click.
- [ ] 3.4 Mount the button at the end of `resources/admin/sections/CaptureSection.tsx` inside a `<div class="pulsepress-export-region">` block.
- [ ] 3.5 Update `app/Providers/AdminServiceProvider.php::i18n()` with the new strings.

## 4. Styles

- [ ] 4.1 Extend `resources/admin/styles/admin.css` with `.pulsepress-export-region` (card-styled), `.pulsepress-export-helper`, `.pulsepress-export-status` (transient pill). Reuse existing `.pulsepress-submit` for the button.

## 5. Tests

- [ ] 5.1 `tests/Unit/CaptureExporterTest.php`:
  - Header line matches the default labels.
  - Each row's cells are CSV-escaped (embedded comma → quoted, embedded `"` → doubled, embedded newline → quoted).
  - Filter can add a new column.
  - `pulsepress_before_export` action throwing `RestException` short-circuits.
  - 1500-row table issues three chunked reads.
- [ ] 5.2 Update `tests/Unit/BootstrapTest.php` autoload assertions for `CaptureExporter` and `ExportController`.
- [ ] 5.3 Run `composer test`; confirm green.

## 6. Manual verification

- [ ] 6.1 Insert ≥ 3 captures via the REST endpoint or `wp eval`.
- [ ] 6.2 `curl -k -H "X-WP-Nonce: $(wp eval 'echo wp_create_nonce("wp_rest");')" -b cookie.jar "https://wp_lab.test/wp-json/pulsepress/v1/captures.csv" -o /tmp/captures.csv` (logged in cookie jar required).
- [ ] 6.3 Open `/tmp/captures.csv` — confirm BOM + header + rows; embedded special characters escape correctly.
- [ ] 6.4 Anonymous request → 401/403.
- [ ] 6.5 Settings → Capture tab → click "Export captures" → browser downloads the file.

## 7. Docs + final

- [ ] 7.1 Update `docs/hooks-and-filters.md`: add `pulsepress_export_columns` filter and `pulsepress_before_export` action.
- [ ] 7.2 Run `openspec validate csv-export --strict --no-interactive` clean.
- [ ] 7.3 PHP lint clean.
- [ ] 7.4 Commit (no co-auth).
