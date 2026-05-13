## Why

Session 4 made it possible to *store* an email; Session 5 is what makes a visitor *give us* an email. Without it, the capture endpoint is unreachable from the front end and the v1 product thesis ("reactions that grow an email list") has no UX. The whole arrangement only works if the prompt feels timely and respectful — appearing right after the visitor expresses something positive about the post, with consent explicit, no modal interruption, and zero coercion. That's exactly what this slice ships.

Free remains generous: the form appears after the default-positive reactions (Love, Insightful, Funny) with no admin configuration required; the consent text is a sensible default; submission failures degrade gracefully without losing the visitor's input. Privacy stays first-class: nothing leaves the browser until the consent checkbox is checked; the form respects the same nonce + REST contract Session 4 published. Accessibility is built in: full keyboard support, focus management on open/submit, role="alert" errors, screen-reader announcement of the thank-you state.

## What Changes

- Add `resources/widget/components/CaptureForm.tsx` rendering: a short prompt sentence, an `<input type="email">` with a visible `<label>`, an `<input type="checkbox">` for explicit consent paired with helper text (`aria-describedby`), a primary submit button, and an inline error region (`role="alert"`). The component manages its own loading + success states.
- Add `resources/widget/positive.ts` exporting `isPositive(type: string, data: PulsePressData): boolean`. Default positive set is `['love', 'insightful', 'funny']`, surfaced through `PulsePressData.positiveReactions` (filterable in PHP via `pulsepress_positive_reactions`).
- Modify `ReactionBar` to render `<CaptureForm postId={postId} reactionType={activeType} data={data} onDone={...} />` immediately below the reaction row when `activeType` is positive AND the visitor hasn't already captured AND a session-local "dismissed" flag isn't set. The form mounts focused on the email input (focus management: trigger button → input on open, returns to the original triggering reaction button on close/submit-success).
- Add localStorage flag `pulsepress:captured:{postId}` set when the form successfully submits OR returns 409 (already exists). Subsequent positive reactions on the same post do NOT re-show the form.
- Add `resources/widget/api.ts::postCapture(...)` typed wrapper around `POST /pulsepress/v1/capture`.
- Update `WidgetServiceProvider::enqueueAssets()` to add `positiveReactions` and an `i18n.capture` payload (prompt, label, placeholder, consent, submitting, thanks, networkError, alreadyCaptured) into `PulsePressData`.
- Add `pulsepress_positive_reactions` PHP filter (default `['love', 'insightful', 'funny']`).
- Add a small CSS section to `widget.css` for the capture form — same scoped accent + spacing + focus rings; full WCAG-AA contrast; reduced-motion friendly.
- **BREAKING**: none. The widget renders unchanged for negative or absent reactions.

## Capabilities

### Modified Capabilities

- `reaction-widget`: gains the inline capture flow. Adds the positive-reaction trigger contract, the per-post localStorage flag, the consent gate, the keyboard/SR focus management, and the success/error states.

### New Capabilities

None — the spec extension lives inside `reaction-widget`.

## Impact

- **New files**: `resources/widget/components/CaptureForm.tsx`, `resources/widget/positive.ts`.
- **Modified files**: `resources/widget/components/ReactionBar.tsx`, `resources/widget/api.ts`, `resources/widget/storage.ts`, `resources/widget/types.ts`, `resources/widget/widget.css`, `app/Providers/WidgetServiceProvider.php`, `app/Reactions/Reactions.php` (adds default-positive constant), `docs/hooks-and-filters.md` (shipped: `pulsepress_positive_reactions`).
- **REST API**: no new endpoints; consumes the Session 4 `POST /capture` contract.
- **Filters introduced**: `pulsepress_positive_reactions` (PHP — controls the default-positive set for the front end).
- **Privacy**: form never auto-submits; visitor must check consent + click submit; nothing posts until both occur.
- **Performance**: form lazy-renders only after a positive click. No additional network on first paint. Bundle size target: stays under the 15 KB gzipped JS budget set in Session 3.
- **Accessibility**: full keyboard support, semantic `<form>` with `<label>` per input, `aria-describedby` for consent helper, `role="alert"` for errors, `role="status"` for the success message, focus moves to input on open and to the trigger button on close, never to a programmatically blurred target.
- **Free/Pro boundary**: untouched. Pro can hook `pulsepress_after_capture` (Session 4) to add ESP sync without modifying Session 5's UI.
