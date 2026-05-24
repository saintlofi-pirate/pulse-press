## Why

Moonfarmer Reactions Lead Capture Free now has a real WordPress.org package path and a documented extension surface. Before building paid features, Pro needs a concrete implementation plan that aligns with Free instead of drifting into internal coupling.

The existing `docs/pro-roadmap.md` captures product direction and feature sketches. This change adds the engineering execution plan: separate repo shape, bootstrap contract, Free hook reuse map, Pro-owned storage, service providers, feature sessions, validation matrix, and release coordination.

## What Changes

- Add `docs/pro-addon-implementation-plan.md`.
- Define the Pro addon as a separate private plugin repo that supports PHP 7.4 through 8.4.
- Map each Pro capability to the Free hook/JS surface it must reuse.
- Split Pro implementation into small sessions from bootstrap through white-labeling.
- Record the open decisions needed before paid feature implementation.

## Out Of Scope

- Creating the private `moonfarmer-reactions-lead-capture-pro` repo.
- Implementing Pro code.
- Choosing the first ESP provider or license server vendor.
- Changing Free hooks in this slice.

## Free/Pro Boundary

Pro must extend Free through documented hooks and JS renderer registration only. If a needed surface is missing, Free gets an additive hook first.
