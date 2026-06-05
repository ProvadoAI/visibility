# Research Summary

## Repositories analyzed

| Repository | Primary category | Usefulness | Reason |
| --- | --- | --- | --- |
| `LibreCrawl` | crawler reference / SEO analyzer reference | medium | Useful modular crawler, fetch failure classification, sitemap discovery, link normalization, metadata extraction, and simple issue detection, but much of the app is Flask/auth/persistence/dashboard code outside our package scope (`research/LibreCrawl/src/crawler.py`, `research/LibreCrawl/src/core/seo_extractor.py`, `research/LibreCrawl/src/core/issue_detector.py`, `research/LibreCrawl/src/core/sitemap_parser.py`). |
| `lighthouse` | SEO/indexability analyzer / report metadata reference | high | Strong source for canonical edge cases, crawlability/noindex/X-Robots-Tag/robots.txt logic, detector metadata, structured details, warnings, and score normalization patterns, but too browser/Chrome-heavy to wrap in core (`research/lighthouse/core/audits/seo/is-crawlable.js`, `research/lighthouse/core/audits/seo/canonical.js`, `research/lighthouse/core/audits/audit.js`). |
| `python-seo-analyzer` | SEO/content analyzer / testing reference | medium | Useful selector maps, metadata/content extraction flow, content hashing, keyword/n-gram evidence, output assembly, and mocked tests; not useful for SERP collection or Product schema validation (`research/python-seo-analyzer/pyseoanalyzer/page.py`, `research/python-seo-analyzer/pyseoanalyzer/analyzer.py`, `research/python-seo-analyzer/tests/test_analyzer.py`). |
| `semantic-fashion-search` | semantic/hybrid product search reference / Medusa adapter reference | medium | Now useful as a future-only reference for product-query matching: CLIP text embeddings, FAISS vector retrieval, Google Cloud Retail keyword retrieval, reciprocal rank fusion, exact product-ID boosting, query translation, product-field indexing, and Medusa/storefront adapter boundaries. It is not useful for v0.1 core or visibility diagnostics because it depends on heavyweight external services and does not explain external SERP visibility (`research/semantic-fashion-search/hybrid-search-engine-api/Search.py`, `research/semantic-fashion-search/medusa-plugin-customsearch/src/services/meilisearch.js`, `research/semantic-fashion-search/medusa-storefront/src/lib/search-client.ts`). |
| `seonaut` | crawler reference / SEO analyzer reference / testing reference | high | Best overall source reference for interface-driven fetching, response evidence DTOs, parser fallback rules, page report fields, issue taxonomy, focused detectors, canonical/indexability checks, robots/sitemap handling, and fixture-like tests (`research/seonaut/internal/crawler/basic_client.go`, `research/seonaut/internal/crawler/crawler.go`, `research/seonaut/internal/services/parser.go`, `research/seonaut/internal/models/pagereport.go`, `research/seonaut/internal/issues/*`). |
| `site-audit-seo` | report generator / rendered-page SEO analyzer reference | medium | Useful for field presets, rendered extraction inventory, validation rules as data, report/export flow, and CLI ergonomics; browser crawler, Lighthouse runtime, and arbitrary eval are not suitable for v0.1 core (`research/site-audit-seo/src/scrap-site.js`, `research/site-audit-seo/src/validate.js`, `research/site-audit-seo/src/presets/scraperFields.js`, `research/site-audit-seo/src/program.js`). |

## Patterns worth adopting

### Crawling

- Use interface-driven fetchers so tests can inject fake HTTP clients/providers, following Seonaut's `HTTPRequester`/`Client` boundaries (`research/seonaut/internal/crawler/basic_client.go`, `research/seonaut/internal/crawler/crawler.go`).
- Capture transport evidence explicitly: requested URL, final URL, HTTP response, error, timeout, TTFB, robots-blocked state, and sitemap inclusion, inspired by Seonaut's `ResponseMessage` and LibreCrawl's fetch failure classification (`research/seonaut/internal/crawler/crawler.go`, `research/LibreCrawl/src/crawler.py`).
- Keep crawl helpers modular: fetcher, parser, link manager, sitemap/robots services, and detectors should be replaceable rather than merged into one crawler (`research/LibreCrawl/src/crawler.py`, `research/seonaut/internal/services/parser.go`).
- Add sitemap discovery later using common sitemap paths plus robots.txt `Sitemap:` lines and sitemap-index recursion (`research/LibreCrawl/src/core/sitemap_parser.py`, `research/seonaut/internal/crawler/sitemap_checker.go`).

### SERP/search result collection

- None of the inspected repositories provide a robust reusable SERP parser or search-provider abstraction for external engines.
- Our v0.1 `SearchProvider` should therefore be designed ourselves, starting with `StaticSearchProvider` fixture results and a `SearchResultSet` snapshot model.
- Search result snapshots should capture query, provider/engine, locale, device, requested timestamp, result URL, title, snippet, position, and provider warnings/limitations. This fills a gap not covered by the cloned code.

### Semantic product-query matching

- `semantic-fashion-search` is useful for a future expected-query layer, not for v0.1 core. Its search API embeds query text with CLIP, searches a FAISS vector index, retrieves keyword candidates through Google Cloud Retail Search, and combines both ranked lists with reciprocal rank fusion (`research/semantic-fashion-search/hybrid-search-engine-api/Search.py`).
- Keep lexical, semantic, and exact-identifier signals separate when designing any future `ProductQueryMatcher`; the inspected API boosts exact product-ID matches and uses keyword query expansion in addition to vector retrieval (`research/semantic-fashion-search/hybrid-search-engine-api/Search.py`).
- If we later add product-query scoring, return structured evidence rather than only a fused score: translated query, semantic rank/distance, keyword rank, exact ID/SKU match, fusion method, catalog fields used, and confidence/limitations. The inspected API returns product hit DTOs without score explanations (`research/semantic-fashion-search/hybrid-search-engine-api/data.py`).
- Product catalog fields worth exposing to future matchers include title, description, handle, thumbnail, gender, variant SKU/title/UPC/EAN/options, type, collection, and tags, based on the Firestore loader and Medusa transform helper (`research/semantic-fashion-search/hybrid-search-engine-api/Search.py`, `research/semantic-fashion-search/medusa-plugin-customsearch/src/utils/transform-product.js`).
- Medusa integration should remain adapter-level. The checked-in plugin forwards search to a custom API and leaves index lifecycle methods as no-ops, while the storefront wraps that through an InstantSearch-compatible client (`research/semantic-fashion-search/medusa-plugin-customsearch/src/services/meilisearch.js`, `research/semantic-fashion-search/medusa-storefront/src/lib/search-client.ts`).
- Do not introduce CLIP, FAISS, Google Cloud Retail, Firestore, Google Translate, Medusa, MeiliSearch, or vector infrastructure into v0.1 core.

### SEO/indexability analysis

- Implement noindex/indexability checks across HTML meta robots, bot-specific meta robots, X-Robots-Tag, `unavailable_after`, and robots.txt, based on Lighthouse and Seonaut (`research/lighthouse/core/audits/seo/is-crawlable.js`, `research/seonaut/internal/issues/page/indexability.go`).
- Implement canonical checks for invalid, relative, missing, multiple/conflicting, homepage-root, hreflang conflict, header-vs-HTML mismatch, and noncanonical-in-sitemap cases (`research/lighthouse/core/audits/seo/canonical.js`, `research/seonaut/internal/issues/page/canonical.go`, `research/seonaut/internal/issues/page/indexability.go`).
- Preserve source evidence for parser fallback decisions: HTML meta vs HTTP headers for robots/canonical/hreflang/language (`research/seonaut/internal/services/parser.go`).
- Treat generic SEO checks as explanatory evidence only; the main question remains whether the expected product URL appears for expected queries.

### Structured data/product schema

- LibreCrawl extracts JSON-LD and schema.org/microdata presence, and site-audit-seo collects schema item types, but neither deeply validates ecommerce Product/Offer schema (`research/LibreCrawl/src/core/seo_extractor.py`, `research/site-audit-seo/src/scrap-site.js`).
- Lighthouse's structured-data audit is manual and does not validate Product schema (`research/lighthouse/core/audits/seo/manual/structured-data.js`).
- We need to design our own Product schema validator that extracts JSON-LD and microdata, handles malformed JSON-LD, resolves `@graph`, finds Product/Offer/AggregateOffer/Review/Brand/Image fields, and reports missing/invalid fields with evidence.

### Scoring/ranking

- Avoid generic SEO scores. Lighthouse scoring is useful as a normalization/reporting reference but not as our core API (`research/lighthouse/core/audits/audit.js`, `research/lighthouse/core/scoring.js`).
- Prefer finding severity/confidence and query-level visibility status over a single score.
- If ranking-like data is needed, store external result position as observed evidence, not as a rank-tracking product.
- Declarative threshold rules can support simple fields, inspired by site-audit-seo, but findings must remain explicit and evidence-backed (`research/site-audit-seo/src/validate.js`).

### Reporting

- Adopt stable detector metadata and structured details from Lighthouse's audit model: ID/code, title, failure title, description, required evidence, details, warnings, and explanations (`research/lighthouse/core/audits/audit.js`).
- Use stable string finding codes inspired by Seonaut's issue taxonomy but make them self-describing for package consumers (`research/seonaut/internal/issues/errors/errors.go`).
- Provide report field groups/presets inspired by site-audit-seo, but keep the primary output as typed value objects/JSON rather than CSV-first reports (`research/site-audit-seo/src/presets/scraperFields.js`).
- Distinguish `visible`, `not_visible`, and `uncertain` at the query level, with findings explaining technical/content/structured-data evidence.

### Testing

- Start with deterministic fixture tests, not live Google/Bing/Chrome/network tests.
- Use fake providers/fetchers/parser fixtures inspired by Seonaut's interface boundaries and python-seo-analyzer's mocked orchestration tests (`research/seonaut/internal/crawler/basic_client_test.go`, `research/seonaut/internal/issues/page/canonical_test.go`, `research/python-seo-analyzer/tests/test_analyzer.py`).
- Add raw HTML/header fixtures for canonical, noindex, X-Robots-Tag, malformed JSON-LD, missing Product schema, and content mismatch.
- Add static SERP result fixtures for exact URL match, acceptable variant match, tracking-parameter normalization, and no match.

## Gaps not covered by the cloned repos

- **External search provider abstraction.** None of the repos implement our required `SearchProvider` contract for search/shopping/marketplace/AI discovery snapshots.
- **SERP parsing.** No cloned repo provides dependable SERP parsing for organic results, shopping results, marketplace results, or answer engines.
- **Product-specific schema validation.** Existing structured-data support is presence/manual only; Product/Offer validation must be designed.
- **Product-query expectation modeling.** `semantic-fashion-search` now provides useful future-only patterns for semantic/vector, keyword, exact-ID, translation, and fusion signals, but our actual framework-agnostic matcher contract, evidence model, confidence rules, fixtures, and merchant-facing recommendations still need to be designed.
- **Search result URL matching.** We need our own `UrlNormalizer` and `CanonicalUrlMatcher` for expected URLs, acceptable variants, canonical targets, tracking parameters, and equivalent product URLs.
- **AI answer-engine visibility.** No inspected source reliably tests LLM/answer-engine citations or product mentions; this must be a later adapter with clear uncertainty labels.
- **Merchant-facing recommendations.** The repos emit SEO issues or audit explanations; we need product-search-specific recommendations tied to visibility findings.
- **Framework-agnostic PHP API.** All references are Go, Python, or Node/browser apps; PHP package boundaries and value objects must be designed in this repo.

## Recommended architecture for our own project

### Core value objects

- `ProductSubject` — product identifier, canonical URL, acceptable URL variants, name, brand, SKU/GTIN/MPN, category, expected terms, and optional locale.
- `SearchQuery` — query text, locale, market, device, provider context, and expected visibility intent.
- `SearchResult` — URL, title, snippet, position, result type, provider metadata, and raw/evidence payload.
- `SearchResultSet` — query, provider, timestamp, locale/device, results, warnings, and limitations.
- `PageSnapshot` — requested URL, final URL, status, headers, body/media type, redirects, TTFB, timeout, fetch failure type, and fetch warnings.
- `ParsedPage` — title, description, canonical, robots directives, hreflang, headings, body text summary, links, JSON-LD blocks, microdata types, Product schema candidates, and parser warnings.
- `Finding` — stable code, severity, confidence, message, structured evidence, and recommendation.
- `QueryVisibility` — query, observed result match, status (`visible`, `not_visible`, `uncertain`), position/result evidence, and findings.
- `VisibilityReport` — product subject, query visibilities, page evidence, summary findings, provider limitations, and generated timestamp.

### Core contracts

- `SearchProvider` — returns `SearchResultSet` for a `SearchQuery` without implying scraping in core.
- `PageFetcher` — returns `PageSnapshot` and can be replaced with fixture, PSR-18, or adapter implementations.
- `PageParser` — turns a `PageSnapshot` into `ParsedPage` with source-aware evidence.
- `UrlMatcher` — decides exact/normalized/canonical/acceptable-variant matches between product URLs and search results.
- `Detector` — receives a detection context and returns `Finding[]`.
- `ReportSerializer` — optional later layer for JSON/report views, not required for v0.1.

### Initial pipeline

1. Accept a `ProductSubject` and one or more `SearchQuery` objects.
2. Ask `SearchProvider` for deterministic search evidence.
3. Use `UrlMatcher` to identify exact or acceptable product-result matches.
4. Fetch the known product page through `PageFetcher`.
5. Parse page evidence through `PageParser`.
6. Run detectors over search evidence, match evidence, page snapshot, and parsed page.
7. Return a `VisibilityReport` with per-query status and evidence-backed findings.

### Module boundaries

- `Core/Search` — search provider contracts, static provider, result models.
- `Core/Url` — URL normalization, canonical matching, acceptable variants.
- `Core/Page` — fetcher/parser contracts and page evidence models.
- `Core/Detector` — detector interface and initial search/page detectors.
- `Core/Report` — findings, query visibility, visibility report.
- `Adapters/*` — later optional PSR HTTP, Laravel, marketplace, rendered browser, and AI answer-engine integrations.

## Future roadmap seeds

- Create Composer/PHPUnit package skeleton for PHP 8.2+ with PSR-4 autoloading.
- Implement `ProductSubject`, `SearchQuery`, `SearchResult`, `SearchResultSet`, `Finding`, `QueryVisibility`, and `VisibilityReport` value objects.
- Implement `SearchProvider` and `StaticSearchProvider` with fixture-based tests.
- Implement `UrlNormalizer` that normalizes scheme/host casing, strips fragments, handles trailing slashes, sorts query params, and removes known tracking params.
- Implement `CanonicalUrlMatcher` for exact, normalized, and acceptable-variant URL matching.
- Implement basic `VisibilityDetector` orchestration for found/not-found URL matching before any page fetching.
- Add fixtures for exact match, acceptable variant match, no match, tracking parameters, and provider limitation/uncertain status.
- Implement `PageFetcher` contract and a fixture fetcher; later add a PSR-18 adapter.
- Implement `PageParser` for title, description, canonical, robots, hreflang, headings, body text, JSON-LD extraction, and parser warnings.
- Implement `IndexabilityDetector` for meta robots, bot-specific robots, X-Robots-Tag, `unavailable_after`, and robots.txt evidence.
- Implement canonical detectors for relative canonical, multiple canonical tags, canonical mismatch, canonical to homepage, and noncanonical product URL.
- Implement Product schema extraction and validation for JSON-LD `Product`, `Offer`, `AggregateOffer`, brand, images, SKU/GTIN/MPN, price, currency, and availability.
- Implement product content mismatch detector comparing query/product terms against title, H1, body, and structured-data fields.
- Add report JSON serialization with compact and full-evidence views.
- Add a later CLI for fixture-driven visibility checks.
- Add optional future adapters for rendered-page fetching, Laravel integration, marketplace search providers, shopping search providers, semantic product-query matching, and answer-engine visibility checks.
