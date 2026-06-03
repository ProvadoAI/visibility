# AGENTS.md

## Project purpose

This repository is for designing and building `visibility-detector`, a framework-agnostic PHP Composer package.

The package has one core question:

> Given a product and expected search queries, determine whether the product appears in external search results, and explain probable reasons when it does not.

The goal is product search visibility detection for ecommerce, not generic SEO.

## Current project stage

The project is still in research/design phase.

Before implementing production code, read:

- `docs/visibility-detector-recommendation.md`
- `docs/source-code-review-patterns.md`

Use the checked-in repositories under `research/` only as references for implementation patterns. Do not copy external source code into `src/`.

## Product boundaries

Do build toward:

- a PHP Composer package
- framework-agnostic core classes
- deterministic evidence collection
- explicit visibility reports
- findings with severity, confidence, evidence, and recommendations
- later Laravel integration through a separate adapter

Do not build:

- a generic SEO crawler
- a rank tracker
- a Lighthouse wrapper
- a Laravel-only application
- a SaaS dashboard
- a browser automation system in core
- a SERP scraper in core
- a vector database or embeddings system in v0.1
- AI-generated explanations in v0.1

## Core architecture

The core should follow this evidence pipeline:

1. Accept a `ProductSubject` and one or more `SearchQuery` objects.
2. Query external search evidence through a `SearchProvider`.
3. Match returned search results to the expected product URL or acceptable variants.
4. Fetch and parse the known product page.
5. Extract indexability, canonical, metadata, content, and structured-data evidence.
6. Run small detectors over the evidence.
7. Return a `VisibilityReport`.

Important contracts/classes to preserve:

- `SearchProvider`
- `PageFetcher`
- `PageParser`
- `UrlMatcher`
- `Detector`
- `ProductSubject`
- `SearchQuery`
- `SearchResult`
- `SearchResultSet`
- `PageSnapshot`
- `ParsedPage`
- `Finding`
- `QueryVisibility`
- `VisibilityReport`

## v0.1 implementation priority

Implement only the smallest reliable core first:

1. Composer package setup for PHP 8.2+
2. PSR-4 autoloading
3. PHPUnit setup
4. Domain value objects
5. `SearchProvider` contract
6. `StaticSearchProvider` for fixture SERP results
7. `UrlNormalizer`
8. `CanonicalUrlMatcher`
9. Basic `VisibilityDetector` orchestration
10. Fixture-driven tests for found/not-found URL matching

Only after that, add:

- `PageFetcher`
- `PageParser`
- noindex/X-Robots-Tag detection
- canonical mismatch detection
- structured data detection
- product content mismatch detection

## Reference project usage

Use `research/` repositories only as pattern references.

Important patterns already identified:

- `research/seonaut/internal/crawler/basic_client.go`
  - interface-driven HTTP fetching and response evidence
- `research/seonaut/internal/crawler/crawler.go`
  - evidence-rich response messages
- `research/LibreCrawl/src/crawler.py`
  - fetch failure classification
- `research/seonaut/internal/crawler/robots_checker.go`
  - per-host robots lookup/cache
- `research/lighthouse/core/audits/seo/is-crawlable.js`
  - noindex and X-Robots-Tag handling
- `research/lighthouse/core/audits/seo/canonical.js`
  - canonical URL edge cases
- `research/seonaut/internal/issues/page/canonical.go`
  - canonical header vs HTML mismatch checks
- `research/LibreCrawl/src/core/seo_extractor.py`
  - metadata and structured-data extraction
- `research/python-seo-analyzer/pyseoanalyzer/page.py`
  - XPath/selector-map style extraction
- `research/site-audit-seo/src/scrap-site.js`
  - rendered-page extraction as later optional inspiration only
- `research/semantic-fashion-search`
  - future product-query semantic matching inspiration only

When using these projects, cite exact file paths in docs or PR descriptions.

## Semantic product-query matching

Semantic matching is not v0.1 core.

It is useful later for the Expected Visibility layer:

> Which queries should plausibly map to this product?

Reserve a seam for this later, such as:

```php
interface ProductQueryMatcher
{
    public function score(ProductSubject $product, SearchQuery $query): ProductQueryMatch;
}
```

or:

```php
interface QueryExpectationProvider
{
    /** @return SearchQuery[] */
    public function expectedQueriesFor(ProductSubject $product): array;
}
```

Do not add embeddings, vector search, model providers, or semantic infrastructure to v0.1.

## Findings and reports

Avoid generic SEO scores.

Every finding should be explicit and evidence-backed.

A `Finding` should include:

- stable code, e.g. `search.not_found`, `page.noindex`, `canonical.mismatch`
- severity
- confidence
- human-readable message
- structured evidence
- recommendation

Reports should distinguish:

- `visible`
- `not_visible`
- `uncertain`

Use `uncertain` when provider limitations, personalization, localization, API limitations, or incomplete evidence prevent a confident answer.

## Testing rules

Prefer deterministic tests.

Use fixtures for:

- static SERP result sets
- exact URL match
- acceptable URL match
- no matching result
- tracking-parameter normalization
- canonical mismatch
- noindex
- X-Robots-Tag
- malformed JSON-LD
- missing Product schema
- product-content mismatch

Do not require real Google, Bing, Lighthouse, Chrome, network access, Laravel, or external APIs for core unit tests.

## Dependency rules

Core package:

- may use PHP 8.2+
- may use PSR interfaces where appropriate
- may use PHPUnit for tests
- may use a lightweight DOM parser when page parsing is introduced

Core package must not depend on:

- Laravel
- Eloquent
- queues
- database migrations
- browser automation
- Lighthouse runtime
- Node
- paid SERP APIs
- AI/LLM providers
- vector databases

## Laravel integration

Laravel integration belongs in a separate adapter/package later.

The Laravel adapter may provide:

- service provider
- config
- queue jobs
- persistence
- events
- notifications
- product model mappers

The Laravel adapter must not duplicate detector logic.

## Pull request expectations

For every PR:

- keep scope small
- update docs when direction changes
- add tests when adding code
- avoid unrelated changes
- do not copy external repository code
- do not introduce Laravel coupling into core
- explain which documented design decision the PR follows

When uncertain, prefer a smaller PR with fewer moving parts.
