## 1. Domain types and helpers

- [x] 1.1 Update `resources/widget/types.ts`: add `positiveReactions: ReactionType[]` and `i18n.capture` shape (prompt, label, placeholder, consent, consentHelper, submit, submitting, thanks, alreadyCaptured, networkError, expiredNonce, dismiss).
- [x] 1.2 Create `resources/widget/positive.ts` exporting `isPositive(type: ReactionType | null, data: MoonfarmerReactionsLeadCaptureData): boolean`. Returns `false` when `type` is null; otherwise checks membership in `data.positiveReactions`.
- [x] 1.3 Update `resources/widget/storage.ts` with `getCapturedFlag(postId)` / `setCapturedFlag(postId)` and matching dismissed helpers. localStorage keys: `moonfarmer-reactions-lead-capture:captured:{postId}` and `moonfarmer-reactions-lead-capture:capture_dismissed:{postId}` (the dismissed flag clears on page load — implement as in-memory only).
- [x] 1.4 Update `resources/widget/api.ts` with `postCapture({ root, nonce, postId }, { email, reactionType, source })` typed wrapper returning `CaptureResponse`. Throws `RestError` on non-2xx so the form can read `code` + `message`.

## 2. CaptureForm component

- [x] 2.1 Create `resources/widget/components/CaptureForm.tsx`. Props: `{ postId: number; reactionType: ReactionType; data: MoonfarmerReactionsLeadCaptureData; triggerRef: { current: HTMLButtonElement | null }; onDone: (result: { status: 'inserted'|'already_exists' }) => void; onDismiss: () => void }`.
- [x] 2.2 Local state: `email` (string), `consent` (boolean), `submitting` (boolean), `error` (string | null), `success` (boolean), `successMessage` (string | null).
- [x] 2.3 On mount: ref the email input and call `.focus()` in a `useEffect`.
- [x] 2.4 On submit: client-side checks (consent required → set error + focus checkbox; email regex → set error + keep focus). On pass, POST, handle 200/409 → success/onDone, handle 422 → server message in error region, handle network → networkError.
- [x] 2.5 On Escape key inside the form: call `onDismiss()` (parent sets the dismissed flag) and the form unmounts.
- [x] 2.6 Success render: replace form contents with `<p role="status" aria-live="polite">{successMessage}</p>` plus a small "Dismiss" button. Focus returns to `triggerRef.current` after a 50 ms timeout.
- [x] 2.7 All visible strings come from `data.i18n.capture.*`. No hardcoded English.

## 3. ReactionBar wiring

- [x] 3.1 In `ReactionBar.tsx`: add `useRef<HTMLButtonElement | null>` per reaction button, store refs in a map keyed by reaction type.
- [x] 3.2 Track `dismissedRef: useRef<boolean>(false)` for the in-session dismissed flag.
- [x] 3.3 In the render, after the buttons div: if `isPositive(activeType, data) && !getCapturedFlag(postId) && !dismissedRef.current`, render `<CaptureForm postId={postId} reactionType={activeType!} data={data} triggerRef={buttonRefs[activeType]} onDone={({status}) => { setCapturedFlag(postId); }} onDismiss={() => { dismissedRef.current = true; forceUpdate(); }} />`.
- [x] 3.4 Forced re-render after dismissal: use a counter `useState(0)` bumped on dismissal to re-evaluate the render.

## 4. PHP localization payload

- [x] 4.1 Update `app/Providers/WidgetServiceProvider.php::enqueueAssets()` payload: add `positiveReactions => array_values((array) apply_filters('moonfarmer_reactions_lead_capture_positive_reactions', ['love', 'insightful', 'funny']))`. Add `i18n.capture.*` strings with sensible English defaults (`prompt`, `label`, `placeholder`, `consent`, `consentHelper`, `submit`, `submitting`, `thanks`, `alreadyCaptured`, `networkError`, `expiredNonce`, `dismiss`).
- [x] 4.2 Add `Reactions::DEFAULT_POSITIVE = ['love', 'insightful', 'funny']` constant referenced by the provider.

## 5. CSS

- [x] 5.1 Extend `widget.css` with scoped `.moonfarmer-reactions-lead-capture-capture` styles: vertical layout, padding/gap matching the reaction row, accent on the submit button, focus-visible rings, `role="alert"` colour for errors, success state styling, reduced-motion-friendly transition for the open/close (height + opacity; zeroed under reduced-motion).
- [x] 5.2 Maintain CSS budget; aim ≤ 3 KB total minified.

## 6. Tests

- [x] 6.1 Add `tests/Unit/PositiveReactionsTest.php` — actually a unit test for the PHP filter: `apply_filters('moonfarmer_reactions_lead_capture_positive_reactions', ['love', 'insightful', 'funny'])` round-trip plus filter extension. Stub `Reactions::DEFAULT_POSITIVE` const access.
- [x] 6.2 Add `tests/Unit/WidgetServiceProviderTest.php` scenario: localized payload contains the new positive set and the i18n.capture keys.
- [x] 6.3 Update `tests/Unit/BootstrapTest.php` (no new PHP classes, only constant + filter — confirm `Reactions::DEFAULT_POSITIVE` exists).
- [x] 6.4 Run `composer test`; confirm all green.

## 7. Build + manual verification

- [x] 7.1 Run `npm run build`. Confirm bundle remains ≤ 15 KB gzipped JS, CSS ≤ 3 KB.
- [x] 7.2 Visit single post; click Love; confirm the form appears with the email input focused.
- [x] 7.3 Tab: email → consent → submit. Confirm visible focus rings; pressing Space toggles the checkbox; Enter on the submit button submits.
- [x] 7.4 Submit with consent unchecked → confirm inline `role="alert"` error and focus on checkbox.
- [x] 7.5 Submit with invalid email → confirm `role="alert"` error.
- [x] 7.6 Submit with valid email + consent checked → confirm 200 response, success message renders with `role="status"`, focus moves to the Love button.
- [x] 7.7 Reload the page; click Love → confirm the form does NOT reappear.
- [x] 7.8 Clear localStorage; click Love; submit the same email → confirm 409 + friendly already-captured success state.
- [x] 7.9 Click Sad (negative) on a fresh post → confirm no form appears.
- [x] 7.10 Enable VoiceOver; verify on success the message is announced and focus reads the now-active Love button.

## 8. Docs + final verification

- [x] 8.1 Update `docs/hooks-and-filters.md`: add `moonfarmer_reactions_lead_capture_positive_reactions` filter (shipped, Session 5).
- [x] 8.2 Run `openspec validate inline-capture-ui --strict --no-interactive` — clean.
- [x] 8.3 Confirm no new entries in `wp-content/debug.log`.
- [x] 8.4 Commit (no Co-Authored-By; AGENTS.md PR body).
