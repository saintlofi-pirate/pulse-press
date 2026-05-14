## MODIFIED Requirements

### Requirement: CaptureForm announces as a labelled dialog

The inline email capture form SHALL be exposed to assistive technology as a dialog. The form's outer container SHALL carry `role="dialog"`, `aria-modal="true"`, and `aria-labelledby` referencing a visible `<h3>` whose text is the capture prompt copy (existing `i18n.prompt`). The existing keyboard contract — auto-focus the email input on open, Escape dismisses and returns focus to the triggering reaction button — SHALL be preserved.

#### Scenario: Screen reader announces a dialog
- **GIVEN** a visitor clicks a positive reaction
- **WHEN** the capture form mounts
- **THEN** the rendered container SHALL have `role="dialog"`, `aria-modal="true"`, and `aria-labelledby` pointing at an `<h3>` element bearing the prompt text

#### Scenario: Title is not duplicated
- **GIVEN** the form renders the prompt copy as its dialog title
- **WHEN** the user views the page visually
- **THEN** the prompt SHALL render exactly once (the `<h3>` replaces the previous `<p class="pulsepress-capture-prompt">`)

#### Scenario: Escape and focus return unchanged
- **GIVEN** the dialog is open
- **WHEN** the user presses Escape
- **THEN** the dialog SHALL dismiss and focus SHALL return to the triggering reaction button, matching pre-change behaviour
