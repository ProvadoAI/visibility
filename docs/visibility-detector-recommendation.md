# Technical recommendation for `visibility-detector`

## Scope and positioning

`visibility-detector` should be a framework-agnostic PHP Composer package focused on one question:

> Given a product and expected search queries, determine whether the product appears in external search results, and explain probable reasons when it does not.

It should not become a generic SEO crawler, rank tracker, Lighthouse wrapper, or Laravel-only application. The package should model a product, collect search evidence, collect product-page evidence, compare the two, and return an explainable visibility decision.

## Repository review: engineering lessons

This document remains the high-level architecture recommendation. For concrete implementation patterns inspected from the checked-in reference repositories under `research/`, see [Source-code review patterns for `visibility-detector`](source-code-review-patterns.md).

### `PhialsBasement/LibreCrawl`

#### What it does well

- Treats crawling as a pipeline rather than a single monolithic script.
- Emphasizes content collection and normalization, which is useful when downstream logic needs comparable page text, links, and metadata.
- Demonstrates that crawler output should be structured enough to feed later analysis.

#### Conceptually reusable parts

- A separation between fetching, parsing, normalization, and analysis.
- URL and content deduplication concepts.
- A crawl result object that preserves both raw evidence and normalized fields.

#### Parts not to copy

- Any goal of broad site crawling as the primary product. Product visibility detection only needs targeted page analysis plus external search-result checks.
- Heavy crawler scheduling, frontier management, or large-scale content ingestion in v0.1.
- A crawler-first data model that lacks product and query context.

### `StJudeWasHere/seonaut`

#### What it does well

- Models SEO checks as issues/findings rather than only a numeric score.
- Uses a product-like workflow: crawl, audit, persist, present actionable problems.
- Shows value in grouping findings by severity and affected URL.

#### Conceptually reusable parts

- Finding/issue taxonomy with severity, confidence, and remediation text.
- A job-oriented audit lifecycle that a Laravel adapter can later expose through queues and dashboards.
- A clear distinction between collected evidence and interpreted findings.

#### Parts not to copy

- A full SEO-audit application architecture in the core package.
- Database schema, web UI, account/project concepts, authentication, or persistence as core concerns.
- Generic page/site SEO checks that do not help answer product search visibility.

### `viasite/site-audit-seo`

#### What it does well

- Packages SEO rules as reusable checks.
- Produces concrete diagnostics for common technical SEO failures.
- Reinforces that audits are most useful when each rule has a small, testable scope.

#### Conceptually reusable parts

- Small analyzer classes that inspect a page snapshot and emit findings.
- Rule metadata: code, name, severity, explanation, and recommendation.
- Deterministic tests around HTML fixtures.

#### Parts not to copy

- A generic SEO checklist as the main library output.
- Coupling visibility detection to a fixed scoring formula.
- Treating every SEO issue as equally relevant to external search appearance.

### `sethblack/python-seo-analyzer`

#### What it does well

- Keeps analysis lightweight and approachable.
- Extracts common on-page signals such as title, headings, links, images, and metadata.
- Demonstrates the value of simple heuristics before introducing heavyweight tooling.

#### Conceptually reusable parts

- HTML parsing for title, canonical URL, robots meta, headings, links, image alt text, and content terms.
- Keyword/query comparison against page content.
- CLI-friendly operation and fixture-driven tests.

#### Parts not to copy

- Keyword-density-style scoring as a proxy for visibility.
- Generic site crawling as the center of the package.
- Python-specific implementation patterns or output formats.

### `GoogleChrome/lighthouse`

#### What it does well

- Strong architecture separation between collection, audits, and reporting.
- Audits are modular, named, documented, and produce structured results.
- Maintains a clear evidence trail from observed browser/page data to final findings.
- Has an extensibility model that makes new audits additive.

#### Conceptually reusable parts

- The gatherer/audit/report mental model:
  - collectors gather evidence,
  - detectors interpret evidence,
  - reports serialize findings.
- Audit result shape with IDs, descriptions, scores/statuses, and details.
- Clear separation between runtime environment and analysis logic.

#### Parts not to copy

- Node/browser-runtime dependency in the PHP core package.
- Performance, accessibility, PWA, and browser trace audits that do not directly answer product search visibility.
- A Lighthouse subprocess dependency as the default path.

## Best model to learn from

The best architectural model is Lighthouse, but only at the pattern level. Its gatherer/audit/report separation maps well to visibility detection: first gather search-result and product-page evidence, then run targeted detectors, then return an explainable report. The package should not depend on Lighthouse in v0.1 because browser automation and Node subprocesses would make the PHP core heavier, slower, and harder to install.

For v0.1 implementation simplicity, the lightweight HTML-analysis style of `python-seo-analyzer` is the best implementation reference. Combine Lighthouse's architecture with small deterministic analyzers inspired by lightweight SEO tools.

## Recommended package architecture

### Design principles

1. Framework agnostic: no Laravel container, Eloquent models, facades, queues, cache, or config helpers in the core.
2. Evidence first: every decision should point to observed search results, HTTP metadata, robots directives, canonical tags, structured data, and page content.
3. Driver based: search providers, HTTP clients, parsers, and optional enrichment services should be replaceable.
4. Targeted checks: analyze a known product URL and expected queries, not an arbitrary website.
5. Explainable output: return probable causes with severity and confidence, not a black-box SEO score.
6. Deterministic tests: core rules should be testable with static HTML and fake search-provider responses.

### High-level flow

1. Accept a `ProductSubject` and one or more `SearchQuery` objects.
2. Query one or more `SearchProvider` implementations.
3. Match returned SERP entries to the product URL and acceptable variants.
4. Fetch and parse the product page.
5. Collect technical and content evidence from the page.
6. Run detectors against the combined evidence.
7. Return a `VisibilityReport` containing:
   - visibility status per query,
   - observed ranking or absence,
   - matched result URL/title/snippet when found,
   - probable causes when absent or weak,
   - confidence and remediation hints.

## Suggested folder structure

```text
visibility-detector/
├── composer.json
├── src/
│   ├── VisibilityDetector.php
│   ├── Contracts/
│   │   ├── SearchProvider.php
│   │   ├── PageFetcher.php
│   │   ├── PageParser.php
│   │   ├── Detector.php
│   │   ├── UrlMatcher.php
│   │   └── Clock.php
│   ├── Domain/
│   │   ├── ProductSubject.php
│   │   ├── SearchQuery.php
│   │   ├── SearchResult.php
│   │   ├── SearchResultSet.php
│   │   ├── PageSnapshot.php
│   │   ├── VisibilityReport.php
│   │   ├── QueryVisibility.php
│   │   ├── Finding.php
│   │   └── Severity.php
│   ├── Search/
│   │   ├── NullSearchProvider.php
│   │   ├── StaticSearchProvider.php
│   │   └── CompositeSearchProvider.php
│   ├── Http/
│   │   └── Psr18PageFetcher.php
│   ├── Parser/
│   │   └── DomCrawlerPageParser.php
│   ├── Matching/
│   │   └── CanonicalUrlMatcher.php
│   ├── Detectors/
│   │   ├── NotFoundInResultsDetector.php
│   │   ├── RobotsBlockedDetector.php
│   │   ├── NoindexDetector.php
│   │   ├── CanonicalMismatchDetector.php
│   │   ├── ProductContentMismatchDetector.php
│   │   ├── StructuredDataDetector.php
│   │   └── HttpStatusDetector.php
│   └── Reporting/
│       └── ArrayReportFormatter.php
├── tests/
│   ├── Unit/
│   ├── Fixtures/
│   │   ├── serp/
│   │   └── html/
│   └── Integration/
└── docs/
    └── visibility-detector-recommendation.md
```

## Core interfaces and classes

### `ProductSubject`

Represents the product being checked.

Recommended fields:

- `id`: caller-defined product identifier.
- `url`: expected canonical product URL.
- `name`: product title/name.
- `brand`: optional brand/manufacturer.
- `sku`: optional SKU.
- `gtin`: optional GTIN/UPC/EAN/ISBN.
- `description`: optional product text.
- `attributes`: optional arbitrary key/value product attributes.
- `acceptableUrls`: optional URL variants that should count as the same product.

### `SearchQuery`

Represents an expected external query.

Recommended fields:

- `query`: raw query string.
- `locale`: optional locale, such as `en_US`.
- `country`: optional country, such as `US`.
- `device`: optional `desktop` or `mobile`.
- `expectedDomain`: optional expected merchant domain.
- `maxResults`: default to 10 or 20.

### `SearchProvider`

```php
interface SearchProvider
{
    public function search(SearchQuery $query): SearchResultSet;
}
```

Implementations should include:

- `StaticSearchProvider` for tests and fixture-driven development.
- `CompositeSearchProvider` for querying multiple engines or APIs later.
- Optional adapters for official APIs, such as Google Programmable Search or Bing Web Search, in separate packages or optional namespaces.

### `SearchResult`

Recommended fields:

- `position`.
- `url`.
- `title`.
- `snippet`.
- `source` or `engine`.
- `raw` provider payload.

### `PageFetcher`

```php
interface PageFetcher
{
    public function fetch(string $url): PageSnapshot;
}
```

Use PSR-18 and PSR-17 abstractions so callers choose Guzzle, Symfony HTTP Client, Laravel HTTP through an adapter, or any compliant client.

### `PageParser`

```php
interface PageParser
{
    public function parse(PageSnapshot $snapshot): ParsedPage;
}
```

Parsed signals should include:

- HTTP status.
- final URL after redirects.
- title and meta description.
- robots meta directives.
- canonical URL.
- headings.
- visible text excerpt or normalized terms.
- structured data product signals.
- links and robots-relevant metadata where practical.

### `UrlMatcher`

```php
interface UrlMatcher
{
    public function matches(ProductSubject $product, SearchResult $result, ?ParsedPage $page = null): MatchResult;
}
```

Matching should normalize scheme, host, path, trailing slash, tracking parameters, and canonical URLs. It should support exact URL matches and acceptable variants.

### `Detector`

```php
interface Detector
{
    /** @return list<Finding> */
    public function detect(DetectionContext $context): array;
}
```

`DetectionContext` should contain the product, query, result set, match result, page snapshot, parsed page, and configuration.

### `Finding`

Recommended fields:

- `code`: stable machine-readable code such as `search.not_found`, `page.noindex`, or `canonical.mismatch`.
- `severity`: `info`, `warning`, `critical`.
- `confidence`: numeric 0.0-1.0 or enum `low`, `medium`, `high`.
- `message`: short human-readable explanation.
- `evidence`: structured evidence array.
- `recommendation`: suggested next action.

### `VisibilityReport`

Should summarize:

- Product identity.
- Checked queries.
- Per-query visibility status:
  - `visible`.
  - `not_visible`.
  - `uncertain`.
- Best matched result and position.
- Findings grouped by query and globally.
- Timestamp and provider metadata.

## Minimal v0.1 implementation plan

### v0.1 goals

Build the smallest reliable core that can answer visibility for controlled inputs and explain obvious causes.

Recommended v0.1 features:

1. Composer package with PHP 8.2+.
2. Framework-agnostic domain objects.
3. `SearchProvider` contract.
4. `StaticSearchProvider` for fixtures/tests.
5. Optional PSR-18 page fetcher.
6. DOM-based page parser.
7. URL matcher with canonical and acceptable URL support.
8. Detection orchestration service.
9. Core detectors:
   - not found in search results,
   - found in search results,
   - HTTP status not 200,
   - noindex robots meta,
   - robots-blocked indicator if robots data is supplied,
   - canonical mismatch,
   - product name/content mismatch,
   - missing or weak Product structured data.
10. Array/JSON report formatter.
11. PHPUnit tests with static SERP and HTML fixtures.

### v0.1 non-goals

Do not include these in v0.1:

- Browser automation.
- Lighthouse execution.
- SERP scraping.
- Rank tracking history.
- Laravel service provider.
- Database persistence.
- Queue jobs.
- Dashboards.
- Sitemap crawling beyond optionally fetching a known product URL.
- Full robots.txt crawl-policy engine unless a small optional parser is easy to add.
- Search Console integration.
- AI-generated explanations.

## Browser automation, APIs, Lighthouse, and external services

### Browser automation

Do not make browser automation a core dependency. Most v0.1 signals can be collected from HTTP responses and static HTML. Browser automation should be optional later for JavaScript-heavy product pages where SSR HTML lacks product content or structured data.

Recommended later adapter:

- `visibility-detector-browser` using Playwright, Symfony Panther, or a remote rendering service.
- Expose it as a `PageFetcher` or `RenderedPageFetcher`, not as core logic.

### Search APIs

Use APIs when possible, but keep them behind `SearchProvider`.

Recommended approach:

- Core ships no paid/API provider by default.
- v0.1 ships `StaticSearchProvider` only.
- Later add official API adapters as optional packages:
  - `visibility-detector-google-custom-search`.
  - `visibility-detector-bing-search`.
  - `visibility-detector-serpapi` if users accept third-party SERP services.

Avoid first-party SERP scraping as a default because it is brittle, may violate terms, and creates operational risk.

### Lighthouse

Do not depend on Lighthouse in core. Lighthouse is useful as an optional diagnostic enrichment tool, especially for renderability, indexability-adjacent page quality, performance, and crawlability clues, but it is not designed to answer whether a specific product appears for a specific external query.

A later optional adapter could map Lighthouse SEO audit output into `Finding` objects, but the visibility decision should not require Lighthouse.

### External services

External services should be optional providers, never hard dependencies. The core package should run with fake/static search results and fixture HTML so it remains easy to test and use in private ecommerce systems.

## Keeping the core independent from Laravel

The core should:

- Avoid Laravel dependencies entirely.
- Use constructor injection, not facades.
- Use PSR interfaces where appropriate:
  - PSR-18 HTTP client,
  - PSR-17 request factories,
  - PSR-3 logger optionally,
  - PSR-16 cache optionally only through an adapter.
- Return immutable value objects or simple DTOs.
- Throw package-specific exceptions rather than Laravel exceptions.
- Avoid config files, migrations, jobs, Eloquent models, and service providers.

The Laravel integration should live in a separate package, for example `visibility-detector-laravel`.

## Future Laravel adapter integration

A Laravel adapter should provide framework conveniences without changing core behavior.

Recommended Laravel adapter responsibilities:

- Service provider that binds:
  - `SearchProvider`,
  - `PageFetcher`,
  - `VisibilityDetector`,
  - configured detectors.
- Config file mapping providers, locales, default max results, timeout, cache TTL, and detector options.
- Queue job such as `DetectProductVisibilityJob`.
- Optional Eloquent models/tables for:
  - visibility checks,
  - per-query results,
  - findings,
  - provider payload snapshots.
- Events:
  - `VisibilityCheckStarted`,
  - `VisibilityCheckCompleted`,
  - `ProductNotVisibleDetected`.
- Notifications or webhooks for critical findings.
- Adapter methods to convert ecommerce product models into `ProductSubject`.

The Laravel adapter should not duplicate detector logic. It should orchestrate persistence, scheduling, configuration, and notifications around the core package.

## Recommended detector taxonomy

### Search evidence detectors

- `SearchResultPresenceDetector`: product appears or does not appear in top N results.
- `WrongUrlDetector`: same product appears through a noncanonical or unexpected URL.
- `CompetitorDominanceDetector`: later feature; identifies repeated competitor domains above expected merchant result.
- `ResultSnippetMismatchDetector`: later feature; detects stale, incorrect, or irrelevant result snippets.

### Indexability detectors

- `HttpStatusDetector`: product URL returns 404, 410, 500, redirect loop, or other problematic status.
- `NoindexDetector`: page has `noindex` robots meta.
- `CanonicalMismatchDetector`: canonical points away from expected product URL.
- `RobotsTxtDetector`: product URL appears disallowed when robots data is supplied or fetched.

### Product relevance detectors

- `ProductContentMismatchDetector`: product name/brand/SKU terms are absent or weak on the page.
- `StructuredDataProductDetector`: Product schema missing, malformed, or mismatched.
- `UnavailableProductDetector`: later feature; product is out of stock, discontinued, hidden, or blocked by business rules.

### Ecommerce-specific detectors for later

- Variant canonicalization issues.
- Faceted URL/index bloat causing wrong product URL ranking.
- Marketplace result ambiguity.
- Product feed mismatch with Merchant Center or marketplace feeds.
- Internal search-only product pages with no canonical landing page.

## Risks and tradeoffs

### API dependency risk

Search APIs cost money, have quotas, and may not reproduce consumer SERPs exactly. Keep provider interfaces narrow and expose provider metadata in reports.

### SERP scraping risk

Direct scraping creates legal, operational, anti-bot, and correctness risks. Do not ship scraper-based providers in core.

### False negatives

A product may be visible for personalized, localized, or device-specific results even if an API does not show it. Reports should include `uncertain` when provider quality is limited.

### False explanations

Search non-visibility is probabilistic. The library should report probable causes with confidence, not definitive claims unless evidence is direct, such as `noindex` or `404`.

### Over-scoping risk

Generic SEO crawlers quickly become large applications. Keep v0.1 centered on product, query, SERP result, page evidence, and finding.

### JavaScript rendering risk

Some ecommerce pages render critical product data client-side. Avoid browser automation in core, but design `PageFetcher` so rendered snapshots can be supplied later.

### Laravel coupling risk

Adding Laravel too early will make the library less reusable. Keep Laravel as an adapter package.

## Recommended roadmap

### v0.1: core visibility engine

- Value objects.
- Contracts.
- Static search provider.
- PSR-18 fetcher.
- DOM parser.
- URL matcher.
- Initial detectors.
- JSON/array report.
- PHPUnit fixture tests.

### v0.2: real provider adapters

- Google Programmable Search provider, if acceptable for the product's search coverage needs.
- Bing Web Search provider, if available to the deployment environment.
- Third-party SERP provider adapter if the team needs real SERP fidelity.
- Provider result normalization tests.

### v0.3: Laravel adapter

- Service provider.
- Config.
- Queue jobs.
- Persistence.
- Events.
- Product model mapper.

### v0.4+: advanced diagnostics

- Optional rendered-page fetcher.
- Optional Lighthouse finding mapper.
- Historical rank/visibility trend storage in Laravel adapter.
- Search Console URL inspection integration.
- Merchant/product feed comparison.
- AI-assisted explanation generation using the structured `Finding` evidence, not raw opaque prompts.

## Final recommendation

Build `visibility-detector` as a small evidence-driven PHP core that borrows Lighthouse's modular gather/analyze/report architecture and lightweight SEO analyzers' deterministic HTML parsing. Do not build a generic crawler. Do not depend on browser automation, Lighthouse, Laravel, or paid search APIs in the core. Make search evidence pluggable, make page analysis deterministic, and make findings explicit, confidence-scored, and ecommerce-specific.
