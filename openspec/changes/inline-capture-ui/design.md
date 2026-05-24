## Context

Session 4 has the storage path; Session 5 puts a face on it. Two product principles constrain every choice in this slice:

1. **No modal interruption.** Gap 4 already rejected modal capture. The form is inline, appears under the reaction row, and can be dismissed by ignoring it.
2. **Consent must be explicit.** A checkbox the visitor actively ticks. No pre-checked checkbox; no implicit consent via submission of the form.

The accessibility direction (newly codified in `docs/moonfarmer-reactions-lead-capture-v1-plan.md` §Accessibility — WCAG 2.1 AA First) adds: real `<form>` semantics, real `<label>`s, full keyboard parity, visible focus rings, full-sentence errors via `role="alert"`, focus management on open/close, screen-reader-announced success.

The implementation reuses the widget's existing `useState`/`useEffect` patterns and the API client built in Session 3, so the bundle delta is small.

## Goals / Non-Goals

**Goals:**

- Render an inline capture form after the visitor casts a positive reaction (Love, Insightful, or Funny by default).
- Trigger only the first time per post per browser — second positive clicks on the same post are silent.
- Real form semantics: `<form>` element, `<label>` per input, `<input type="email">`, `<input type="checkbox">`, submit `<button type="submit">`.
- Focus moves to the email input when the form opens. After successful submit, focus returns to the triggering reaction button and the success message is announced via `role="status"`.
- The form respects the `prefers-reduced-motion` setting (no slide-in animation when set).
- Validation errors render inline (`role="alert"`) with full-sentence text from the REST response.
- The submit path is the Session 4 `POST /capture` endpoint; nothing more, nothing less.
- Bundle stays under the 15 KB gzipped JS budget.

**Non-Goals:**

- No double opt-in mail in Free.
- No CAPTCHA / Turnstile / hCaptcha. Spam protection is the dedup + before_capture hook story.
- No "remember me on this site forever" mechanic beyond the localStorage flag.
- No analytics on form display/dismiss. Session 9's dashboard reads from `moonfarmer_reactions_lead_capture_captures` only.
- No "send me a copy" follow-up email.
- No multi-field signup (name, etc.). Only email.
- No marketing copy variations. One sensible default; admins customise via `i18n.capture.*` overrides in Session 6 settings.

## Decisions

### D1. Inline beneath the reaction row, not a popover or modal

The form lives in the widget's bounding box, expanding the container's height. No `position: absolute`, no `<dialog>`, no focus trap (the focus moves naturally to the email input and back).

Trade-off: the form may push content below it. Acceptable — the widget is positioned via `the_content` at priority 20, so what's "below" is typically the related-posts module or footer, not critical content.

### D2. Positive-reaction trigger is a small, filterable allowlist

`positiveReactions` defaults to `['love', 'insightful', 'funny']` (gap 0 confirmed default). The filter `moonfarmer_reactions_lead_capture_positive_reactions` lets sites:
- Disable inline capture entirely by returning `[]`.
- Include "surprised" if a site's audience treats it as positive.
- Per-post tuning via the standard WP filter context.

Session 6 will surface this as a checkbox list in settings.

### D3. Per-post localStorage flag, not per-visitor cookie

Key: `moonfarmer-reactions-lead-capture:captured:{postId}` set to `'1'` on a successful 200 OR a 409 from the capture endpoint. Both states mean "we have this email or have explicitly heard from this device on this post" — re-asking would be rude.

The flag is local-only; the server is still the source of truth (the unique key catches duplicates regardless of what the browser thinks). A visitor clearing localStorage will see the form again; the server will return 409 if they re-submit with the same email.

### D4. Don't reopen the form after dismissal in the same session

If the visitor reacts positively (form shows), then dismisses by reacting again with a different positive type, the form does NOT re-show. We set `moonfarmer-reactions-lead-capture:capture_dismissed:{postId}` on first dismissal to prevent surprise re-opens.

The session-local dismissal cleared on next page load — that's intentional. A second visit to the same post is a fresh opportunity to ask.

### D5. Focus management is explicit and tested

On open:
- `useEffect` after render: `emailInputRef.current?.focus()`.

On successful submit:
- Replace form with success message in the same container.
- After 50ms (so the success render commits), focus the triggering reaction button (the active one).
- Success message uses `role="status"` with `aria-live="polite"` so SR announces it without stealing focus.

On error:
- Focus stays on the email input (most failures originate there).
- Error renders in a `<p role="alert">` adjacent to the input.

On `Esc` while focus is in the form:
- Close the form, return focus to the triggering button. Set the dismissed flag.

### D6. Form submission flow

1. Visitor checks consent + types email + clicks submit.
2. Local validation: email must match `^[^\s@]+@[^\s@]+\.[^\s@]+$` (loose; the server is authoritative).
3. Set `submitting: true` to disable the submit button + show "Submitting…" label.
4. `postCapture(...)` POSTs to `/moonfarmer-reactions-lead-capture/v1/capture` with `{post_id, email, reaction_type: activeType, consent: true, source: 'inline'}`.
5. On 200: set captured flag, render success state, focus returns to trigger.
6. On 409: set captured flag, render "We already have your email for this post" success-styled state.
7. On 422 (any sub-code): show the server's message in the error region; clear `submitting`; keep focus on input.
8. On 429 / 5xx / network error: show `i18n.capture.networkError`; clear `submitting`; keep input + checkbox state.

### D7. Component owns its state; ReactionBar owns its display

`ReactionBar` decides whether to render `<CaptureForm>` based on `activeType ∈ positiveReactions` AND `!isCaptured(postId)` AND `!isDismissed(postId)`. Once rendered, `CaptureForm` is self-contained — it doesn't know about counts or other buttons. It calls `onDone({ status, email })` so `ReactionBar` can flip the captured flag and re-render.

This keeps the component testable in isolation and avoids prop drilling.

### D8. Success message is the form's last state, then optionally collapses

After success, the form is replaced with a static `<p role="status">Thanks — we'll keep you in the loop.</p>` plus a small "Dismiss" link that hides it. The form does not auto-collapse; the success message stays until the visitor dismisses it OR the page reloads.

### D9. No second framework for the form

`<form onSubmit>` with controlled inputs via `useState`. No third-party form library (Formik, react-hook-form, etc.) in the budget.

### D10. CSS scoped to `.moonfarmer-reactions-lead-capture` namespace

Form styles live alongside the existing `.moonfarmer-reactions-lead-capture-*` styles in `widget.css`. Same CSS variables, same focus-ring strategy, same reduced-motion handling. No new external CSS files.

## Risks / Trade-offs

- **Risk**: the form pushes content jarringly downward. → Mitigation: a 150 ms ease-in-out height transition (disabled under reduced-motion) softens the appearance; in practice the form is ~120 px tall, well within normal content variation.
- **Risk**: visitors fill the form mid-page-cache, then submit — and the nonce has expired. → Mitigation: the REST framework returns `rest_cookie_invalid_nonce`; we map it to a friendly "Your session has expired. Please refresh the page and try again." We don't auto-refresh because that would lose the typed-in email.
- **Risk**: visitor clicks Love, sees form, doesn't fill it, clicks Insightful (also positive). → Mitigation: we keep the form open across positive-reaction changes (the `reaction_type` field updates to match the new active reaction). No dismissal happens automatically.
- **Risk**: A site overrides positives via filter to include a negative type by mistake. → Mitigation: the controller (Session 4) still validates `reaction_type` against the Reactions allowlist — filter abuse can't bypass server validation. The form's local logic just decides *whether to ask*; the server decides what to store.
- **Risk**: localStorage disabled. → Mitigation: `getCapturedFlag` returns `false`, the form always shows on positive clicks. The server's 409 catches re-submission. Mild UX regression, not a data integrity issue.
- **Trade-off**: no "skip for now" button. The form is dismissed by clicking outside or pressing Esc — not by a visible close button. Acceptable for a first cut; we can add a "× Dismiss" affordance if user testing shows confusion.

## Migration Plan

No data migration. The component renders only when the new conditions are met; legacy widget behaviour (just the reaction row, no form) is unchanged.

For deployment safety:

1. Land the change.
2. Visit a single post on `wp_lab.test`. Click Love. Confirm the inline form appears with focus on the email input.
3. Tab through: email → consent → submit. Confirm visible focus rings on each.
4. Submit without consent → confirm `role="alert"` error in the form (server returns 422).
5. Submit with consent + valid email → confirm success message announces and focus returns to the Love button.
6. Click Sad. Confirm no form appears (Sad is not in the positive set).
7. Reload the page. Click Love again. Confirm the form does NOT reappear (localStorage flag).
8. Clear `localStorage` for the site. Click Love again. Confirm the form reappears but the server returns 409 on submit (already captured); confirm the friendly already-captured success state renders.

## Open Questions

- **Q1**: Should the consent text be filterable via JS as well as PHP? → **Decided yes through PHP only.** Admins set the text in Settings (Session 6) and we surface the current snapshot in `i18n.capture.consent`. JS-only override would defeat the consent-versioning audit story.
- **Q2**: Should the success message include the visitor's email? → **Decided no.** Risk: showing back the email feels surveillance-y on a public computer. The success message is intentionally generic.
- **Q3**: Should we send an "unsubscribe" link in any way? → **Deferred to Pro.** ESP integrations handle unsubscribe natively. Free's captures are admin-managed.
