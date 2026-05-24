## ADDED Requirements

### Requirement: Inline capture form renders after a positive reaction

When a visitor's `activeType` is in the positive-reactions allowlist (default `['love', 'insightful', 'funny']` from `MoonfarmerReactionsLeadCaptureData.positiveReactions`) AND the per-post captured flag is `false` AND the per-post dismissed flag is `false`, the widget SHALL render a `<form class="moonfarmer-reactions-lead-capture-capture">` element immediately below the reaction button row. When any of those conditions fails, no form SHALL be rendered.

#### Scenario: Positive reaction shows the form

- **WHEN** the visitor clicks the Love button on a fresh post
- **THEN** after the optimistic update, a `<form class="moonfarmer-reactions-lead-capture-capture">` element appears below the reaction row containing a labelled email input, a labelled consent checkbox, and a primary submit button

#### Scenario: Negative reaction hides the form

- **WHEN** the visitor clicks Sad (not in the default positive set)
- **THEN** no `<form class="moonfarmer-reactions-lead-capture-capture">` is rendered

#### Scenario: Filter narrows the positive set

- **WHEN** PHP registers `add_filter('moonfarmer_reactions_lead_capture_positive_reactions', fn() => ['love'])` so `MoonfarmerReactionsLeadCaptureData.positiveReactions === ['love']`, and the visitor clicks Insightful
- **THEN** no form is rendered

#### Scenario: Already-captured suppresses the form

- **WHEN** `localStorage['moonfarmer-reactions-lead-capture:captured:42'] === '1'` and the visitor clicks Love on post 42
- **THEN** no form is rendered

#### Scenario: Dismissed-in-session suppresses the form

- **WHEN** the visitor opens the form, presses Escape, then clicks a different positive reaction on the same post
- **THEN** no form re-renders for the remainder of that page load

### Requirement: Form uses real HTML form semantics

The capture form SHALL be a real `<form>` element. The email input SHALL be `<input type="email" required>` paired with a visible `<label>`. The consent control SHALL be `<input type="checkbox" required>` paired with a visible `<label>` plus helper text linked via `aria-describedby`. The submit control SHALL be `<button type="submit">`.

#### Scenario: Inspecting the rendered form

- **WHEN** the form is open
- **THEN** the document contains exactly one `<form>` element with the `moonfarmer-reactions-lead-capture-capture` class, one `<input type="email">` with a `<label for="…">` associated to it, one `<input type="checkbox">` with a `<label>` plus an associated helper paragraph linked via `aria-describedby`, and one `<button type="submit">`

### Requirement: Focus management on open and on success

When the form opens, focus SHALL move to the email input within one render cycle. On successful submission (200 or 409), focus SHALL return to the triggering reaction button (the button matching `activeType`).

#### Scenario: Email input takes focus on open

- **WHEN** the form mounts
- **THEN** `document.activeElement` equals the email input

#### Scenario: Reaction button takes focus on success

- **WHEN** the form submits and the server returns 200
- **THEN** the success message renders with `role="status"` and within 100 ms `document.activeElement` equals the active reaction button

#### Scenario: Pressing Escape closes and restores focus

- **WHEN** the form has focus and the visitor presses Escape
- **THEN** the form unmounts, focus returns to the active reaction button, and the dismissed flag is set for the current page load

### Requirement: Validation errors render with role="alert"

When the server returns a validation error (any 4xx other than 409), the form SHALL render the server's `message` field verbatim inside a `<p role="alert">` adjacent to the relevant input. The submit button SHALL re-enable. The email and consent state SHALL be preserved so the visitor can correct and resubmit.

#### Scenario: Invalid email error

- **WHEN** the server returns `422` with `code: 'moonfarmer_reactions_lead_capture_invalid_email'` and `message: "Please enter a valid email address."`
- **THEN** the form renders `<p role="alert">Please enter a valid email address.</p>` near the email input, the submit button is enabled, and the email input retains its typed value

#### Scenario: Missing consent error from the client side

- **WHEN** the visitor submits without checking the consent checkbox
- **THEN** the form does not POST; instead it renders a client-side `<p role="alert">` with the consent-required message and focus moves to the checkbox

#### Scenario: Network failure

- **WHEN** the `fetch` rejects with a network error
- **THEN** the form renders `i18n.capture.networkError` in a `<p role="alert">` and the submit button re-enables; email and consent state are preserved

### Requirement: Success state announces and locks the form for this post

On a 200 response (`status: 'inserted'`) OR a 409 response (`status: 'already_exists'`), the widget SHALL set `localStorage['moonfarmer-reactions-lead-capture:captured:{postId}'] = '1'` and replace the form with a `<p role="status" aria-live="polite">` announcing the thank-you message. Subsequent positive reactions on the same post SHALL NOT re-render the form.

#### Scenario: Successful new capture

- **WHEN** the server returns 200 with `status: 'inserted'`
- **THEN** `localStorage['moonfarmer-reactions-lead-capture:captured:42']` becomes `'1'`, the form is replaced with `<p role="status">Thanks — we'll keep you in the loop.</p>`, and a second click on Insightful (also positive) does not re-render the form

#### Scenario: Already-existing capture

- **WHEN** the server returns 409 with code `moonfarmer_reactions_lead_capture_capture_already_exists`
- **THEN** the form is replaced with a friendly already-captured message; the captured flag is set; the success state is announced via `role="status"`

### Requirement: Submission posts to /capture with consent: true

The submit handler SHALL POST to `${data.root}capture` with header `X-WP-Nonce: ${data.nonce}`, content-type JSON, credentials same-origin, and a body of exactly `{post_id, email, reaction_type, consent: true, source: "inline"}`. It SHALL NOT send `consent: 1`, `"true"`, or any other truthy non-boolean.

#### Scenario: Submit body shape

- **WHEN** the visitor submits the form
- **THEN** the network request body is the JSON object above with `consent` being the boolean `true`

### Requirement: Reduced-motion users get instant transitions

The form's appearance, the success state replacement, and any height transitions SHALL be instantaneous when `prefers-reduced-motion: reduce` matches.

#### Scenario: Reduced-motion user opens form

- **WHEN** `window.matchMedia('(prefers-reduced-motion: reduce)').matches` is `true` and the visitor clicks a positive reaction
- **THEN** the form appears without any height or opacity animation

### Requirement: Localised strings drive every visible label

Every visible string in the form SHALL come from `data.i18n.capture.*` (prompt, label, placeholder, consent, submit, submitting, thanks, alreadyCaptured, networkError, expiredNonce). No string SHALL be hardcoded English in the component.

#### Scenario: Strings are sourced from i18n

- **WHEN** inspecting the rendered form
- **THEN** the prompt text, email label, consent label and helper, submit button label, and success message all match the corresponding `data.i18n.capture.*` values

## MODIFIED Requirements

### Requirement: Widget renders six reactions with counts and active state

The Preact widget SHALL render exactly six `<button>` elements (one per reaction in `MoonfarmerReactionsLeadCaptureData.reactions`), each containing an inline SVG icon, a visible count, an `aria-label` naming the reaction with its current count and selected state, and `aria-pressed` reflecting whether that reaction is the visitor's current choice. Counts SHALL come from a `GET /counts/{postId}` request on mount. The active reaction SHALL come from `localStorage` under `moonfarmer-reactions-lead-capture:reaction:{postId}` if present. After a positive-reaction click that does not match an existing captured flag for the post, the widget SHALL render the inline capture form below the buttons (see `Requirement: Inline capture form renders after a positive reaction`).

#### Scenario: First paint on a post with no prior reactions

- **WHEN** the widget mounts on a post whose counts endpoint returns `{counts: {}}`
- **THEN** six buttons render, each with a count of `0` and `aria-pressed="false"`

#### Scenario: Returning visitor sees their stored active state

- **WHEN** `localStorage.getItem('moonfarmer-reactions-lead-capture:reaction:42')` returns `'love'` and the page is for post 42
- **THEN** the `love` button renders with `aria-pressed="true"` and is visually tinted with `var(--moonfarmer-reactions-lead-capture-accent)`

#### Scenario: Counts from server replace localStorage on reconciliation

- **WHEN** the server returns counts including `love: 5` and `localStorage` did not record an active reaction
- **THEN** the love button shows `5` and remains `aria-pressed="false"`

#### Scenario: Positive reaction triggers the inline capture form

- **WHEN** a visitor with no captured flag for the post clicks Love (in the positive set) and the optimistic update completes
- **THEN** the inline capture form is rendered below the reaction row in the same widget container
