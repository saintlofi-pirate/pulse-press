## MODIFIED Requirements

### Requirement: ToggleField surface text is localised

`ToggleField` SHALL render its visible on/off state strings from the admin i18n payload rather than hardcoded English. The admin localiser SHALL provide `adminData.i18n.toggle = { on: string, off: string }`. The component SHALL accept an optional `labels` prop carrying that shape and fall back to `'On' / 'Off'` only when the prop is omitted.

#### Scenario: English admin sees default copy
- **GIVEN** the admin payload's `i18n.toggle.on` is `"On"` and `i18n.toggle.off` is `"Off"`
- **WHEN** a ToggleField is rendered in the checked state
- **THEN** the visible state text SHALL read `"On"`

#### Scenario: Translated locale sees translated copy
- **GIVEN** `i18n.toggle.on` has been localised to `"Ein"` and `i18n.toggle.off` to `"Aus"`
- **WHEN** a ToggleField is rendered in the unchecked state
- **THEN** the visible state text SHALL read `"Aus"`

### Requirement: Tab panel container has a visible focus indicator

The `.moonfarmer-reactions-lead-capture-panel` element SHALL show a visible focus ring when reached via the keyboard (`:focus-visible`). The non-keyboard focused state SHALL remain unstyled.

#### Scenario: Keyboard user lands on the panel
- **GIVEN** an admin user tabs from the active tab button into the panel
- **WHEN** the panel receives focus from the keyboard
- **THEN** a 2px accent ring SHALL render around the panel with appropriate offset, matching the rest of the admin's focus-visible treatment

#### Scenario: Click does not flash the ring
- **GIVEN** an admin user clicks inside the panel
- **WHEN** the panel programmatically receives focus
- **THEN** the focus ring SHALL NOT render (the `:focus-visible` heuristic suppresses it)
