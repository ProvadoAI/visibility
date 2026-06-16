# Deferred scope

## Purpose

This document is the single, durable backlog of capabilities that have been **explicitly deferred** from the shipped versions of `visibility-detector`.

It exists so that:

- the deliberate non-goals scattered across `docs/mvp.md`, `docs/architecture.md`, and each `docs/roadmap/v0.x.md` are visible in one place;
- a reader can tell the difference between "not built yet on purpose" and "missing by accident";
- each deferred item records **why** it is deferred and **what condition would unlock it**, so future versions can pull from this list instead of re-deciding scope each time.

This is a backlog, not a commitment. Items here are candidates for future versions; being listed does not schedule them.

## How to use this document

- When a version intentionally excludes a capability, add or update its entry here.
- When a version implements a deferred item, move it to "Recently unlocked" with the version that delivered it.
- Keep the per-version roadmap `non-goals` sections pointing here rather than duplicating rationale.

## Review cadence

This document is reviewed as part of **closing out each version** — it is a gate in the
version's completion definition, not an afterthought. When a version reaches `Validated`,
before planning the next one:

1. Move anything the version delivered into "Recently unlocked", tagged with that version.
2. Update the "Current version context" line.
3. Re-check every remaining item's **unlock condition** — a just-shipped version often
   satisfies a prerequisite (e.g. v0.4's HTTP plumbing unlocks live search acquisition).
4. Add any new capability the version deliberately pushed out of scope.
5. Promote the strongest now-unblocked candidate as the proposed theme for the next version.

## Status legend

- `Deferred` — intentionally out of scope for now; a candidate for a future version.
- `Partially unlocked` — some enabling work exists, but the full capability is still out of scope.
- `Recently unlocked` — implemented in a shipped version; kept here briefly for traceability.

## Current version context

- Shipped and validated: **v0.1**, **v0.2**, **v0.3**.
- Planned next: **v0.4 — real page-evidence acquisition** (see `docs/roadmap/v0.4.md`).

The core today is a deterministic, single-product diagnostic engine. It accepts supplied search-result evidence and supplied (fixture) page evidence, and returns a structured `VisibilityReport`. The deferred items below describe everything beyond that boundary.

## Deferred capabilities

### 1. Live search-result acquisition

- **Status:** Deferred (top candidate for v0.5).
- **What:** A real `SearchProvider` that queries Google, Bing, or similar and returns observed `SearchResultSet`s, instead of relying on supplied/fixture evidence.
- **Why deferred:** The core principle "no acquisition coupling" (`docs/architecture.md`) keeps the engine independent of how results were obtained. Live acquisition adds API keys, quotas, rate limits, and non-determinism that would compromise the deterministic test suite if built into the core.
- **Unlock condition:** v0.4 establishes HTTP plumbing, a safety `FetchPolicy`, and an opt-in live CLI mode. Once those exist, a live `SearchProvider` adapter can be added behind the existing interface with cached/snapshot capture to preserve determinism in tests.

### 2. Marketplace, Google Shopping, and comparison-engine adapters

- **Status:** Deferred.
- **What:** Visibility checks against marketplaces, Google Shopping, and comparison/discovery engines named in `docs/project-objective.md`.
- **Why deferred:** Each is a distinct acquisition surface with its own auth, formats, and terms. They depend on the same acquisition abstractions as live search.
- **Unlock condition:** After live search acquisition (item 1) proves the provider-adapter pattern end to end.

### 3. AI answer-engine / shopping-agent visibility

- **Status:** Deferred.
- **What:** Measuring whether products surface in AI search assistants and future shopping/discovery agents.
- **Why deferred:** Highest non-determinism of all channels and dependent on the acquisition-adapter foundation. Explicitly out of scope in the MVP.
- **Unlock condition:** A stable acquisition-adapter layer plus a defined, testable evidence format for answer-engine responses.

### 4. Semantic / vector product-query matching

- **Status:** Deferred.
- **What:** Embeddings, vector search, CLIP/FAISS-style infrastructure, and a `ProductQueryMatcher` that decides which queries a product *should* appear for.
- **Why deferred:** The core is deterministic and "evidence over opinions." v0.1–v0.4 require the caller to supply expected queries; the engine does not infer them.
- **Unlock condition:** A deliberate decision to add a non-deterministic "expected visibility" layer, isolated behind an interface and excluded from the deterministic core test guarantees. `semantic-fashion-search` research is the intended seam.

### 5. Automatic expected-query generation

- **Status:** Deferred.
- **What:** Generating the queries a product should rank for, rather than receiving them from the caller.
- **Why deferred:** Same determinism boundary as item 4; the roadmaps state the engine "does not decide automatically which queries a product should appear for."
- **Unlock condition:** Built together with or after semantic matching (item 4).

### 6. Browser rendering / JavaScript execution

- **Status:** Deferred.
- **What:** Fetching and analyzing pages whose content is rendered client-side (Playwright/Puppeteer-style rendering).
- **Why deferred:** Heavy runtime dependency and non-determinism. v0.4 introduces server-response HTTP fetching only.
- **Unlock condition:** Demonstrated need from real merchant pages where server HTML is insufficient, plus an isolated rendered-page fetcher behind the `PageFetcher` interface.

### 7. Full-site crawling

- **Status:** Partially unlocked by v0.4 (bounded), otherwise Deferred.
- **What:** Crawling beyond the expected product URL — following internal links across a site.
- **Why deferred:** Scope, politeness, and runtime cost. v0.4 deliberately limits live fetching to the expected product URL (plus its redirects), `robots.txt`, and a bounded sitemap inspection — explicitly **not** a crawl.
- **Unlock condition:** A separate, clearly-scoped crawler effort with its own politeness and bounding rules.

### 8. Batch / catalog analysis

- **Status:** Deferred.
- **What:** Analyzing many products in one run and producing aggregate, catalog-level reports (`BatchVisibilityAnalyzer`).
- **Why deferred:** Every version through v0.4 fixes "one product at a time" as the central unit to keep the model and reports simple.
- **Unlock condition:** The single-product report and prioritization are stable enough to aggregate; a defined catalog input format (e.g. CSV/JSON) and aggregate report shape.

### 9. Laravel / ecommerce-platform integration

- **Status:** Deferred.
- **What:** A Laravel package wrapper and translators for platforms such as Magento/Adobe Commerce, Shopify, Medusa, WooCommerce.
- **Why deferred:** "No ecommerce-platform coupling" (`docs/architecture.md`): the core must stay framework-agnostic. Integrations belong in separate packages that translate platform data into `ProductSubject` / `SearchQuery` / `SearchResultSet`.
- **Unlock condition:** A stable core API worth wrapping; built as a separate package depending on the core, never inside it.

### 10. Persistence, scheduling, and monitoring

- **Status:** Deferred.
- **What:** Historical storage of reports, scheduled re-analysis, and ongoing visibility monitoring.
- **Why deferred:** The core is a stateless diagnostic engine. State and scheduling are application concerns layered on top.
- **Unlock condition:** A consuming application (SaaS or otherwise) that owns storage and scheduling.

### 11. SaaS / multi-tenant platform and dashboard UI

- **Status:** Deferred.
- **What:** Account/project model, multi-tenancy, and a dashboard UI.
- **Why deferred:** The MVP is explicitly "the core diagnostic layer that those systems can use later," not the product surface.
- **Unlock condition:** A productization decision built on top of the core, persistence, and (likely) live acquisition.

### 12. Competitor analysis and historical ranking charts

- **Status:** Deferred.
- **What:** Comparing a merchant's visibility against competitors and charting rank/visibility over time.
- **Why deferred:** Requires live acquisition (items 1–2) plus persistence/history (item 10).
- **Unlock condition:** After acquisition and persistence exist.

### 13. Revenue forecasting and attribution modeling

- **Status:** Deferred.
- **What:** Translating visibility failures into modeled revenue impact and attribution.
- **Why deferred:** The engine carries caller-supplied commercial context (priority, value, price) but deliberately does not model revenue. "Evidence over opinions."
- **Unlock condition:** A separate analytics layer with its own data and validated model; never part of the deterministic core.

## Recently unlocked

_None yet. When a deferred item ships, move it here with the delivering version (for example: "Real page-evidence acquisition — unlocked in v0.4")._
