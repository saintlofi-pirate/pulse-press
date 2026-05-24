## Context

`moonfarmer_reactions_lead_capture_captures` stores consented emails with full metadata. There is no way for an admin to retrieve them today; the only inputs that matter are the column list, the file name, and the permission gate. The hard part is making the response stream correctly through a REST route while keeping the column set extensible for Pro.

The constraint: WordPress's REST framework wants to JSON-encode responses. We need raw CSV bytes. The standard workaround is to bypass the framework's serialiser by `echo`-ing the body inside the callback, calling `header_remove()` / `header()` to fix the response headers, and exiting via `wp_die('', '', ['response' => 200])` so PHP doesn't re-enter REST output buffering.

## Goals / Non-Goals

**Goals:**

- A single REST endpoint produces a downloadable CSV.
- Default column set covers what an admin needs to import elsewhere: timestamp, email, post id + title, reaction type, consent version, source, captured-at.
- Pro can layer in extra columns through a filter without touching Free.
- Memory stays bounded on large tables (chunked SELECT).
- Strings are CSV-escaped properly (RFC 4180 — double quotes, embedded commas, newlines).
- The admin UI shows a clear "Export captures" button on the Capture tab, with loading + success states.

**Non-Goals:**

- No filtering by date range / by post / by reaction type in this slice. Future polish.
- No JSON export. CSV is the format admins ask for.
- No background-job queueing. The export is synchronous; an admin click waits for the download. For very large tables, Pro can layer Action Scheduler.
- No "delete after export" toggle. Captures stay until manually deleted (gap 3).
- No header customisation. Headers are the column `label` from the column definition; Pro overrides via the filter if needed.
- No automatic encoding detection. CSV is UTF-8 with a BOM for Excel compatibility.

## Decisions

### D1. Endpoint path is `/captures.csv`, not `/captures/export.csv`

`/captures.csv` reads as a noun-with-extension — a file you fetch. The extension is decorative (no content negotiation), but it makes the URL self-explanatory and tells browsers to default the download filename if our header is somehow stripped.

### D2. Streaming pattern: header_remove + echo + wp_die

The controller's `download` method:

1. Validates capability and nonce (permission_callback handled the cap; the controller re-asserts in case of a misconfigured filter).
2. Computes the filename: `moonfarmer-reactions-lead-capture-captures-YmdHis.csv` using site timezone.
3. Calls `nocache_headers()`, then `header_remove('Content-Type')`, `header('Content-Type: text/csv; charset=utf-8')`, `header('Content-Disposition: attachment; filename=...')`, `header('X-Content-Type-Options: nosniff')`.
4. Echoes a UTF-8 BOM (`"\xEF\xBB\xBF"`) so Excel handles non-ASCII correctly.
5. Streams the CSV using the `CaptureExporter` with an `echo`-based emitter.
6. Calls `wp_die('', '', ['response' => 200])` to short-circuit the REST framework's JSON serialiser.

### D3. CaptureExporter is testable in isolation

`CaptureExporter::stream(callable $emit, array $options = [])` takes the emit callback as a parameter so tests pass a closure that captures lines into an array, while the controller passes a closure that `echo`-s.

Chunked reads: `SELECT … FROM moonfarmer_reactions_lead_capture_captures ORDER BY id ASC LIMIT $chunkSize OFFSET $offset`. `chunkSize` defaults to 500 (filterable via `$options['chunk_size']`).

### D4. Column registry + filter

```php
$columns = [
    'consent_at'           => ['label' => 'Consent timestamp', 'render' => fn($r) => $r['consent_at']],
    'email'                => ['label' => 'Email',              'render' => fn($r) => $r['email']],
    'post_id'              => ['label' => 'Post ID',            'render' => fn($r) => (string) $r['post_id']],
    'post_title'           => ['label' => 'Post title',         'render' => fn($r) => self::resolveTitle((int) $r['post_id'])],
    'reaction_type'        => ['label' => 'Reaction',           'render' => fn($r) => $r['reaction_type']],
    'consent_text_version' => ['label' => 'Consent version',    'render' => fn($r) => $r['consent_text_version']],
    'source'               => ['label' => 'Source',             'render' => fn($r) => $r['source']],
    'created_at'           => ['label' => 'Captured at',        'render' => fn($r) => $r['created_at']],
];
$columns = apply_filters('moonfarmer_reactions_lead_capture_export_columns', $columns);
```

Pro adds `esp_sync_status` by hooking the filter and appending its column definition. The `render` callable receives the raw row array; Pro can join its own per-capture state.

### D5. Title resolution

Resolved via `get_the_title($postId)`. Deleted posts get `"(deleted post)"`. Titles cached in-memory inside one export run to avoid duplicate `get_post` calls when an email captures on multiple times — actually impossible since unique key is `(email, post_id)` so each row's post id is unique relative to the email but multiple rows may share a post id.

### D6. CSV escaping uses `fputcsv` semantics manually

We can't use `fputcsv` directly (writes to a stream resource, not a callback). The exporter implements the same rules: wrap a cell in double quotes if it contains `",\r\n`; escape inner double quotes by doubling them. Lines end with `\r\n` per RFC 4180.

### D7. Filename includes a timestamp

`moonfarmer-reactions-lead-capture-captures-20260514T103015Z.csv`. Site-timezone date for legibility, `Z` to signal it's a snapshot timestamp.

### D8. Admin button uses fetch + Blob + temporary <a>

The button:

1. Sets `aria-busy="true"`.
2. `fetch(url, {credentials: 'same-origin', headers: {'X-WP-Nonce': nonce}})`.
3. Reads response as Blob (the response is text but Blob handles binary too).
4. `URL.createObjectURL(blob)` → `<a download={filename}>` element → click → revoke object URL after 1s.
5. Clears `aria-busy`, flashes a "Download started." status pill for 1.5s.

Errors render an inline `role="alert"` with the response message.

### D9. Empty-table behaviour

If there are zero rows: header is still emitted, body is empty. Downloading an empty file is preferable to a 404 — the admin can keep their import pipeline pointed at the URL.

## Risks / Trade-offs

- **Risk**: very large captures table times out a single-request export. → Mitigation: chunked reads keep memory bounded but CPU still has to format every row. A future Pro feature could shard into multiple requests. For Free, "100k rows under 30s" is enough.
- **Risk**: streamed `echo` collides with WP output buffering and produces a corrupted file. → Mitigation: `wp_die('', '', ['response' => 200])` is the documented escape hatch; explicit `nocache_headers()` + `header_remove('Content-Type')` ensures the framework doesn't override our headers.
- **Risk**: an admin's spreadsheet tool mangles UTF-8 (Excel for Windows historically). → Mitigation: BOM-prefixed output. Documented in the spec.
- **Risk**: a malicious column filter returns non-callable `render` and PHP throws. → Mitigation: the exporter checks `is_callable($column['render'])` before calling; falls back to empty string with a debug log line.
- **Risk**: the streaming response evades nonce checks. → Mitigation: the REST framework runs the permission_callback before invoking the controller; we also re-assert inside the controller as belt-and-braces.
- **Trade-off**: synchronous download. For 100k+ rows the browser shows a loading state for seconds. Acceptable trade-off; Pro can layer async.

## Migration Plan

No data migration. The endpoint becomes available the moment Session 10 ships. Rollback is `git revert`.

## Open Questions

- **Q1**: should we also offer a JSON export? → **Decided no for v1.** CSV is the request; JSON adds surface for negligible value.
- **Q2**: should the export include the IP/UA hashes? → **Decided no for v1.** They are abuse-review metadata; surfacing them in a CSV could leak hashed identifiers to an ESP or backup. Pro may add an opt-in toggle.
