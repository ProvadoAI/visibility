# lighthouse

## What this project actually does

The inspected Lighthouse source is a large browser-driven audit framework. It gathers browser artifacts, runs audit classes with declared metadata, normalizes audit products into scored results, and renders structured details. The SEO-related source includes audits for crawlability, canonical links, hreflang, HTTP status, anchors/link text, meta descriptions, robots.txt, and a manual structured-data reminder (`research/lighthouse/core/audits/seo/*`).

For our visibility detector, Lighthouse is most useful as a reference for detector metadata, evidence-rich audit output, canonical edge cases, and indexability logic. It is not a package we should wrap in core, because its runtime assumes browser artifacts, DevTools logs, Chrome, and Lighthouse scoring (`research/lighthouse/core/runner.js`, `research/lighthouse/core/audits/audit.js`).

## Relevant source-code areas inspected

- `research/lighthouse/core/audits/audit.js` — abstract audit contract, metadata requirements, score normalization, details helpers, node/source detail helpers, warnings, explanations, and result generation.
- `research/lighthouse/core/audits/seo/is-crawlable.js` — noindex/none/unavailable_after handling, user-agent-specific robots meta/header handling, X-Robots-Tag handling, robots.txt parsing, and warning behavior when only some bots are blocked.
- `research/lighthouse/core/audits/seo/canonical.js` — canonical link collection, body-link exclusion, invalid URL detection, relative canonical detection, missing canonical as not-applicable, multiple conflicting canonicals, hreflang/canonical mismatch, and homepage-root canonical mistake detection.
- `research/lighthouse/core/audits/seo/hreflang.js` — validation of alternate/hreflang links for expected language codes, fully qualified URLs, and source reporting from HTML or headers.
- `research/lighthouse/core/audits/seo/meta-description.js` — minimal audit that fails missing or empty meta description.
- `research/lighthouse/core/audits/seo/robots-txt.js` — robots.txt parse/fetch audit behavior.
- `research/lighthouse/core/audits/seo/manual/structured-data.js` — confirms that Lighthouse treats structured data as a manual check, not a deep validator.

## Useful concepts for our visibility-detection project

- **Detectors declare metadata.** Each audit exposes `meta` with an ID, title, failure title, description, supported modes, and required artifacts (`research/lighthouse/core/audits/audit.js`, `research/lighthouse/core/audits/seo/canonical.js`). Our detectors can declare stable `code`, required evidence, default severity, and recommendation templates.
- **Indexability is multi-source.** `is-crawlable.js` checks HTML meta robots, X-Robots-Tag headers, user-agent prefixes, `unavailable_after`, and robots.txt for multiple known bots (`research/lighthouse/core/audits/seo/is-crawlable.js`). This is directly relevant to explaining why a product page may not appear in search results.
- **Bot-specific uncertainty.** Lighthouse passes crawlability if at least one known bot is allowed, but emits warnings for blocked bot user agents (`research/lighthouse/core/audits/seo/is-crawlable.js`). Our reports should similarly distinguish `not_visible` from `uncertain` when one provider/agent is blocked and another is not.
- **Canonical edge-case taxonomy.** `canonical.js` distinguishes invalid, relative, missing, conflicting, cross-language, and root-homepage canonicals (`research/lighthouse/core/audits/seo/canonical.js`). These map well to separate finding codes.
- **Structured details instead of strings only.** Audit details can be tables, snippets, source locations, nodes, warnings, and explanations (`research/lighthouse/core/audits/audit.js`). Our findings should carry structured evidence, not just rendered prose.
- **Structured data gap.** The manual structured-data audit points users elsewhere instead of validating JSON-LD itself (`research/lighthouse/core/audits/seo/manual/structured-data.js`). Product schema validation remains something we need to design ourselves.

## Reusable implementation ideas

- Create a `DetectorMetadata` structure with `code`, `title`, `failureTitle`, `description`, `requiredEvidence`, and `defaultSeverity`, adapting the discipline of Lighthouse audit metadata without adopting its scoring system (`research/lighthouse/core/audits/audit.js`).
- Implement indexability detectors for `meta robots`, bot-specific `meta name="googlebot"`, `X-Robots-Tag`, `unavailable_after`, and robots.txt blocks, inspired by `is-crawlable.js` (`research/lighthouse/core/audits/seo/is-crawlable.js`).
- Model canonical findings as separate codes: `canonical.invalid`, `canonical.relative`, `canonical.conflicting`, `canonical.points_to_homepage`, `canonical.hreflang_conflict`, and `canonical.header_html_mismatch`, informed by Lighthouse and Seonaut canonical logic (`research/lighthouse/core/audits/seo/canonical.js`, `research/seonaut/internal/issues/page/canonical.go`).
- Return evidence tables/snippets/source references in `Finding.evidence`, following Lighthouse's table/source-location details pattern (`research/lighthouse/core/audits/audit.js`).
- Treat Lighthouse scoring only as a UI/reporting inspiration. For product visibility, a detector result should be evidence-backed severity/confidence rather than a 0-to-1 performance score.

## What NOT to reuse

- Do not wrap Lighthouse or require Chrome/DevTools in v0.1 core. Its gatherer/runtime model is too heavy for a framework-agnostic PHP package (`research/lighthouse/core/runner.js`).
- Do not reuse generic Lighthouse performance/PWA/accessibility scoring for product visibility diagnosis (`research/lighthouse/core/scoring.js`, `research/lighthouse/core/audits/audit.js`).
- Do not stop at Lighthouse's manual structured-data check. Our product use case requires extracting JSON-LD/microdata and validating Product/Offer fields (`research/lighthouse/core/audits/seo/manual/structured-data.js`).
- Do not mirror Lighthouse's binary score as the main product API; our status values should remain `visible`, `not_visible`, and `uncertain` with supporting findings.

## Possible role in our future system

- **SEO analyzer reference** — high usefulness for canonical and indexability edge cases.
- **structured data validator reference** — low usefulness because structured data is manual rather than validated.
- **report generator reference** — high conceptual usefulness for structured audit metadata/details, but not for its exact scoring UI.
- **testing/reference implementation** — medium usefulness because SEO audits are isolated units with predictable artifact inputs.

## Concrete lessons for our roadmap

- Build an `IndexabilityDetector` that checks meta robots, bot-specific robots meta, X-Robots-Tag, `unavailable_after`, and robots.txt evidence.
- Build canonical detectors that distinguish absent, invalid, relative, multiple conflicting, homepage-root, hreflang conflict, and header-vs-HTML mismatch cases.
- Add `Finding.evidence` support for source type, source URL, header name/value, HTML snippet, and parser warnings.
- Add detector metadata so reports are stable and localizable later without changing detector code.
- Add tests for bot-specific directives, generic robots fallback, X-Robots-Tag, robots.txt blocked/allowed, relative canonical, multiple canonicals, and canonical pointing to homepage.
