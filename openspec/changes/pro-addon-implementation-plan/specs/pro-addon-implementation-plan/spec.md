## ADDED Requirements

### Requirement: Pro addon plan exists

The repository SHALL include a Pro addon implementation plan that defines repo shape, bootstrap contract, Free reuse surface, Pro-owned storage, service providers, feature sessions, testing, release strategy, and open decisions.

#### Scenario: Plan can guide the first Pro session

- **WHEN** a developer opens `docs/pro-addon-implementation-plan.md`
- **THEN** they can identify the first implementation session, the minimum Free dependency, and the Free hook/JS surfaces Pro must reuse

### Requirement: Pro plan preserves Free/Pro boundary

The plan SHALL state that Pro extends Free through documented hooks and JS renderer registration only. It SHALL say that missing Pro surfaces are fixed by additive Free hooks, not Free file modification.

#### Scenario: Pro needs a new surface

- **WHEN** a planned Pro feature cannot be built from the documented Free hooks
- **THEN** the plan directs the developer to add a Free hook first

### Requirement: Pro plan matches Free PHP compatibility

The plan SHALL require Pro runtime code to support PHP 7.4 through 8.4, matching the Free package.

#### Scenario: Pro session verification

- **WHEN** a Pro implementation session is completed
- **THEN** its verification checklist includes runtime PHP lint across 7.4, 8.0, 8.1, 8.2, 8.3, and 8.4
