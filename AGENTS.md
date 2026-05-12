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

## Code Review Graph

- Prefer code-review-graph for structural exploration once it is installed and built for this repo.
- After every `git pull` or `git push`, refresh the local graph with `code-review-graph update` when the command is available.
- Before PR publication, run `code-review-graph detect-changes --brief` when the repo is a Git checkout and the graph has been built.
- If the graph is empty or stale, run `code-review-graph build` before relying on graph results.
