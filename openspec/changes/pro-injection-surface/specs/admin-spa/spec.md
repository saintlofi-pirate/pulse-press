## MODIFIED Requirements

### Requirement: Admin SPA tab list

The admin SPA SHALL render tabs from `adminData.tabs` rather than a hardcoded list. Tabs SHALL be presented in the order PHP returned them (already sorted by `order`). Hash routing SHALL honour every registered tab id; navigating to a hash matching any registered tab SHALL activate that tab. If the hash does not match any registered tab, the SPA SHALL activate the first tab.

#### Scenario: Five Free tabs render
- **GIVEN** no Pro extension is installed
- **WHEN** the admin SPA mounts
- **THEN** the tab strip SHALL show five tabs in the order `display, analytics, reactions, capture, privacy`

#### Scenario: Pro tab activates via hash
- **GIVEN** `adminData.tabs` contains a `pro_esp` tab and a Pro renderer is registered
- **WHEN** the admin visits `…#pro_esp`
- **THEN** the SPA SHALL activate the Pro tab and render Pro's content inside its `ExtensionMount`

#### Scenario: Unknown hash falls back to first tab
- **GIVEN** the admin visits `…#nonexistent`
- **WHEN** the SPA initialises
- **THEN** the first tab in `adminData.tabs` SHALL be active

### Requirement: Analytics section accepts Pro injections

The Analytics tab SHALL render Free's four metric cards first, followed by extension metric cards in payload order. Entries with `renderJs` SHALL render via `<ExtensionMount kind="card" />`; entries without `renderJs` SHALL render via a `MetricCard` component using `title`, `value`, `helper`, and `emphasis`. The top-posts table SHALL be followed by extension analytics panels rendered via `<ExtensionMount kind="panel" />`, each panel data taken from the entry's `data` field.

#### Scenario: No Pro cards, layout unchanged
- **GIVEN** `metricCards` and `analyticsPanels` are both empty
- **WHEN** the Analytics tab renders
- **THEN** the layout SHALL match the existing Free layout exactly (four cards, sentiment callout, daily series chart, top-posts table) with no extra DOM nodes for extension slots

#### Scenario: Pro card with renderJs uses ExtensionMount
- **GIVEN** `metricCards` contains `[{ id: 'compare', renderJs: 'compare_card', title: 't', value: '', helper: '', data: {} }]`
- **WHEN** the Analytics tab renders
- **THEN** an `ExtensionMount` with `kind='card'`, `id='compare'` SHALL appear after Free's four cards

#### Scenario: Pro panel renders below top posts
- **GIVEN** `analyticsPanels` contains one entry
- **WHEN** the Analytics tab renders
- **THEN** the corresponding `ExtensionMount kind='panel'` SHALL appear after the top-posts table
