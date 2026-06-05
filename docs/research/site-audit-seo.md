# site-audit-seo

## What this project actually does

`site-audit-seo` is a Node.js CLI/web tool that drives a headless Chrome crawler, evaluates JavaScript snippets inside rendered pages, optionally runs Lighthouse, writes CSV/JSON reports, validates collected fields with warning/error thresholds, and can upload or view reports (`research/site-audit-seo/src/index.js`, `research/site-audit-seo/src/program.js`, `research/site-audit-seo/src/scrap-site.js`, `research/site-audit-seo/src/validate.js`).

The useful parts are the configurable field presets, rendered-page extraction inventory, validation rules-as-data, report/export flow, and CLI option design. The Chrome crawler/Lighthouse runtime is too heavy for our v0.1 core.

## Relevant source-code areas inspected

- `research/site-audit-seo/src/scrap-site.js` — main crawler integration, URL-list parsing, partial report resume, field selection, optional Lighthouse launch, CSV exporter setup, rendered-page `evaluatePage()` extraction, screenshots, custom fields, request interception, and success/error hooks.
- `research/site-audit-seo/src/validate.js` — declarative validation rules for status, title, description, canonical count, canonical status, DOM size, timings, Lighthouse scores, and validation summaries.
- `research/site-audit-seo/src/presets/scraperFields.js` — preset field lists for default, minimal, SEO, headers, parse, Lighthouse, and Lighthouse-all reports.
- `research/site-audit-seo/src/presets/fields.js` and `research/site-audit-seo/src/presets/filters.js` — field metadata and report filters.
- `research/site-audit-seo/src/program.js` — CLI option parsing for URLs, presets, timeout, max depth, concurrency, Lighthouse, delay, custom fields, screenshots, robots/sitemap options, max requests, output paths, JSON/upload behavior, and post-parse normalization.
- `research/site-audit-seo/src/index.js` — CLI entrypoint and scan/CSV-conversion flow.
- `research/site-audit-seo/src/registry.js` — plugin registry hook for additional fields.

## Useful concepts for our visibility-detection project

- **Rendered extraction inventory.** `evaluatePage()` collects status-adjacent and DOM fields: request time, title, H1/counts, description, canonical, canonical count, OpenGraph, schema itemtypes, image/link counts, text ratio, DOM/head/body sizes, and microformat date (`research/site-audit-seo/src/scrap-site.js`). These fields are useful as a checklist for `ParsedPage`, even if we parse server HTML rather than render Chrome in core.
- **Field presets.** `scraperFields.js` groups fields into `seo-minimal`, `seo`, `headers`, `parse`, `lighthouse`, and `lighthouse-all` presets (`research/site-audit-seo/src/presets/scraperFields.js`). Our package can eventually have report “views” or serialization presets for basic URL matching, indexability, structured data, and content matching.
- **Validation rules as data.** `colsValidate` maps field names to warning/error lambdas and messages, then `validateResults()` applies those rules over collected fields (`research/site-audit-seo/src/validate.js`). Simple detectors such as missing title or multiple canonicals can use a declarative rule table if each result still becomes a structured finding.
- **CLI ergonomics.** `program.js` shows useful CLI controls: URL list, preset, timeout, max depth, concurrency, delay, follow XML sitemap, ignore robots, max requests, output directory/name, JSON, and custom fields (`research/site-audit-seo/src/program.js`). This can inform a later demo CLI for the Composer package.
- **Custom field hooks.** `scrap-site.js` exposes `__customFields()` into the browser and evaluates user-defined snippets (`research/site-audit-seo/src/scrap-site.js`). For our core, this suggests an extension seam for custom detectors, not arbitrary `eval`.
- **Partial/resumable reports.** The crawler can load a partial report and continue remaining URLs (`research/site-audit-seo/src/scrap-site.js`). Future batch visibility checks may need resumable snapshots.

## Reusable implementation ideas

- Use a small declarative rules layer for trivial field thresholds while keeping complex visibility checks as detector classes (`research/site-audit-seo/src/validate.js`).
- Define report field groups such as `search`, `url_match`, `indexability`, `metadata`, `structured_data`, and `content` inspired by scraper presets (`research/site-audit-seo/src/presets/scraperFields.js`).
- Build a later CLI with explicit options for query fixtures, provider, locale/device, timeout, output JSON, and strict/no-network mode, borrowing the option-rich style of `program.js` without tying to Node/Chrome (`research/site-audit-seo/src/program.js`).
- Capture canonical count, title/description length, H1 count, schema types, image/link counts, and text ratio as optional parsed page evidence (`research/site-audit-seo/src/scrap-site.js`).
- Add a plugin/custom-detector seam, but require typed PHP callables/classes instead of browser `eval` (`research/site-audit-seo/src/scrap-site.js`, `research/site-audit-seo/src/registry.js`).

## What NOT to reuse

- Do not require headless Chrome, `@popstas/headless-chrome-crawler`, or Lighthouse in v0.1 core (`research/site-audit-seo/src/scrap-site.js`).
- Do not use arbitrary custom-field `eval` in a package intended for merchant integrations (`research/site-audit-seo/src/scrap-site.js`).
- Do not copy its generic site-audit thresholds directly; product visibility findings need evidence, confidence, and recommendations rather than generic warnings (`research/site-audit-seo/src/validate.js`).
- Do not couple report generation to CSV-first output. Our primary API should be value objects and JSON-serializable reports.

## Possible role in our future system

- **report generator reference** — medium usefulness for field presets, validation summaries, CSV/JSON report flow, and CLI output behavior.
- **SEO analyzer reference** — medium usefulness for rendered DOM field inventory.
- **crawler reference** — low-to-medium usefulness because the crawler is browser-based and out of scope for core.
- **testing/reference implementation** — low usefulness; less fixture-driven than Seonaut for detector behavior.

## Concrete lessons for our roadmap

- Add report serialization groups so consumers can request compact search visibility output or full diagnostic evidence.
- Build declarative threshold detectors only for simple page fields such as missing title, empty description, multiple canonical tags, or low text ratio.
- Add a future CLI wrapper with fixture input, provider selection, locale/device metadata, timeout, and JSON output.
- Add optional rendered-page adapter later, outside core, for sites where server HTML lacks product content or schema.
- Design a typed custom-detector interface instead of arbitrary script evaluation.
