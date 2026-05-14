## Why

Session 11a is the regression-prevention slice of the planned a11y + perf pass (`docs/gap-questions-and-session-tasks.md §Session 11`). An audit of resources/widget and resources/admin shows three concrete gaps left over from earlier sessions where the surrounding ARIA/keyboard contract is otherwise solid:

1. **CaptureForm popover lacks dialog semantics.** It auto-focuses the email input, has Escape-to-dismiss, and returns focus to the triggering reaction button — but screen readers don't know it's a dialog because there is no `role="dialog"`, no `aria-modal="true"`, and no `aria-labelledby`. NVDA / VoiceOver announce it as ordinary form fields appearing in the page.
2. **ToggleField hardcodes English On/Off.** `resources/admin/components/fields/ToggleField.tsx:39` renders `{checked ? 'On' : 'Off'}` instead of pulling from `adminData.i18n`. Every other admin string is localised; this one slipped.
3. **`.pulsepress-panel` has `outline: none` but is keyboard-focusable.** Tab-key landing on the panel container after a tab change has no visible focus indicator (`resources/admin/styles/admin.css:122`).

The perf wins from the same audit (lazy CaptureForm and lazy DailySeriesChart / TopPostsTable) will land separately as Session 11b so the a11y patches commit cleanly on their own.

## What Changes

- **Widget — CaptureForm dialog semantics**: Wrap the form in `role="dialog"`, set `aria-modal="true"`, and reference a new visible-but-styled `<h3 id="pulsepress-capture-title">` (or `aria-labelledby` on the existing prompt copy if it already serves as the title). Add `aria-describedby` pointing at the consent helper so the dialog's purpose is announced on open.
- **Admin SPA — ToggleField i18n**: Add `toggle: { on, off }` to the admin payload's `i18n` block (PHP + types). Thread it through ToggleField props with sensible English fallbacks.
- **Admin SPA — visible focus on panel**: Replace `outline: none` on `.pulsepress-panel` with a `:focus-visible` rule that uses the existing accent ring tokens. Keep the panel non-focused-by-default look unchanged.
- **BREAKING**: none. All changes are additive at the markup/ARIA level or replace hardcoded English with localised copy.

## Capabilities

### Modified Capabilities

- `widget`: CaptureForm announces as a dialog, with a labelled title and described-by reference.
- `admin-spa`: ToggleField surface text is fully localised; `.pulsepress-panel` keyboard focus is visible.

## Impact

- **Modified files**: `resources/widget/components/CaptureForm.tsx`, `resources/admin/components/fields/ToggleField.tsx`, `resources/admin/types.ts`, `app/Providers/AdminServiceProvider.php` (extend i18n block), `resources/admin/styles/admin.css` (panel focus styling), `resources/widget/styles/widget.css` (capture dialog title styling, if needed).
- **No** new files, no REST changes, no schema changes, no new filters.
- **i18n strings added** (admin): `toggleOn` ("On"), `toggleOff` ("Off"). Already-translated locales just inherit English until translators update.
- **Tests**: existing pest suite remains green (no PHP behavior change beyond payload shape). One ToggleField change exercises the unchanged FieldShell contract; no new test needed.
- **Perf**: untouched. CSS gains a single `:focus-visible` rule (negligible). Markup adds a single `<h3>` to CaptureForm.
- **Manual a11y verification**: VoiceOver/NVDA announces "Email capture dialog" on positive-reaction click; Tab focus indicator visible when traversing through the admin panel; ToggleField announces translated state copy in non-English locales.
