# LibreCrawl

## What this project actually does

LibreCrawl is a Flask-backed site crawler and SEO-audit application. The source is organized around a `WebCrawler` orchestrator that owns crawl configuration, HTTP session state, robots/cache state, result buffers, and modular helpers for rate limiting, link extraction, JavaScript rendering, sitemap discovery, issue detection, and SEO extraction (`research/LibreCrawl/src/crawler.py`, `research/LibreCrawl/src/core/*`). The application layer adds authentication, crawl persistence, status endpoints, exports, and a browser dashboard (`research/LibreCrawl/main.py`, `research/LibreCrawl/src/auth_db.py`, `research/LibreCrawl/src/crawl_db.py`).

For our purposes, the useful parts are not the web app or account system. They are the crawler evidence patterns: fetch-failure classification, a single page-result dictionary enriched by extractors, deterministic link discovery, sitemap discovery, and issue objects generated from extracted evidence.

## Relevant source-code areas inspected

- `research/LibreCrawl/src/crawler.py` — main crawl orchestration, default crawl settings, HTTP session setup, fetch failure classification, robots cache, crawl state, and component wiring.
- `research/LibreCrawl/src/core/seo_extractor.py` — extraction of title, meta description, robots, canonical, headings, OpenGraph/Twitter metadata, JSON-LD, microdata/schema.org, response timing, and body metrics.
- `research/LibreCrawl/src/core/issue_detector.py` — rule-based conversion of extracted page fields into issue dictionaries for title, meta description, headings, content, status code, canonical, mobile, accessibility, social metadata, structured data, and performance.
- `research/LibreCrawl/src/core/link_manager.py` — URL joining, fragment stripping, internal/external classification, duplicate avoidance, and source-page tracking.
- `research/LibreCrawl/src/core/sitemap_parser.py` — common sitemap URL probing, robots.txt `Sitemap:` discovery, gzip handling, sitemap-index recursion, XML namespace stripping, and URL extraction.
- `research/LibreCrawl/src/core/js_renderer.py` — optional Playwright rendering path; useful as a later adapter idea but outside v0.1 core.
- `research/LibreCrawl/src/crawl_db.py` and `research/LibreCrawl/main.py` — persistence, checkpoints, history, exports, and web endpoints; inspected mainly to identify what should stay out of our framework-agnostic core.

## Useful concepts for our visibility-detection project

- **Fetch failure classification.** `classify_fetch_error()` maps DNS, timeout, refused connection, SSL, and generic connection failures into stable machine-readable values (`research/LibreCrawl/src/crawler.py`). This is directly relevant to `PageSnapshot` error evidence and `uncertain` visibility reports.
- **Composable crawler helpers.** `WebCrawler` wires separate helpers (`SEOExtractor`, `LinkManager`, `SitemapParser`, `IssueDetector`, `RateLimiter`) rather than mixing every concern into the crawl loop (`research/LibreCrawl/src/crawler.py`). We can mirror the separation with `PageFetcher`, `PageParser`, `Detector`, and optional sitemap/robots services.
- **Metadata extraction as normalized fields.** `SEOExtractor` writes page facts such as `title`, `meta_description`, `canonical_url`, robots data, social tags, structured data, headings, links, and size/timing metrics into a result structure (`research/LibreCrawl/src/core/seo_extractor.py`). Our `ParsedPage` can be a typed version of this pattern.
- **Simple issue detectors over evidence.** `IssueDetector` checks page result fields and emits issue dictionaries with category/severity/details (`research/LibreCrawl/src/core/issue_detector.py`). Our findings should keep this idea but use stable codes, confidence, structured evidence, and recommendations.
- **Sitemap discovery from robots.txt.** `SitemapParser` tries conventional sitemap paths and also reads `Sitemap:` directives from robots.txt (`research/LibreCrawl/src/core/sitemap_parser.py`). Later product visibility diagnostics can say whether a product URL is absent from known sitemap sources.
- **Link normalization.** `LinkManager` converts relative links to absolute URLs, strips fragments, compares domains without leading `www.`, tracks link locations, and avoids duplicates (`research/LibreCrawl/src/core/link_manager.py`). This maps to URL variant matching and product-page internal-link evidence.

## Reusable implementation ideas

- Define a `FetchFailureType` enum/value object inspired by `classify_fetch_error()` with values such as `dns_not_found`, `timeout`, `connection_refused`, `ssl_error`, and `connection_error` (`research/LibreCrawl/src/crawler.py`).
- Keep `PageFetcher` transport evidence separate from `PageParser` semantic evidence, even if an orchestrator combines them, following LibreCrawl's separate crawler/extractor/detector components (`research/LibreCrawl/src/crawler.py`, `research/LibreCrawl/src/core/seo_extractor.py`, `research/LibreCrawl/src/core/issue_detector.py`).
- Use a sitemap service that returns discovered sitemap URLs, parsed product/page URLs, parse failures, and source (`robots.txt`, default location, nested sitemap), adapting the discovery flow from `SitemapParser` (`research/LibreCrawl/src/core/sitemap_parser.py`).
- Use a fixture-friendly issue detector approach: feed a normalized page evidence array/object into small checks, as `IssueDetector.detect_issues()` does over a result dictionary (`research/LibreCrawl/src/core/issue_detector.py`).
- Capture link source context, such as where a link came from, as an evidence field for future internal discoverability findings (`research/LibreCrawl/src/core/link_manager.py`).

## What NOT to reuse

- Do not reuse the Flask app, authentication, email, sessions, guest limits, or SQLite persistence code; those are application concerns and conflict with a Composer package core (`research/LibreCrawl/main.py`, `research/LibreCrawl/src/auth_db.py`, `research/LibreCrawl/src/crawl_db.py`).
- Do not copy its broad generic SEO issue list directly. Our project should explain product search visibility, not emit a generic site-audit checklist (`research/LibreCrawl/src/core/issue_detector.py`).
- Do not put Playwright/browser rendering in v0.1 core. `JavaScriptRenderer` is useful only as a future optional adapter pattern (`research/LibreCrawl/src/core/js_renderer.py`).
- Do not copy mutable dictionary result shapes. Our public package API should prefer typed value objects such as `PageSnapshot`, `ParsedPage`, and `Finding`.

## Possible role in our future system

- **crawler reference** — useful for crawl orchestration boundaries, fetch failure classification, sitemap discovery, and link normalization.
- **SEO analyzer reference** — useful for metadata, canonical, heading, social tag, and structured-data extraction ideas.
- **structured data validator reference** — low-to-medium usefulness; it detects presence of JSON-LD/schema.org but does not deeply validate Product/Offer semantics.
- **report generator reference** — low usefulness; exports exist, but the shape is app/reporting oriented rather than package API oriented.

## Concrete lessons for our roadmap

- Build a `PageSnapshot` transport model with URL, status, headers, body/media type, timing, redirects, and stable fetch failure type.
- Build a `ParsedPage` model with title, description, canonical, robots directives, headings, links, social metadata, JSON-LD blocks, microdata types, word/text counts, and parse warnings.
- Build a sitemap-inspection module that discovers sitemap locations from conventional paths and robots.txt, handles sitemap indexes and gzip, and records source/failure evidence.
- Build URL/link normalization that removes fragments, resolves relative URLs, compares domains consistently, and records source context.
- Build detectors as small classes over normalized evidence rather than as a monolithic crawler rule list.
- Add deterministic fixtures for fetch failures, sitemap presence/absence, canonical mismatch, no structured data, and tracking/fragment URL normalization.
