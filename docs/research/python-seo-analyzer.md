# python-seo-analyzer

## What this project actually does

`python-seo-analyzer` is a Python crawler/content analyzer. It crawls a base website, creates `Page` objects, extracts text and metadata, computes word/n-gram counts, records warnings, tracks duplicate content by content hash, and returns a JSON-like analysis output. It also has optional LLM-enhanced analysis using LangChain/Anthropic (`research/python-seo-analyzer/pyseoanalyzer/page.py`, `research/python-seo-analyzer/pyseoanalyzer/website.py`, `research/python-seo-analyzer/pyseoanalyzer/analyzer.py`, `research/python-seo-analyzer/pyseoanalyzer/llm_analyst.py`).

The source is useful for page-content parsing, selector maps, duplicate-content detection, output shaping, and unit-test strategy. It is less useful for search-result inspection or Product schema validation, which are not implemented in the inspected code.

## Relevant source-code areas inspected

- `research/python-seo-analyzer/pyseoanalyzer/page.py` — `Page` data container, metadata extraction, XPath selector maps for headings and extra tags, content hashing, content extraction, tokenization, n-gram generation, links/images/H1/title/description warnings, and optional LLM call.
- `research/python-seo-analyzer/pyseoanalyzer/website.py` — crawl queue, sitemap support, base-domain restriction, aggregate counters, duplicate-content hash tracking, and follow-links behavior.
- `research/python-seo-analyzer/pyseoanalyzer/analyzer.py` — public `analyze()` function, output shape, duplicate page grouping, keyword filtering, total-time calculation, and CLI-friendly JSON result construction.
- `research/python-seo-analyzer/pyseoanalyzer/http.py` — small HTTP wrapper around urllib3.
- `research/python-seo-analyzer/pyseoanalyzer/__main__.py` — CLI argument parsing and output behavior.
- `research/python-seo-analyzer/pyseoanalyzer/llm_analyst.py` — optional Anthropic/LangChain structured-output chains for entity, credibility, conversational, platform, and recommendation analysis.
- `research/python-seo-analyzer/tests/*` — mocked tests for analyzer orchestration and page behavior.

## Useful concepts for our visibility-detection project

- **Selector maps as parser configuration.** `HEADING_TAGS_XPATHS` and `ADDITIONAL_TAGS_XPATHS` centralize XPath selectors for headings, title, description, viewport, charset, canonical, alternate/hreflang, and OpenGraph fields (`research/python-seo-analyzer/pyseoanalyzer/page.py`). This is useful for a deterministic PHP `PageParser`.
- **Content extraction plus metadata extraction.** `Page.analyze()` uses Trafilatura metadata/content extraction and BeautifulSoup/lxml for targeted tag checks (`research/python-seo-analyzer/pyseoanalyzer/page.py`). Our parser can combine a DOM parser with explicit selector extraction; we should not require Trafilatura in PHP, but the separation is useful.
- **Duplicate content evidence.** Pages receive a SHA-1 `content_hash`, and `Website`/`analyzer` groups URLs with the same hash into duplicate pages (`research/python-seo-analyzer/pyseoanalyzer/page.py`, `research/python-seo-analyzer/pyseoanalyzer/analyzer.py`). This can later explain why a search result shows a variant URL or why a canonical variant may win.
- **Keyword/n-gram diagnostics.** The analyzer tokenizes page text, filters stop words, and computes keyword, bigram, and trigram frequency (`research/python-seo-analyzer/pyseoanalyzer/page.py`). For products, this can become content evidence for whether query terms appear in title/H1/body/schema.
- **Orchestration test strategy.** Tests patch `Website`, mock crawled pages, and assert output shape, constructor arguments, duplicates, and keyword filtering (`research/python-seo-analyzer/tests/test_analyzer.py`). Our `VisibilityDetector` orchestration tests can use the same fixture/mocking approach.
- **AI analysis as a later seam only.** `llm_analyst.py` defines Pydantic structured outputs for entity, credibility, conversation, platform presence, and recommendations, but it requires external Anthropic/LangChain calls (`research/python-seo-analyzer/pyseoanalyzer/llm_analyst.py`). This aligns with a future optional answer-engine module, not v0.1 core.

## Reusable implementation ideas

- Implement parser selector maps in PHP for title, description, canonical, viewport, robots, hreflang, OpenGraph, and heading extraction, inspired by `ADDITIONAL_TAGS_XPATHS` and `HEADING_TAGS_XPATHS` (`research/python-seo-analyzer/pyseoanalyzer/page.py`).
- Add a content evidence object with normalized text, word count, query-term coverage, and optional content hash, adapting the `Page` text-processing pipeline conceptually (`research/python-seo-analyzer/pyseoanalyzer/page.py`).
- Add duplicate/variant evidence later: if SERP returns a URL variant with the same page hash or canonical target, report it as “variant found” instead of simply “not found” (`research/python-seo-analyzer/pyseoanalyzer/analyzer.py`).
- Structure top-level report assembly separately from crawling/parsing, as `analyzer.analyze()` wraps `Website.crawl()` and converts page objects into output (`research/python-seo-analyzer/pyseoanalyzer/analyzer.py`).
- Use tests that mock providers/fetchers/parsers and assert deterministic report output, following the mocked `Website` tests (`research/python-seo-analyzer/tests/test_analyzer.py`).

## What NOT to reuse

- Do not depend on Trafilatura, BeautifulSoup, lxml, urllib3, LangChain, Anthropic, or Python runtime in the PHP package core (`research/python-seo-analyzer/pyseoanalyzer/page.py`, `research/python-seo-analyzer/pyseoanalyzer/llm_analyst.py`).
- Do not use optional LLM analysis in v0.1. It requires network/API credentials and generates interpretive output rather than deterministic evidence (`research/python-seo-analyzer/pyseoanalyzer/llm_analyst.py`).
- Do not reuse generic SEO keyword-frequency output as a visibility score. Query/product relevance should be evidence-backed and query-specific.
- Do not over-index on duplicate-content warnings; duplicate or hashed-equivalent content is context, not automatically a visibility failure.

## Possible role in our future system

- **SEO analyzer reference** — medium usefulness for metadata/content extraction and warnings.
- **testing/reference implementation** — medium usefulness for mocked orchestration tests and deterministic output assertions.
- **AI visibility / answer-engine reference** — low-to-medium conceptual usefulness through `llm_analyst.py`, but not suitable for deterministic v0.1 core.
- **not a SERP parser reference** — it does not collect or parse external search results.

## Concrete lessons for our roadmap

- Build a selector-map-based parser for title, meta description, robots, canonical, hreflang, OpenGraph, headings, and body text.
- Add query-term coverage evidence for product title, H1, body text, and structured-data fields.
- Add optional content hashing for URL variant and duplicate-content diagnostics after v0.1 URL matching is stable.
- Keep report assembly independent from crawling/parsing so tests can mock `SearchProvider`, `PageFetcher`, and `PageParser`.
- Reserve an explicit future AI-analysis adapter boundary, but keep all v0.1 findings deterministic and fixture-driven.
