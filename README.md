# visibility-detector

`visibility-detector` is a PHP package for deterministic product visibility analysis.

## v0.1 scope

v0.1 is limited to a core engine skeleton and deterministic analysis of caller-supplied product, query, search-result, and page HTML evidence. It does not include Laravel integration, dashboards, live scraping, crawlers, live search providers, AI answer-engine checks, semantic/vector matching, or external API calls.

## v0.1 usage example

v0.1 analyzes **one product at a time**. The package does not scrape Google, Bing, marketplaces, or any other external provider. Search results and product-page HTML are supplied by the caller, usually from in-memory objects or local fixtures. Given that deterministic evidence, the analyzer produces query visibility findings, deterministic visibility health, and a prioritized summary.

The repository includes a local-only demo under [`examples/`](examples/):

- [`examples/basic-analysis.php`](examples/basic-analysis.php) builds one `ProductSubject`, one expected-visible `SearchQuery`, a `StaticSearchProvider`, a `FixturePageFetcher`, `VisibilityAnalyzer`, and `JsonReportSerializer`.
- [`examples/fixtures/search-results.json`](examples/fixtures/search-results.json) contains static search result evidence where the expected product URL is absent.
- [`examples/fixtures/product-page.html`](examples/fixtures/product-page.html) contains deterministic product-page HTML with technical issues: a `noindex` meta directive, a canonical URL pointing to another page, and no Product/Offer JSON-LD.
- [`examples/sample-report.json`](examples/sample-report.json) is a short deterministic JSON report with `generatedAt` fixed to `2026-01-01T00:00:00+00:00`.

After installing dependencies in your own environment, you can run the example script locally:

```sh
php examples/basic-analysis.php
```

Runtime validation for this repository is owner-managed; the example is intentionally fixture-only and does not perform HTTP calls, browser automation, live scraping, or framework bootstrapping.

### Minimal flow

```php
$product = new ProductSubject(
    expectedUrl: 'https://example.test/products/aurora-trail-shoe',
    name: 'Aurora Trail Shoe',
    brand: 'Acme Outdoor',
    category: 'Trail running shoes',
    expectedTerms: ['Aurora Trail Shoe', 'Acme Outdoor', 'waterproof trail running shoes'],
    commercialPriority: 'critical',
    commercialValue: 'launch_product_high_margin',
);

$query = new SearchQuery(
    text: 'acme waterproof trail running shoes',
    provider: 'static-fixture',
    intent: 'category_product',
    expectedVisibility: true,
    priority: 'critical',
    reason: 'High-value launch product should appear for branded category demand.',
);

$report = $analyzer->analyze($product, [$query]);
$json = (new JsonReportSerializer())->serialize($report, new DateTimeImmutable('2026-01-01T00:00:00+00:00'));
```

The full example wires the supporting objects explicitly:

- `StaticSearchProvider` receives a caller-supplied `SearchResultSet` from the JSON fixture.
- `FixturePageFetcher` receives a `PageSnapshot` whose body comes from the HTML fixture.
- `VisibilityAnalyzer` uses the static provider, fixture fetcher, URL matcher, page parser, detectors, and summary generation.
- `JsonReportSerializer` emits deterministic JSON when passed a fixed timestamp.


### URL evidence policy

Reports use explicit URL roles so search-result matching, fetching, redirects, expected product targets, and parsed canonical declarations are not conflated:

- `matchedUrl` is the URL found in caller-supplied search-result evidence. It is preserved exactly as supplied by the search provider and is not overwritten with a normalized, expected, final, or canonical URL.
- `requestedUrl` is the URL sent to the `PageFetcher` by the analyzer.
- `finalUrl` is the final URL known from fixture or redirect evidence in the `PageSnapshot`.
- `expectedUrl` is the merchant/product URL supplied on `ProductSubject`.
- `canonicalUrl` is the canonical URL declared by the parsed page.

Evaluation is deterministic and one-product scoped:

- Visibility matching compares `expectedUrl` and `acceptableUrlVariants` against the preserved `matchedUrl` from supplied search results.
- Page diagnostics use fetch evidence: `requestedUrl`, `finalUrl`, and page body evidence.
- Canonical diagnostics compare `canonicalUrl` against `expectedUrl` plus `acceptableUrlVariants`.

The top-level `urlEvidence` report section summarizes these roles for the analyzed product. Query-level `urlMatch` evidence preserves each query's `matchedUrl`, and `pageSnapshot` preserves the fetcher's `requestedUrl` and `finalUrl`.

In reports with multiple query visibilities, `urlEvidence.matchedUrls` may include more than one preserved search-result URL. `urlEvidence.requestedUrl`, `urlEvidence.finalUrl`, and `urlEvidence.canonicalUrl` describe the single page snapshot fetched and parsed by the analyzer for the product.

### Reading the JSON output

The main output sections are:

- `queryVisibilities`: one entry per supplied query, including the query context, visible/not-visible/uncertain status, `visibilityHealth`, URL match evidence, query-level findings, and warnings.
- `visibilityHealth`: query-level technical health derived only from deterministic findings. Values are `healthy`, `at_risk`, `blocked`, or `unknown`; this is separate from the visible/not-visible/uncertain result-set status.
- `findings`: diagnostics attached to each query visibility. In the demo these include absence from supplied search results, `noindex`, canonical mismatch, and missing Product/Offer structured data.
- `summary.overallStatus`: the rollup visibility status for the product across supplied queries.
- `summary.overallPriority`: the business priority after combining query priority, product commercial context, and finding severity.
- `summary.topProbableCauses`: the most important likely reasons the product is not visible.
- `summary.topRecommendedActions`: prioritized next actions derived from the findings.
- `diagnosticSummary`: additive merchant-facing fields for demos and integrations, including `title`, `statusExplanation`, `primaryIssue`, `merchantExplanation`, `recommendedNextStep`, and `evidenceHighlights`. This object is deterministic and derived only from existing report data: statuses, query `visibilityHealth`, prioritized findings, recommended actions, URL evidence, and report summary values. It does not use AI, LLMs, embeddings, semantic matching, scraping, browser rendering, external services, dashboards, SaaS workflows, or batch analysis.
- `evidenceReferences`: structured evidence excerpts copied from the top summary findings, including code, category, affected query, severity, and supporting evidence.

`diagnosticSummary` is intentionally a summary layer, not a replacement for the full structured report. Full JSON output still includes the original query findings, URL evidence, `pageSnapshot`, `parsedPage`, `summaryFindings`, and `summary` sections so callers can inspect or present the underlying deterministic evidence. Compact JSON output remains focused on the existing compact fields and safely omits the merchant-facing summary.

## Documents

- [Project objective](docs/project-objective.md)
- [MVP definition](docs/mvp.md)
- [Architecture](docs/architecture.md)
- [v0.1 roadmap](docs/roadmap/v0.1.md)

## Minimal scenario CLI

The repository also includes a small plain-PHP developer CLI for running deterministic local scenario JSON files without creating a new PHP script for each scenario.

From a repository checkout, run the local bin directly:

```sh
bin/visibility analyze examples/scenarios/not-visible.json
```

When installed as a dependency through Composer, run the Composer-exposed bin:

```sh
vendor/bin/visibility analyze <scenario-json-path>
```

Full JSON output remains the default. Without additional flags, the command prints the existing `JsonReportSerializer` JSON report format to stdout, including full report evidence such as `pageSnapshot` and `parsedPage` sections.

For terminal demos or quick inspection, pass `--compact` to print deterministic compact JSON with only product identity, overall status and priority, query statuses, query `visibilityHealth`, top probable causes, top recommended actions, URL evidence summary, and warnings:

```sh
bin/visibility analyze examples/scenarios/visible-clean.json --compact
```

Compact JSON omits full `pageSnapshot.body`, large parsed page internals, and large nested evidence payloads. Errors, such as an unknown command, invalid JSON, a scenario validation failure, or a missing fixture file, are written to stderr and return a non-zero exit code.

The CLI is intentionally local-only: it does not call search engines, fetch live pages, run browser rendering, crawl sites, use Laravel, or invoke external services. Search-result evidence comes from scenario inline data or local JSON fixtures, and page evidence comes from local HTML fixtures loaded into the existing `FixturePageFetcher` abstraction.

### Scenario JSON format

A scenario file is explicit and one-product scoped. It defines:

- `product`: a `ProductSubject` payload, including `expectedUrl` and optional fields such as `name`, `brand`, `acceptableUrlVariants`, and commercial context.
- `queries`: one or more `SearchQuery` payloads for that single product.
- `searchResultFixtures`: optional local JSON fixture paths containing one `SearchResultSet` object or an array of result-set objects.
- `searchResults`: optional inline `SearchResultSet` objects when a scenario should keep search evidence in the scenario file.
- `pageFixtures`: local page fixture definitions. Each entry supplies the requested/final URL, status metadata, and either `htmlFixture` for a local HTML file or an inline `body` value.

Relative fixture paths are resolved predictably from the scenario file location first, then from the repository root/current working directory. The examples use repository-root-relative fixture paths such as `examples/fixtures/product-page-clean.html`.

### Scenario validation and authoring guardrails

The CLI validates scenario structure before analysis starts. Invalid scenarios fail early, write a clear `Error: ...` message to stderr, and return a non-zero exit code. Validation is deterministic and local-only; it does not use network calls, browser rendering, scraping, schema registries, Laravel, databases, UI workflows, or batch execution.

Common scenario authoring mistakes caught before analysis include:

- missing or empty `product.expectedUrl`;
- missing `queries`, an empty `queries` array, or query objects with missing/empty `text` or `provider`;
- missing search-result evidence for a declared query;
- search-result evidence whose query/provider/locale/device context does not match any declared scenario query;
- duplicate search-result evidence for the same query/provider/locale/device context, because that would make fixture selection ambiguous;
- missing local JSON or HTML fixture files;
- malformed `pageFixtures`, including entries without `requestedUrl` or without either `htmlFixture` or `body`;
- page evidence that does not include a fixture for the product `expectedUrl` requested by the one-product analyzer.

For each query in `queries`, provide exactly one matching search-result evidence object across `searchResultFixtures` and `searchResults`. A match uses the deterministic query context: `text`, `provider`, `locale`, and `device`. Page evidence should include a fixture whose `requestedUrl` is the product `expectedUrl`, because the analyzer fetches that single product URL from local fixtures.

```json
{
  "product": {
    "expectedUrl": "https://example.test/products/aurora-trail-shoe",
    "name": "Aurora Trail Shoe",
    "brand": "Acme Outdoor",
    "acceptableUrlVariants": []
  },
  "queries": [
    {
      "text": "acme waterproof trail running shoes",
      "provider": "static-fixture",
      "expectedVisibility": true,
      "priority": "critical"
    }
  ],
  "searchResultFixtures": [
    "examples/fixtures/search-results.json"
  ],
  "pageFixtures": [
    {
      "requestedUrl": "https://example.test/products/aurora-trail-shoe",
      "finalUrl": "https://example.test/products/aurora-trail-shoe",
      "htmlFixture": "examples/fixtures/product-page-clean.html",
      "statusCode": 200,
      "contentType": "text/html; charset=utf-8"
    }
  ]
}
```

### Included scenarios

The local deterministic scenarios under `examples/scenarios/` are:

- `not-visible.json`: uses `examples/fixtures/search-results.json`, where the expected product URL is absent.
- `visible-clean.json`: uses inline visible search-result evidence and `product-page-clean.html`.
- `visible-noindex.json`: uses inline visible search-result evidence and `product-page-noindex.html`.
- `visible-canonical-mismatch.json`: uses inline visible search-result evidence and `product-page-canonical-mismatch.html`.
- `visible-missing-schema.json`: uses inline visible search-result evidence and `product-page-missing-schema.html`.

Existing PHP example scripts, including `examples/basic-analysis.php` and `examples/run-analysis.php`, remain available for compatibility.

## Live HTTP page fetching (opt-in)

By default the analyzer reads page evidence from fixtures and performs **no
network calls**. v0.4 adds an optional `VisibilityDetector\Adapters\Http\Psr18PageFetcher`
that can fetch the expected product page over real HTTP, behind the same
`PageFetcher` interface. It depends on PSR HTTP **abstractions only**
(`psr/http-client`, `psr/http-factory`, `psr/http-message`); you inject any
concrete PSR-18 client and PSR-17 factories:

```php
use VisibilityDetector\Adapters\Http\FetchPolicy;
use VisibilityDetector\Adapters\Http\Psr18PageFetcher;

$fetcher = new Psr18PageFetcher(
    client: $yourPsr18Client,        // e.g. Guzzle, Symfony HttpClient
    requestFactory: $yourPsr17Factory,
    uriFactory: $yourPsr17Factory,
    policy: FetchPolicy::default(),   // safe defaults; see below
);
```

### Fetch safety policy (`FetchPolicy`)

Live fetching is bounded and gated by a deterministic `FetchPolicy`. Exceeding a
bound never throws — it produces a controlled `PageSnapshot` with a warning
and/or an appropriate `failureType`. The defaults are conservative:

| Setting | Default | Behaviour when exceeded |
| --- | --- | --- |
| `timeoutSeconds` | `10.0` | Apply this to your PSR-18 client to bound each request (a client timeout is mapped to `failureType = timeout`). The fetcher also enforces it as a wall-clock budget *between* redirect hops. |
| `connectTimeoutSeconds` | `5.0` | Advisory — apply it to your PSR-18 client (PSR-18 has no transport-config hook). |
| `maxRedirects` | `5` | Redirects beyond the cap are not followed; the partial result is returned with a warning. |
| `maxBodyBytes` | `5_242_880` (5 MiB) | Larger bodies are truncated to the limit with a warning. |
| `userAgent` | `visibility-detector/0.4` | Sent on every request. |
| `allowedHosts` | `[]` (any host) | A host not on a non-empty allowlist is refused before any request (`failureType = blocked`). |
| `deniedHosts` | `[]` | A host on the denylist is refused before any request (`failureType = blocked`), taking precedence over the allowlist. |

Override any subset, for example to restrict fetching to a single host:

```php
$policy = new FetchPolicy(
    timeoutSeconds: 8.0,
    maxRedirects: 3,
    allowedHosts: ['example.test'],
);
```

This fetcher only requests the expected URL (plus its redirects). It does not
crawl the site or follow page links. The default, fixture-backed analysis path
remains fully deterministic and network-free.
