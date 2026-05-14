## 1. CaptureForm dialog semantics

- [x] 1.1 Add a stable id constant `CAPTURE_TITLE_ID = 'pulsepress-capture-title'` near the existing id constants.
- [x] 1.2 Wrap the form return in a `<div role="dialog" aria-modal="true" aria-labelledby={CAPTURE_TITLE_ID}>` (or apply the attributes directly to the existing `<form>` if cleaner).
- [x] 1.3 Promote `i18n.prompt` rendering to an `<h3 id={CAPTURE_TITLE_ID} class="pulsepress-capture-title">` so the dialog's accessible name comes from existing copy without doubling text.
- [x] 1.4 Verify Escape + focus-return still work via the existing keyDown handler (no logic change needed).
- [x] 1.5 Add `.pulsepress-capture-title { font-weight: 600; font-size: 1rem; margin: 0 0 0.5rem; }` (or equivalent matching the surrounding visual rhythm) to `resources/widget/styles/widget.css`.

## 2. ToggleField i18n

- [x] 2.1 Extend the admin i18n block in `app/Providers/AdminServiceProvider.php` with a `toggle` array carrying `on` and `off` translations.
- [x] 2.2 Extend `PulsePressAdminData['i18n']` in `resources/admin/types.ts` with the same shape.
- [x] 2.3 Thread the strings through `ToggleField` via a new optional `labels?: { on: string; off: string }` prop with `'On' / 'Off'` fallbacks.
- [x] 2.4 Update every existing call site that renders a ToggleField (display, reactions, capture, privacy sections) to pass `labels={adminData.i18n.toggle}`.

## 3. Visible focus on .pulsepress-panel

- [x] 3.1 In `resources/admin/styles/admin.css`, replace the `.pulsepress-panel { outline: none; }` rule with `.pulsepress-panel:focus { outline: none; } .pulsepress-panel:focus-visible { outline: 2px solid var(--pp-accent); outline-offset: 4px; border-radius: var(--pp-radius); }`.
- [x] 3.2 Confirm the rule sits within the reduced-motion media query stack (no animation tied to it).

## 4. Verify

- [x] 4.1 `./vendor/bin/pest` — full PHP suite passes.
- [x] 4.2 `npx tsc --noEmit` — clean.
- [x] 4.3 `npm run build` — emits without warnings.
- [x] 4.4 Manual on wp_lab.test: open a post, click a positive reaction, confirm CaptureForm announces as dialog and Tab cycles within visible focus. Open admin settings, Tab to panel, confirm focus ring appears.

## 5. Commit

- [x] 5.1 Commit Session 11a with message describing the three patches.
