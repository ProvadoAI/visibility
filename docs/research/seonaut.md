# seonaut

## What this project actually does

Seonaut is a Go web crawler and SEO audit/reporting application. Its crawler sends request/response messages through a queue, checks robots.txt and sitemaps, parses pages into a large `PageReport` model, runs page-level and multipage issue reporters, stores reports/issues, and exposes dashboards, exports, and web routes (`research/seonaut/internal/crawler/*`, `research/seonaut/internal/services/parser.go`, `research/seonaut/internal/models/pagereport.go`, `research/seonaut/internal/issues/*`, `research/seonaut/internal/repository/*`, `research/seonaut/internal/routes/*`).

For our project, Seonaut is one of the strongest references because it uses interface-driven fetching, evidence-rich response messages, per-page report DTOs, a central issue taxonomy, isolated issue reporters, canonical/indexability checks, robots/sitemap checking, and extensive unit tests.

## Relevant source-code areas inspected

- `research/seonaut/internal/crawler/basic_client.go` — `HTTPRequester` interface, `BasicClient`, GET/HEAD methods, basic-auth domain handling, user-agent parsing, and TTFB capture with `httptrace`.
- `research/seonaut/internal/crawler/crawler.go` — crawler options, request/response message types, queue/storage wiring, robots/sitemap integration, allowed-domain handling, and response callback architecture.
- `research/seonaut/internal/crawler/robots_checker.go` and `research/seonaut/internal/crawler/robots_checker_test.go` — per-host robots lookup/cache and tests.
- `research/seonaut/internal/crawler/sitemap_checker.go` — sitemap checking/inclusion support.
- `research/seonaut/internal/services/parser.go` — HTML/header parser for language, robots, canonical, hreflang, titles, descriptions, links, images, scripts, styles, iframes, media, meta refresh, and header fallbacks.
- `research/seonaut/internal/models/pagereport.go` — evidence-rich `PageReport` model with URL, parsed URL, redirects, status, media type, language, title, description, robots, noindex/nofollow, canonical, headings, links, words, hreflang, images, robots-blocked/sitemap state, depth, hash, timeout, and TTFB.
- `research/seonaut/internal/models/issue_reporter.go` — page and multipage reporter callback structures.
- `research/seonaut/internal/issues/errors/errors.go` — central issue/error taxonomy.
- `research/seonaut/internal/issues/page/canonical.go` and `canonical_test.go` — canonical multiple-tag, relative URL, and header-vs-HTML mismatch reporters and tests.
- `research/seonaut/internal/issues/page/indexability.go` and `indexability_test.go` — noindex, robots-blocked, sitemap/noindex, sitemap/blocked, noncanonical-in-sitemap, meta-in-body, and nosnippet reporters.
- `research/seonaut/internal/issues/multipage/*` — SQL-backed multipage issue reporters for duplicate titles/descriptions/body, canonical relationships, hreflang, orphan pages, links, and statuses.

## Useful concepts for our visibility-detection project

- **Interface-driven HTTP fetching.** `BasicClient` depends on an `HTTPRequester` interface and exposes `Get`, `Head`, and `GetUAName` (`research/seonaut/internal/crawler/basic_client.go`). Our `PageFetcher` should be interface-based and easy to fake in tests.
- **Evidence-rich response messages.** `ResponseMessage` includes URL, HTTP response, error, TTFB, blocked state, sitemap inclusion, timeout, and caller data (`research/seonaut/internal/crawler/crawler.go`). This maps well to `PageSnapshot` and `SearchResultSet` evidence.
- **Parser with HTML/header fallback rules.** `Parser.robots()`, `Parser.canonical()`, and `Parser.hreflangs()` prefer HTML values and fall back to HTTP headers, while `lang()` falls back from HTML lang to Content-Language (`research/seonaut/internal/services/parser.go`). Our parser should preserve source information for whichever source wins.
- **Page report as normalized evidence.** `PageReport` collects transport, indexability, metadata, content, link, sitemap, and timing evidence in one report model (`research/seonaut/internal/models/pagereport.go`). Our equivalent should be split into `PageSnapshot` and `ParsedPage` but maintain explicit fields.
- **Central issue taxonomy.** `errors.go` enumerates stable issue types such as noindex, blocked, sitemap noncanonical, canonical mismatch, nosnippet, timeout, and media type issues (`research/seonaut/internal/issues/errors/errors.go`). Our public `Finding.code` strings should be similarly stable.
- **Small detector/reporters.** `PageIssueReporter` pairs an error type with a callback over page report, HTML node, and headers (`research/seonaut/internal/models/issue_reporter.go`). Our `Detector` interface can be class-based but should retain this small, focused style.
- **Canonical/indexability details.** Canonical reporters distinguish multiple tags, relative canonical URLs, and HTML/header mismatch; indexability reporters cover noindex, robots-blocked, sitemap conflicts, meta tags in body, and nosnippet (`research/seonaut/internal/issues/page/canonical.go`, `research/seonaut/internal/issues/page/indexability.go`).
- **Multipage checks.** SQL reporters compare page reports for duplicates, hreflang return links, canonicals to noncanonical/noindex/redirect/error pages, and orphan pages (`research/seonaut/internal/issues/multipage/*`). These are later roadmap items after single-product checks.

## Reusable implementation ideas

- Define `PageFetcher` and `SearchProvider` as interfaces from the beginning, mirroring Seonaut's `Client` and `HTTPRequester` testable boundary (`research/seonaut/internal/crawler/basic_client.go`, `research/seonaut/internal/crawler/crawler.go`).
- Model `PageSnapshot` with fields analogous to `ResponseMessage`: requested URL, final URL/response, status, headers, TTFB, timeout, robots-blocked flag, sitemap inclusion, and fetch error (`research/seonaut/internal/crawler/crawler.go`).
- Model `ParsedPage` from a subset of `PageReport`: language, title, description, robots, noindex/nofollow, canonical, headings, links, hreflang, images, word count, content hash, and parser warnings (`research/seonaut/internal/models/pagereport.go`).
- Use stable string finding codes inspired by Seonaut's issue taxonomy, such as `page.noindex`, `robots.blocked`, `sitemap.noncanonical`, `canonical.mismatch`, `canonical.relative`, and `snippet.blocked` (`research/seonaut/internal/issues/errors/errors.go`).
- Add first-class tests for canonical and indexability detectors using raw HTML/header fixtures, following Seonaut's page issue test pattern (`research/seonaut/internal/issues/page/canonical_test.go`, `research/seonaut/internal/issues/page/indexability_test.go`).

## What NOT to reuse

- Do not reuse Seonaut's database/repository/routes/services as core package architecture; those are web-application concerns (`research/seonaut/internal/repository/*`, `research/seonaut/internal/routes/*`).
- Do not expose integer issue constants as our package API. Stable string codes are clearer for merchants and integrations (`research/seonaut/internal/issues/errors/errors.go`).
- Do not implement broad multipage/site-audit checks in v0.1. Product visibility starts with one product, expected queries, external results, URL matching, and page evidence.
- Do not copy source; use patterns only.

## Possible role in our future system

- **crawler reference** — high usefulness for interface-driven fetching, response evidence, queue/storage boundaries, robots/sitemap checks, and tests.
- **SEO analyzer reference** — high usefulness for parser fields, canonical/indexability detectors, and issue taxonomy.
- **testing/reference implementation** — high usefulness because the repository includes targeted tests for crawler storage, robots, canonical, indexability, headings, links, status, and other reporters.
- **report generator reference** — medium usefulness for page reports and exports, less for merchant-facing visibility narratives.

## Concrete lessons for our roadmap

- Build `PageFetcher` around an interface and capture TTFB, status, headers, timeout, redirects, media type, and fetch errors.
- Build `PageParser` with explicit HTML/header fallback logic for robots, canonical, hreflang, and language, preserving source evidence.
- Build `FindingCode` constants as stable strings and treat them as package API.
- Build small detectors for `page.noindex`, `robots.blocked`, `sitemap.noindex`, `sitemap.blocked`, `sitemap.noncanonical`, `canonical.relative`, `canonical.multiple`, `canonical.mismatch`, and `snippet.blocked`.
- Add fixture tests for each detector with raw HTML and header combinations.
- Defer multipage and database-backed checks until a later adapter or reporting layer exists.
