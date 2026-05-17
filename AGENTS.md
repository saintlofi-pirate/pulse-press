# Repository Instructions

## Project

PulsePress is a WordPress plugin for reactions, inline email capture, and analytics. The free plugin should be useful on its own; Pro should add provider sync, longer analytics, A/B testing, advanced targeting, webhooks, white-labeling, and support.

## PR Draft Template

For PR drafts in this repository, use this exact body unless the user explicitly asks for a different format:

```md
## What does this PR do and why?

<!-- What: brief description of the change. Why: the problem or need behind it. -->

**Related issue:** <!-- Fixes #123 or N/A -->

## Scope

- [ ] Free plugin
- [ ] Pro plugin
- [ ] Both

## Changes

<!-- Check all that apply. -->

- [ ] PHP (backend logic, models, services, hooks)
- [ ] Vue/React (admin UI, block editor)
- [ ] CSS/SCSS (styling)
- [ ] Database (migrations, schema changes)
- [ ] REST API (new or changed endpoints)
- [ ] Build/config (Vite, composer, CI)

## How to test

<!--
Steps for the reviewer to verify this works.
Be specific — which page, what input, what to expect.
-->

1.

## Screenshots

<!-- Before/after screenshots for UI changes. Remove this section if not applicable. -->

## Anything the reviewer should know?

<!-- Edge cases, trade-offs, things you're unsure about, or areas you'd like extra scrutiny on. -->
```

## Commit Message Style

Use uppercase, feature-wise prefixes by default:

- `FIX: ...`
- `FEAT: ...`
- `CHORE: ...`
- `REFACTOR: ...`
- `TEST: ...`
- `DOCS: ...`
- `BUILD: ...`
- `CI: ...`
- `PERF: ...`
- `SECURITY: ...`
- `STYLE: ...`

Commit messages should include a short uppercase-prefixed subject line and the same structured body used for PR drafts unless the user asks for a different format.

## OpenSpec

- Use OpenSpec for non-trivial product, schema, API, analytics, privacy, or free/pro boundary changes.
- Prefer proposal/design/tasks/spec artifacts before implementation when the change affects product behavior or data contracts.
- Validate changes with `openspec validate <change-id> --strict --no-interactive` before calling the plan complete.
- Treat OpenSpec telemetry or PostHog network errors as non-blocking unless the actual validation command fails.

## Accessibility

Full spec: `docs/pulsepress-v1-plan.md` §Accessibility — WCAG 2.1 AA First. Summary:

- Real semantic elements only — `<button>`, `<a>`, `<input>`, `<label>`, `<dialog>` — never `<div>` with onclick.
- Keyboard works for everything; visible focus rings via `:focus-visible`; modals manage focus + close on Escape.
- WCAG AA contrast: 4.5:1 text, 3:1 non-text UI. Colour is never the only cue. Active state is `aria-pressed`, not just visual.
- `prefers-reduced-motion: reduce` honoured everywhere; no auto-play, no flashing.
- Icon-only buttons have `aria-label`; decorative icons are `aria-hidden`. Form errors point via `aria-describedby` to `role="alert"`.
- Every shipped UI slice is keyboard-tested **and** screen-reader-spot-tested before its OpenSpec change can close.
- Automated tests assert ARIA attributes where applicable. Session 11's a11y pass is regression prevention, not a fix-everything slog.

A UI PR that can't tick the relevant boxes goes back for revision before merge.

## Code Quality

Full spec: `docs/pulsepress-v1-plan.md` §Code Quality Principles. Summary:

- **Hooks and filters first.** Every decision point gets `apply_filters('pulsepress_<thing>', ...)`; every side effect gets `do_action('pulsepress_<noun>_<verb>', ...)`. Pro never modifies Free internals — it attaches via hooks. Document new hooks in `docs/hooks-and-filters.md` and in the change's OpenSpec spec under ADDED Requirements **in the same commit** that introduces them.
- **Clean.** Names earn length. No dead code. Comments explain WHY, never WHAT. No premature abstractions. No defensive error handling for things the framework or type system already guards.
- **Modular.** One responsibility per file. Constructor injection over service location. Static helpers reserved for value objects; anything with state/side-effects is instance-class. Service providers are the wiring layer; feature code doesn't `new` its own collaborators. Files over ~200 lines are a smell.
- **Maintainable.** Test the contract, not the implementation. Runtime PHP must stay compatible with PHP 7.4 through 8.4. Use typed parameters, returns, and properties where PHP 7.4 allows them; avoid PHP 8-only syntax in shipped runtime files. `final class` by default; inheritance is opt-in. Reuse existing helpers (`Schema::tableName`, etc.) before writing new ones. Migrations + option keys are append-only.
- **Easy to extend.** Public methods of repositories/services are part of the API surface — once shipped, they're stable. Settings/meta/transient/option keys are namespaced under `pulsepress_` / `_pulsepress_` and documented.

A PR that violates these goes back for changes; an OpenSpec design that violates them gets rewritten before code starts.

## Code Review Graph

- Prefer code-review-graph for structural exploration once it is installed and built for this repo.
- After every `git pull` or `git push`, refresh the local graph with `code-review-graph update` when the command is available.
- Before PR publication, run `code-review-graph detect-changes --brief` when the repo is a Git checkout and the graph has been built.
- If the graph is empty or stale, run `code-review-graph build` before relying on graph results.
