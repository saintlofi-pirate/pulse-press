## MODIFIED Requirements

### Requirement: CaptureForm loads on demand

The widget SHALL NOT include the `CaptureForm` component in its entry chunk. The component SHALL be loaded via dynamic `import()` only after a visitor clicks a positive reaction (i.e., when `showCapture` first becomes true). The build SHALL emit `CaptureForm` as its own Vite chunk.

#### Scenario: First-paint widget excludes capture code
- **GIVEN** a fresh page load with no prior reaction state
- **WHEN** the widget chunk is fetched and executed
- **THEN** the network panel SHALL NOT include `CaptureForm.*.js` until the user clicks a positive reaction

#### Scenario: First positive click triggers capture chunk
- **GIVEN** the widget is mounted and no capture chunk has loaded
- **WHEN** the visitor clicks a positive reaction
- **THEN** `CaptureForm.*.js` SHALL be requested exactly once, and `CaptureForm` SHALL render once it resolves

#### Scenario: Loading state is accessible
- **GIVEN** `showCapture` has become true and the capture chunk is still loading
- **WHEN** the widget renders
- **THEN** a placeholder element with `role="status"` and `aria-live="polite"` SHALL render in place of the capture form, so screen readers announce the brief loading state
