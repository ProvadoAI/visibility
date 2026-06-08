# Changelog

## v0.1.0 - Initial deterministic visibility engine

v0.1.0 introduces a deterministic PHP core for analyzing one ecommerce product at a time using caller-supplied search results and product-page evidence.

This version focuses on visibility diagnostics, root-cause evidence, and prioritized reporting. It does not perform live scraping, external API calls, AI checks, semantic matching, batch monitoring, Laravel integration, or SaaS workflows.

### Added

- Composer package skeleton for `VisibilityDetector`.
- Core value objects for product, search evidence, page evidence, findings, query visibility, reports, and summaries.
- `StaticSearchProvider` for deterministic caller-supplied search result evidence.
- `FixturePageFetcher` for local product-page evidence without real HTTP calls.
- URL normalization and matching, including tracking-parameter cleanup and acceptable URL variants.
- Query-level visibility detection for `visible`, `not_visible`, and `uncertain` outcomes.
- DOM page parser for title, meta description, canonical URL, robots directives, headings, links, JSON-LD, schema candidates, and body text summary.
- HTTP availability diagnostics for fetch failures, HTTP errors, redirects, and non-HTML responses.
- Indexability diagnostics for `noindex`, robots `none`, and `unavailable_after` evidence.
- Canonical diagnostics for missing, invalid, relative, conflicting, homepage, and other-URL canonicals.
- Structured data diagnostics for Product and Offer schema gaps.
- Basic lexical content alignment diagnostics using caller-supplied expected product terms.
- `VisibilityAnalyzer` orchestration for one product and one or more supplied queries.
- Deterministic report summary and prioritization with probable causes, recommended actions, evidence references, and commercial/query priority context.
- `JsonReportSerializer` for stable machine-readable report output with deterministic timestamps when supplied.
- Local examples, fixtures, README usage documentation, and sample JSON report output.

### Not included in v0.1.0

- Live Google, Bing, or marketplace scraping.
- Browser rendering.
- Automatic expected-query generation.
- AI or LLM visibility checks.
- Embeddings or semantic/vector matching.
- Batch analysis for many products.
- Scheduled monitoring.
- Laravel integration.
- Dashboard or SaaS account/project model.
- Revenue forecasting or attribution modeling.

### Validation

- v0.1 roadmap completed through Phase 16.
- Runtime validation is owner-managed with `composer test`.
