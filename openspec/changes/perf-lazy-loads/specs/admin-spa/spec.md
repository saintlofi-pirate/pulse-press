## MODIFIED Requirements

### Requirement: Analytics chart and top-posts table load on demand

The admin SPA SHALL NOT include `DailySeriesChart` or `TopPostsTable` in its entry chunk. Both components SHALL be loaded via dynamic `import()` triggered when `AnalyticsSection` first mounts. The build SHALL emit each as its own Vite chunk.

#### Scenario: Admin tabs other than Analytics never load chart code
- **GIVEN** an admin lands on Display, Reactions, Capture, or Privacy
- **WHEN** the admin SPA mounts and renders that tab
- **THEN** `DailySeriesChart.*.js` and `TopPostsTable.*.js` SHALL NOT be requested

#### Scenario: Visiting Analytics streams the chart and table
- **GIVEN** the admin clicks the Analytics tab
- **WHEN** `AnalyticsSection` mounts
- **THEN** both lazy chunks SHALL be requested in parallel with the analytics REST fetch and SHALL render as soon as they resolve

#### Scenario: Placeholder while chart loads
- **GIVEN** the analytics envelope has loaded but the chart chunk is still in flight
- **WHEN** AnalyticsSection renders
- **THEN** a skeleton placeholder element with `role="status"` SHALL occupy the chart's footprint until the component mounts
