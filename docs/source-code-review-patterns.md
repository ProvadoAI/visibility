# Source-code review patterns for `visibility-detector`

## Purpose

This document extracts implementation patterns from the reference repositories under `research/` that are useful for a future PHP Composer package named `visibility-detector`.

The target product remains narrow:

> Given a product and expected search queries, determine whether the product appears in external search results, and explain probable reasons when it does not.

This is **not** a request to implement the library yet, copy code into `src/`, create Laravel code, build a dashboard, or broaden the product into a generic SEO crawler.

## Review lens

Useful patterns are evaluated by whether they help `visibility-detector` build a deterministic evidence pipeline:

1. collect external search-result evidence,
2. fetch and parse the known product page,
3. extract product/indexability signals,
4. match SERP URLs to the expected product URL,
5. emit explainable findings with confidence.

## Pattern inventory

### 1. Targeted HTTP client and fetch-result timing

- **Source repo:** `research/seonaut`
- **File path:** `research/seonaut/internal/crawler/basic_client.go`
- **Class/function/module:** `BasicClient`, `ClientOptions`, `HTTPRequester`, `ClientResponse`, `BasicClient.Get`, `BasicClient.Head`, `BasicClient.do`
- **What the code does:** Wraps HTTP GET/HEAD requests behind a small requester interface, applies a configured user agent, optionally applies basic auth for selected domains, and records time-to-first-byte through `httptrace`.
- **How it maps to `visibility-detector`:** The PHP package should expose a `PageFetcher` abstraction that returns a `PageSnapshot` with status, final URL, headers, body, timing, and transport error metadata. TTFB is not central to visibility, but HTTP status, response headers, redirects, and timeouts are direct evidence for probable non-visibility causes.
- **Recommendation:** **Adapt conceptually.** Implement with PSR-18/PSR-17 rather than Go's `net/http`.
- **Risk/complexity/licensing notes:** Low complexity. Do not copy Go code. Keep timing optional so the core does not become a performance audit tool.

### 2. Minimal page-fetcher interface instead of hard-coding a client

- **Source repo:** `research/seonaut`
- **File path:** `research/seonaut/internal/crawler/crawler.go`
- **Class/function/module:** `Client` interface, `ClientResponse`, `ResponseMessage`
- **What the code does:** Defines crawler behavior against a `Client` interface with `Get`, `Head`, and `GetUAName`; response messages preserve URL, response, error, TTFB, robots-blocked state, sitemap state, timeout state, and caller data.
- **How it maps to `visibility-detector`:** Core orchestration should depend on interfaces: `SearchProvider`, `PageFetcher`, `PageParser`, `UrlMatcher`, and `Detector`. A `DetectionContext` should preserve raw evidence and not just final booleans.
- **Recommendation:** **Adapt conceptually.** Use interface-driven design and evidence-rich DTOs.
- **Risk/complexity/licensing notes:** Low risk. Avoid importing crawler queue semantics into v0.1.

### 3. Fetch-error classification for explainable transport failures

- **Source repo:** `research/LibreCrawl`
- **File path:** `research/LibreCrawl/src/crawler.py`
- **Class/function/module:** `classify_fetch_error`
- **What the code does:** Normalizes low-level exceptions and message fragments into coarse error categories such as DNS failure, timeout, connection refused, SSL error, and generic connection error.
- **How it maps to `visibility-detector`:** If a product URL cannot be fetched, the report should explain whether the page appears unreachable because of DNS, timeout, TLS, connection, or HTTP status evidence. This is directly useful when the product is missing from SERPs.
- **Recommendation:** **Adapt conceptually.** Implement as a PHP `FetchFailureClassifier` or part of `PageFetcher` error mapping.
- **Risk/complexity/licensing notes:** Medium risk if overfit to provider-specific error strings. Prefer exception classes from PSR-18 clients where possible, and treat string matching as fallback evidence with lower confidence.

### 4. Robots.txt cache and per-host lookup

- **Source repo:** `research/seonaut`
- **File path:** `research/seonaut/internal/crawler/robots_checker.go`
- **Class/function/module:** `RobotsChecker`, `IsBlocked`, `Exists`, `GetSitemaps`, `getRobotsMap`
- **What the code does:** Fetches `/robots.txt` once per host, parses it, caches the parsed result, checks whether a URL is blocked for the configured user agent, and exposes sitemap references.
- **How it maps to `visibility-detector`:** Product visibility explanations need indexability evidence. A `RobotsPolicyProvider` can fetch/cache robots data and contribute findings such as `robots.blocked` or `robots.unavailable`. For v0.1, robots can be optional or injected as evidence.
- **Recommendation:** **Adapt conceptually.** Start with optional robots evidence; add fetching/caching when the core has stable `PageFetcher` and cache interfaces.
- **Risk/complexity/licensing notes:** Robots parsing has edge cases. Use an existing PHP robots parser if it has compatible licensing; otherwise keep v0.1 focused on page-level `noindex` and headers.

### 5. User-agent-aware noindex and robots handling

- **Source repo:** `research/lighthouse`
- **File path:** `research/lighthouse/core/audits/seo/is-crawlable.js`
- **Class/function/module:** `IsCrawlable`, `hasBlockingDirective`, `getUserAgentFromHeaderDirectives`, `determineIfCrawlableForUserAgent`
- **What the code does:** Checks robots meta tags, `X-Robots-Tag` headers, `unavailable_after`, `noindex`, `none`, and robots.txt behavior across several search bot user agents.
- **How it maps to `visibility-detector`:** A product may be invisible because Googlebot is blocked while another bot is not, or because a generic robots directive blocks indexing. Findings should include the directive source, bot scope, and confidence.
- **Recommendation:** **Use as inspiration.** For v0.1, implement generic `noindex` and `X-Robots-Tag` parsing; later expand to per-bot diagnostics.
- **Risk/complexity/licensing notes:** Lighthouse is Apache-2.0, but do not copy code. Bot-specific behavior is complex; represent partial evidence as `uncertain` rather than definitive.

### 6. Page-level indexability reporters

- **Source repo:** `research/seonaut`
- **File path:** `research/seonaut/internal/issues/page/indexability.go`
- **Class/function/module:** `NewNoIndexableReporter`, `NewBlockedByRobotstxtReporter`, `NewNoIndexInSitemapReporter`, `NewSitemapAndBlockedReporter`, `NewNonCanonicalInSitemapReporter`, `NewNosnippetReporter`
- **What the code does:** Models page issue checks as small reporter callbacks that read `PageReport` evidence and return a boolean for specific indexability conditions.
- **How it maps to `visibility-detector`:** Create small detector classes such as `NoindexDetector`, `RobotsBlockedDetector`, `NonCanonicalDetector`, and `SnippetDirectiveDetector`. Each should emit one or more `Finding` objects with evidence rather than mutating global report state.
- **Recommendation:** **Adapt conceptually.** Use one detector per visibility cause.
- **Risk/complexity/licensing notes:** Low risk. Avoid generic sitemap checks unless product URL sitemap evidence is available.

### 7. Canonical URL extraction and validation

- **Source repo:** `research/lighthouse`
- **File path:** `research/lighthouse/core/audits/seo/canonical.js`
- **Class/function/module:** `Canonical`, `collectCanonicalURLs`, `findInvalidCanonicalURLReason`, `findCommonCanonicalURLMistakes`, `audit`
- **What the code does:** Collects canonical links from link artifacts, ignores body canonical links, flags invalid URLs, relative URLs, multiple conflicting canonicals, hreflang conflicts, and root-page canonical mistakes.
- **How it maps to `visibility-detector`:** Product search visibility depends heavily on canonical URL matching. The core should extract canonical URLs, compare them against the expected product URL, and classify mismatches as probable causes when SERPs show no result or show a different URL.
- **Recommendation:** **Use as inspiration.** Implement simpler v0.1 rules: missing canonical as info, multiple canonicals as warning, invalid/relative canonical as warning, canonical-to-different-product as critical when product absent.
- **Risk/complexity/licensing notes:** Canonical semantics are nuanced. Avoid treating every off-URL canonical as fatal because ecommerce variants can legitimately canonicalize to parent products.

### 8. Canonical header-vs-HTML mismatch

- **Source repo:** `research/seonaut`
- **File path:** `research/seonaut/internal/issues/page/canonical.go`
- **Class/function/module:** `NewCanonicalMultipleTagsReporter`, `NewCanonicalRelativeURLReporter`, `NewCanonicalMismatchReporter`
- **What the code does:** Uses XPath to inspect `<head>` canonical tags, detects multiple tags, detects relative canonical URLs, and compares HTML canonical against HTTP `Link` header canonical.
- **How it maps to `visibility-detector`:** Product pages may emit inconsistent canonical hints. `ParsedPage` should contain both HTML and header canonical evidence, and `CanonicalMismatchDetector` should cite both sources.
- **Recommendation:** **Adapt conceptually.** Include header canonical extraction in the parser, not only DOM extraction.
- **Risk/complexity/licensing notes:** Header parsing can be tricky; implement a conservative parser and keep raw header evidence.

### 9. Lightweight metadata extraction

- **Source repo:** `research/LibreCrawl`
- **File path:** `research/LibreCrawl/src/core/seo_extractor.py`
- **Class/function/module:** `SEOExtractor.extract_basic_seo_data`, `extract_meta_tags`, `extract_opengraph_tags`, `extract_twitter_tags`, `create_empty_result`
- **What the code does:** Extracts title, meta description, headings, word count, language, charset, robots, canonical URL, OpenGraph/Twitter metadata, and initializes a normalized result structure.
- **How it maps to `visibility-detector`:** The package needs a normalized `ParsedPage` with product-relevant metadata and indexability signals. Product matching can compare expected product name/brand/SKU to title, H1, meta description, visible text, OpenGraph title, and structured data.
- **Recommendation:** **Adapt conceptually.** Do not replicate every SEO field. Keep v0.1 fields tied to visibility explanation.
- **Risk/complexity/licensing notes:** Low complexity. Avoid accumulating unrelated SEO metrics such as analytics tags.

### 10. XPath map for optional extra tags

- **Source repo:** `research/python-seo-analyzer`
- **File path:** `research/python-seo-analyzer/pyseoanalyzer/page.py`
- **Class/function/module:** `HEADING_TAGS_XPATHS`, `ADDITIONAL_TAGS_XPATHS`, `Page.analyze_heading_tags`, `Page.analyze_additional_tags`
- **What the code does:** Defines tag extraction as a mapping from field names to XPath expressions and populates optional metadata dictionaries.
- **How it maps to `visibility-detector`:** A PHP parser can maintain a small selector map for title, description, robots, canonical, hreflang, OpenGraph, Product JSON-LD, and product microdata. This makes fixture tests straightforward.
- **Recommendation:** **Adapt conceptually.** Use Symfony DomCrawler/CssSelector or DOMXPath, depending on dependencies.
- **Risk/complexity/licensing notes:** Low risk. Keep selector maps small and product-oriented.

### 11. Structured data extraction from JSON-LD and microdata

- **Source repo:** `research/LibreCrawl`
- **File path:** `research/LibreCrawl/src/core/seo_extractor.py`
- **Class/function/module:** `SEOExtractor.extract_json_ld`, `extract_schema_org`, `_extract_microdata_properties`
- **What the code does:** Parses `application/ld+json` blocks into structured JSON and extracts Schema.org microdata item types and properties.
- **How it maps to `visibility-detector`:** Product visibility explanations need to know whether a page exposes `Product` schema and whether name, SKU, brand, GTIN, availability, and canonical URL align with the expected product.
- **Recommendation:** **Adapt conceptually.** Implement robust JSON-LD parsing and product-node discovery; microdata support can follow if needed.
- **Risk/complexity/licensing notes:** Medium complexity. JSON-LD can be arrays, graphs, nested nodes, invalid JSON, or script-injected. Treat malformed or missing data as evidence, not as a hard failure.

### 12. Rendered-page extraction as optional enrichment

- **Source repo:** `research/site-audit-seo`
- **File path:** `research/site-audit-seo/src/scrap-site.js`
- **Class/function/module:** `scrapSite`, `evaluatePage`, `customCrawl`
- **What the code does:** Uses a headless Chrome crawler, optionally blocks static resources, evaluates page metadata in the browser context, collects rendered title/H1/meta/canonical/schema counts, and can run Lighthouse.
- **How it maps to `visibility-detector`:** Some ecommerce product pages only expose product data after JavaScript rendering. The core should allow a future `RenderedPageFetcher` to supply rendered HTML/evidence, but v0.1 should not require browser automation.
- **Recommendation:** **Use only as inspiration.** Design the fetcher/parser boundary to accept rendered snapshots later.
- **Risk/complexity/licensing notes:** High operational complexity: Chrome installs, timeouts, anti-bot behavior, resource blocking side effects, and Node subprocess dependencies. Keep out of core.

### 13. Field presets for report shaping

- **Source repo:** `research/site-audit-seo`
- **File path:** `research/site-audit-seo/src/presets/scraperFields.js`
- **Class/function/module:** default export with `minimal`, `seo`, `parse`, `lighthouse`, and `lighthouse-all` field presets
- **What the code does:** Separates collection from output shape by defining named lists of fields to include in different reports.
- **How it maps to `visibility-detector`:** `VisibilityReport` should be stable, but formatters can provide summary, full evidence, and debug views. This helps Laravel or CLI consumers choose output depth.
- **Recommendation:** **Adapt conceptually.** Implement `ReportFormatter` later; v0.1 can return arrays/JSON with all core evidence.
- **Risk/complexity/licensing notes:** Low risk. Avoid exposing hundreds of generic SEO fields.

### 14. Validation rules as data

- **Source repo:** `research/site-audit-seo`
- **File path:** `research/site-audit-seo/src/validate.js`
- **Class/function/module:** `colsValidate`, `validateResults`, `getValidationSum`, `warnErrorThresholds`
- **What the code does:** Encodes warning/error thresholds as a data structure and applies them over result fields.
- **How it maps to `visibility-detector`:** Some detectors can be declarative: HTTP status classes, canonical count, missing title, missing product schema, or content mismatch thresholds. Declarative rules make test cases simpler.
- **Recommendation:** **Adapt conceptually, selectively.** Use class-based detectors for complex visibility explanations and data-driven checks for trivial field thresholds.
- **Risk/complexity/licensing notes:** Medium risk if rules become opaque. Every finding should still carry a human-readable explanation, confidence, and evidence.

### 15. Audit metadata and result normalization

- **Source repo:** `research/lighthouse`
- **File path:** `research/lighthouse/core/audits/audit.js`
- **Class/function/module:** `Audit.meta`, `Audit.audit`, `Audit.generateAuditResult`, `Audit.makeTableDetails`, `Audit.makeNodeItem`
- **What the code does:** Forces each audit to declare metadata, normalizes scores/display modes, selects failure titles, attaches explanations, warnings, and structured details.
- **How it maps to `visibility-detector`:** Each detector should declare stable metadata: `code`, `title`, `description`, evidence schema, and default severity. `Finding` should not be just a string; it should include machine-readable code, severity, confidence, source evidence, and recommendation.
- **Recommendation:** **Use as inspiration.** Do not implement Lighthouse scoring. Model findings explicitly for visibility decisions.
- **Risk/complexity/licensing notes:** Medium complexity if over-engineered. Start with simple PHP value objects.

### 16. Small issue reporter objects

- **Source repo:** `research/seonaut`
- **File path:** `research/seonaut/internal/models/issue_reporter.go`
- **Class/function/module:** `PageIssueReporter`, `MultipageIssueReporter`, `MultipageCallback`
- **What the code does:** Represents page checks as callbacks paired with an error type; multipage checks stream page report IDs for broader issues.
- **How it maps to `visibility-detector`:** A `Detector` interface can receive a `DetectionContext` and return `Finding[]`. Later, multipage/history checks can compare previous visibility checks without changing page detectors.
- **Recommendation:** **Adapt conceptually.** Prefer typed detector classes over anonymous callbacks in PHP for readability and testability.
- **Risk/complexity/licensing notes:** Low risk. Avoid coupling detectors to database IDs.

### 17. Stable issue taxonomy

- **Source repo:** `research/seonaut`
- **File path:** `research/seonaut/internal/issues/errors/errors.go`
- **Class/function/module:** integer constants such as `ErrorNoIndexable`, `ErrorBlocked`, `ErrorSitemapNonCanonical`, `ErrorCanonicalMismatch`, `ErrorNosnippet`
- **What the code does:** Maintains a central list of issue types used by reporters and persistence.
- **How it maps to `visibility-detector`:** Use stable string codes such as `search.not_found`, `page.noindex`, `robots.blocked`, `canonical.mismatch`, `schema.product_missing`, and `content.product_terms_missing`.
- **Recommendation:** **Adapt conceptually.** Prefer strings over integers in a package API so reports are self-describing.
- **Risk/complexity/licensing notes:** Low risk. Codes are API surface; changing them is breaking.

### 18. Page snapshot/result DTO with all extracted signals

- **Source repo:** `research/seonaut`
- **File path:** `research/seonaut/internal/models/pagereport.go`
- **Class/function/module:** `PageReport`
- **What the code does:** Stores URL, parsed URL, redirects, status, media type, language, title, description, robots, noindex/nofollow flags, canonical, headings, links, words, hreflang, images, robots-blocked state, sitemap state, depth, hash, timeout, and TTFB.
- **How it maps to `visibility-detector`:** Create `PageSnapshot` for transport evidence and `ParsedPage` for normalized page evidence. Splitting these avoids mixing raw HTTP response data with parsed semantic fields.
- **Recommendation:** **Adapt conceptually.** Keep v0.1 DTOs smaller and product-oriented.
- **Risk/complexity/licensing notes:** Low risk. Avoid making one large mutable array the package's primary API.

### 19. Content hashing and duplicate detection

- **Source repo:** `research/python-seo-analyzer`
- **File path:** `research/python-seo-analyzer/pyseoanalyzer/page.py` and `research/python-seo-analyzer/pyseoanalyzer/analyzer.py`
- **Class/function/module:** `Page.content_hash`, `analyze`, `duplicate_pages`
- **What the code does:** Hashes raw page content and reports duplicate pages when multiple URLs share the same content hash.
- **How it maps to `visibility-detector`:** Product variants may canonicalize or duplicate content. In v0.1, a product page hash is not required, but later URL variant checks can use hashes to support explanations like “SERP found a different URL with equivalent content.”
- **Recommendation:** **Use as inspiration for later.** Not a v0.1 core requirement.
- **Risk/complexity/licensing notes:** Low implementation complexity, but high interpretation risk. Duplicate content is not automatically a visibility failure.

### 20. Link normalization and fragment removal

- **Source repo:** `research/LibreCrawl`
- **File path:** `research/LibreCrawl/src/core/link_manager.py`
- **Class/function/module:** `LinkManager.extract_links`, `collect_all_links`, `is_internal`
- **What the code does:** Converts relative links to absolute URLs, strips fragments, compares cleaned domains, avoids duplicates, tracks source pages, and separates internal/external links.
- **How it maps to `visibility-detector`:** SERP URL matching needs URL normalization: scheme, host, `www`, trailing slash, fragments, common tracking parameters, canonical URL, and acceptable variants.
- **Recommendation:** **Adapt conceptually.** Implement a dedicated `UrlNormalizer` and `UrlMatcher`, not a site-wide link crawler.
- **Risk/complexity/licensing notes:** Medium risk because ecommerce URLs may use query parameters for meaningful product variants. Do not strip all query parameters blindly.

### 21. Lightweight crawl queue as pipeline inspiration only

- **Source repo:** `research/seonaut`
- **File path:** `research/seonaut/internal/crawler/queue.go` and `research/seonaut/internal/crawler/crawler.go`
- **Class/function/module:** `Queue.manage`, `Crawler.AddRequest`, `Crawler.Start`, `Crawler.crawl`, `Crawler.consumer`
- **What the code does:** Manages queued URLs, active acknowledgements, visited storage, domain constraints, robots checks, sitemap seeding, concurrent consumers, and random request delay.
- **How it maps to `visibility-detector`:** The useful idea is pipeline sequencing and deduplication, not a broad crawler. `visibility-detector` should process a bounded set: product URL plus search-result URLs if needed for matching.
- **Recommendation:** **Use only as inspiration.** Do not implement a crawler in v0.1.
- **Risk/complexity/licensing notes:** High scope risk. A crawler would distract from product visibility detection.

### 22. Fixture-driven unit tests for small rules

- **Source repo:** `research/seonaut`
- **File path:** `research/seonaut/internal/issues/page/*_test.go`, `research/seonaut/internal/crawler/*_test.go`
- **Class/function/module:** tests for canonical, indexability, queue, robots checker, URL storage, and other reporters
- **What the code does:** Keeps issue reporters and crawler components testable through small focused tests.
- **How it maps to `visibility-detector`:** v0.1 should include PHPUnit fixtures for SERP result sets and HTML pages: noindex page, canonical mismatch page, product schema page, missing product text page, and SERP found/not-found cases.
- **Recommendation:** **Adapt conceptually.** The package should be fixture-first before real search API providers.
- **Risk/complexity/licensing notes:** Low risk. Do not copy test fixtures; create ecommerce product-specific fixtures.

### 23. Lighthouse canonical test cases as edge-case catalog

- **Source repo:** `research/lighthouse`
- **File path:** `research/lighthouse/core/test/audits/seo/canonical-test.js`
- **Class/function/module:** canonical audit test cases
- **What the code does:** Tests no canonical, multiple canonical URLs, invalid URLs, relative URLs, hreflang mismatch, root canonical mistakes, identical duplicate canonicals, header canonicals, and body canonicals.
- **How it maps to `visibility-detector`:** Use these categories to design PHP unit tests for `CanonicalUrlExtractor` and `CanonicalMismatchDetector`.
- **Recommendation:** **Use as inspiration.** Recreate cases in product-specific fixtures.
- **Risk/complexity/licensing notes:** Low risk if we write original fixtures and assertions.

## `semantic-fashion-search` review status and future-product-query patterns

The repository entry `research/semantic-fashion-search` is included as a reference project, but in this checkout it is a Git submodule pointer rather than materialized source code. `find research/semantic-fashion-search -type f` returns no files, and `git submodule update --init --recursive research/semantic-fashion-search` fails because `.gitmodules` does not provide a URL for that path. Therefore this review intentionally does **not** claim specific inspected classes or functions from that repository yet.

This still affects the planning documentation because the project's intended reference value is distinct from the crawler/indexability repositories already reviewed: it belongs to a future **Expected Visibility / Product-Query Matching** layer, not to the v0.1 noindex/canonical/robots core. Once the submodule source is materialized, review should focus on the concrete files/classes/functions/modules that implement product embeddings, query embeddings, semantic similarity, vector search, product metadata normalization, user-query-to-product mapping, expected-query generation or validation, and demo/test dataset structure.

### Semantic reference scope to reserve for later review

- **Source repo:** `research/semantic-fashion-search`
- **File path:** `research/semantic-fashion-search` submodule pointer at commit `d163cff1ce16faef12b092301bcd4f34147f13b9`; no source files are present in this checkout.
- **Class/function/module:** Not available in the current checkout. Do not invent names until the submodule is populated.
- **What the code does:** Intended reference area is semantic product-query matching: embedding products and queries, scoring semantic similarity, and ranking candidate products for natural-language product intent.
- **How it maps to `visibility-detector`:** This maps to a later layer that can validate whether a caller's expected queries semantically describe the product and can suggest related expected queries. It does **not** replace v0.1's external SERP evidence collection, URL matching, noindex checks, canonical diagnostics, or structured-data checks.
- **Recommendation:** **Use as inspiration after source is available.** Reserve interfaces now; do not copy or implement semantic search code yet.
- **Risk/complexity/licensing notes:** High complexity compared with v0.1 because embeddings introduce model choice, vector storage, latency, explainability, evaluation datasets, and licensing concerns. Also, the current checkout cannot verify license headers or dependency licenses inside the submodule.

### Pattern 24. Product and query embedding provider seam

- **Source repo:** `research/semantic-fashion-search`
- **File path:** To be confirmed after the submodule is materialized; likely files related to embedding generation or model clients.
- **Class/function/module:** To be confirmed; look for product-embedding and query-embedding generators.
- **What the code does:** A semantic search system normally transforms normalized product text and natural-language queries into vectors in the same embedding space so that product-query relevance can be computed beyond exact keyword overlap.
- **How it maps to `visibility-detector`:** Reserve an optional `ProductQueryMatcher` or `SemanticQueryMatcher` contract that accepts a `ProductSubject` and `SearchQuery` and returns a deterministic `QueryProductMatch` with score, matched fields, and explanation metadata. This lets future versions validate expected queries without making embeddings part of v0.1.
- **Recommendation:** **Adapt only at the interface level for now.** Keep v0.1 deterministic and dependency-free; add embedding adapters later as optional packages.
- **Risk/complexity/licensing notes:** Embedding providers can be proprietary, network-bound, non-deterministic across model versions, and expensive. Store model name/version and normalization inputs in evidence if this is added later.

### Pattern 25. Vector-search candidate ranking as an optional expected-query validator

- **Source repo:** `research/semantic-fashion-search`
- **File path:** To be confirmed after the submodule is materialized; likely files related to vector indexes or nearest-neighbor search.
- **Class/function/module:** To be confirmed; look for vector-search, similarity-search, or nearest-neighbor modules.
- **What the code does:** Vector search retrieves products whose embeddings are nearest to the query embedding, usually with cosine similarity, dot product, or distance metrics.
- **How it maps to `visibility-detector`:** Later versions can use this as an internal sanity check: if a product does not semantically rank for one of its expected queries inside the merchant catalog, an external-SERP absence may reflect a weak expected query rather than a crawl/indexability issue.
- **Recommendation:** **Use as inspiration.** Do not make vector storage a core dependency; expose a matcher contract and allow callers to plug in their own vector index.
- **Risk/complexity/licensing notes:** Vector search can broaden the package into catalog search infrastructure. Keep it bounded to expected-query validation and evidence generation, not a product-search application.

### Pattern 26. Product metadata normalization before semantic matching

- **Source repo:** `research/semantic-fashion-search`
- **File path:** To be confirmed after the submodule is materialized; likely dataset loaders, product serializers, or metadata preprocessing modules.
- **Class/function/module:** To be confirmed; look for functions that combine product title, brand, category, description, attributes, variants, colors, sizes, or tags into searchable text.
- **What the code does:** Semantic product search depends on converting heterogeneous product metadata into consistent text/features before embedding and retrieval.
- **How it maps to `visibility-detector`:** The existing `ProductSubject` should keep generic fields (`name`, `brand`, `sku`, `gtin`, `description`, `attributes`, `acceptableUrls`) so future query matching can normalize catalog data without hard-coding fashion attributes.
- **Recommendation:** **Adapt conceptually.** Preserve generic metadata normalization hooks, but do not introduce fashion-specific taxonomies.
- **Risk/complexity/licensing notes:** Attribute normalization can become domain-specific quickly. Avoid color/size/category assumptions in the core package.

### Pattern 27. Expected-query generation and validation fixtures

- **Source repo:** `research/semantic-fashion-search`
- **File path:** To be confirmed after the submodule is materialized; likely demo data, fixtures, notebooks, seed data, or tests.
- **Class/function/module:** To be confirmed; look for expected query lists, demo query/product pairs, validation scripts, and evaluation metrics.
- **What the code does:** Semantic search projects often include small product catalogs and representative natural-language queries to demonstrate or evaluate that query intent maps to the right products.
- **How it maps to `visibility-detector`:** Future fixtures should include product/query pairs such as `expected_match`, `weak_match`, and `mismatch`. These fixtures can test whether expected queries are plausible before external search APIs are involved.
- **Recommendation:** **Adapt the fixture idea only.** Create original, generic ecommerce fixtures; do not copy fashion data or broaden the product into fashion search.
- **Risk/complexity/licensing notes:** Demo datasets may have unclear licensing, personal data, or brand/product-trademark constraints. Use synthetic fixtures unless licenses are explicit.

## Recommendation for semantic product-query matching

Semantic product-query matching should be **v0.2 or later**, not v0.1. v0.1 should reserve a minimal interface but remain focused on indexability, canonical/noindex diagnostics, product-page evidence, search-result collection, and URL matching.

Minimal interface to reserve now:

```php
interface ProductQueryMatcher
{
    /**
     * @return QueryProductMatch[] Evidence explaining whether each query plausibly maps to the product.
     */
    public function match(ProductSubject $product, array $queries): array;
}
```

The returned `QueryProductMatch` should be a small value object with `query`, `score`, `status` (`strong_match`, `weak_match`, `mismatch`, `not_evaluated`), `matchedFields`, `modelOrMethod`, and `evidence`. A v0.1 implementation can ship only a `NullProductQueryMatcher` or omit the dependency entirely while keeping constructor/configuration space for it.

## Code patterns to avoid

### Avoid broad crawler scope

- **Source examples:** `research/LibreCrawl/src/crawler.py`, `research/seonaut/internal/crawler/crawler.go`, `research/site-audit-seo/src/scrap-site.js`
- **Why to avoid:** Full crawling brings queue management, concurrency, persistence, robots policy, sitemap loading, dashboards, and rate limits. The product needs targeted visibility detection for known products and expected queries.
- **Visibility-specific alternative:** Accept explicit `ProductSubject` and `SearchQuery[]`; fetch only the product URL and consume external SERP evidence through `SearchProvider`.

### Avoid browser automation as a core dependency

- **Source examples:** `research/site-audit-seo/src/scrap-site.js`, `research/LibreCrawl/src/core/js_renderer.py`
- **Why to avoid:** Browser rendering adds Node/Chrome installation, timeouts, resource blocking decisions, and operational fragility.
- **Visibility-specific alternative:** Keep core `PageFetcher` static-HTML first; add a later optional rendered snapshot provider.

### Avoid Lighthouse as a required subprocess

- **Source examples:** `research/site-audit-seo/src/scrap-site.js`, `research/site-audit-seo/src/presets/scraperFields.js`, `research/lighthouse/core/audits/*`
- **Why to avoid:** Lighthouse is excellent for audits, but external product search visibility is not its primary question.
- **Visibility-specific alternative:** Borrow its audit-result structure and metadata discipline, not the runtime dependency.

### Avoid generic SEO scoring

- **Source examples:** `research/site-audit-seo/src/validate.js`, `research/lighthouse/core/audits/audit.js`
- **Why to avoid:** A numeric score can hide the direct reason a product is missing from search results.
- **Visibility-specific alternative:** Return `visible`, `not_visible`, or `uncertain` per query plus evidence-backed findings.

### Avoid untyped mutable result arrays as the public API

- **Source examples:** `research/LibreCrawl/src/core/seo_extractor.py`, `research/site-audit-seo/src/scrap-site.js`
- **Why to avoid:** Large result maps are flexible but brittle for library consumers.
- **Visibility-specific alternative:** Use typed PHP value objects: `ProductSubject`, `SearchQuery`, `SearchResult`, `PageSnapshot`, `ParsedPage`, `Finding`, `QueryVisibility`, and `VisibilityReport`.

### Avoid AI-generated explanations in v0.1

- **Source example:** `research/python-seo-analyzer/pyseoanalyzer/llm_analyst.py`
- **Why to avoid:** The core needs deterministic explanations tied to evidence.
- **Visibility-specific alternative:** Use deterministic finding templates; optional AI summaries can come later from structured findings.

## Recommended first components for v0.1

Implement these first, in this order, with original PHP code and ecommerce-specific fixtures:

1. `ProductSubject`, `SearchQuery`, `SearchResult`, `SearchResultSet`, `PageSnapshot`, `ParsedPage`, `Finding`, `QueryVisibility`, and `VisibilityReport` value objects.
2. `SearchProvider` plus `StaticSearchProvider` for fixture SERP results.
3. `UrlNormalizer` and `CanonicalUrlMatcher` for matching SERP URLs to product URLs and acceptable variants.
4. `PageFetcher` interface and an optional PSR-18 implementation.
5. `PageParser` that extracts title, description, robots meta, `X-Robots-Tag`, canonical tag, canonical header, H1, visible text summary, OpenGraph title, JSON-LD Product nodes, and basic microdata Product nodes if feasible.
6. `VisibilityDetector` orchestrator that runs query checks, page fetch/parse, URL matching, and detectors.
7. Detectors for:
   - product not found in top N SERP results,
   - product found with position and matched URL,
   - HTTP status/transport failure,
   - `noindex` or `none` robots directive,
   - robots.txt blocked when robots evidence is supplied,
   - canonical mismatch,
   - product name/brand/SKU content mismatch,
   - missing or mismatched Product structured data.
8. `ArrayReportFormatter` or `JsonReportFormatter` for deterministic reports.
9. PHPUnit fixtures under `tests/Fixtures/serp` and `tests/Fixtures/html` covering found/not-found SERPs, noindex, canonical mismatch, missing schema, malformed JSON-LD, and product-content mismatch.

No production source code should be added until these interfaces and fixtures are intentionally planned. The reference projects should guide architecture and tests, not provide copied implementation.
