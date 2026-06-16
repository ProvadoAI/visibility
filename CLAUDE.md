# CLAUDE.md

Guidance for Claude Code when working in this repository.

## Project

`visibility-detector` — a deterministic, framework-agnostic PHP core that diagnoses
ecommerce **product visibility**: for a given product and a given buyer search, did
the product appear, and if not, what evidence-backed technical reasons explain why.

It is a diagnostic engine, not a SaaS/dashboard/crawler. Key principles (see
`docs/architecture.md`): deterministic-first, evidence over opinions, no acquisition
coupling, no ecommerce-platform coupling.

## Source of truth

- `docs/project-objective.md` — product vision.
- `docs/mvp.md` — MVP definition and success criteria.
- `docs/architecture.md` — value objects, contracts, detectors, extension seams.
- `docs/roadmap/v0.x.md` — phased implementation roadmaps and live progress status.
- `docs/deferred.md` — the backlog of explicitly deferred capabilities, with the
  reason each is deferred and the condition that would unlock it. Reviewing it is a
  gate when closing out each version (see its "Review cadence" section): move shipped
  items to "Recently unlocked", re-check unlock conditions, and propose the next theme.

## Commit / PR workflow

- **Documentation changes** (`docs/**`, `*.md`, `CLAUDE.md`): commit directly to
  `main`. No PR, no need to ask first.
- **Code changes** (anything else — `src/`, `tests/`, `bin/`, `composer.json`,
  fixtures, scenarios): open a pull request and leave it for the owner to review and
  merge. Do not merge code PRs yourself. The owner pulls, tests, and reports back.

## Testing

- Run the suite with `composer test` (or `vendor/bin/phpunit`).
- The test suite must stay deterministic and network-free. Live/HTTP behavior is
  opt-in and must be tested with mock clients, never real network calls.
