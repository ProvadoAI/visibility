# semantic-fashion-search

## What this project actually does

The checked-out `semantic-fashion-search` directory does not contain the application source code for the referenced system. It contains only `.gitmodules`, `README.md`, and `architecture.png`; the actual components are listed as submodules but are not present in the clone (`research/semantic-fashion-search/.gitmodules`). Because the task requires source-code-based analysis, this repository cannot be treated as evidence for implementation details beyond the fact that it is intended to be composed from separate submodule repositories.

The `.gitmodules` file names five missing components: `hybrid-search-engine-api`, `medusa-plugin-customsearch`, `medusa-store`, `medusa-storefront`, and `medusa-admin` (`research/semantic-fashion-search/.gitmodules`). Without those directories, there are no source files to inspect for query matching, indexing, embedding, storefront integration, APIs, tests, or ranking behavior.

## Relevant source-code areas inspected

- `research/semantic-fashion-search/.gitmodules` — inspected to identify the intended submodule layout and confirm source components are absent.
- `research/semantic-fashion-search/README.md` — inspected only to understand repository intent; not used as evidence for implementation claims.
- `research/semantic-fashion-search/architecture.png` — present as an architecture asset, but not source code.
- Directory inventory under `research/semantic-fashion-search/` — confirmed no `hybrid-search-engine-api`, `medusa-plugin-customsearch`, `medusa-store`, `medusa-storefront`, or `medusa-admin` source directories are checked in.

## Useful concepts for our visibility-detection project

- **Modular expected-query/search architecture is only implied, not inspectable.** The missing submodule names suggest separation between a hybrid search API, ecommerce plugin, store backend, storefront, and admin UI (`research/semantic-fashion-search/.gitmodules`). This is conceptually aligned with keeping our core package separate from Laravel/ecommerce adapters, but there is no source code to validate patterns.
- **Semantic product-query matching remains a future seam.** The repository name and submodule names suggest semantic fashion search, but no implementation is available. This reinforces the existing project decision to reserve a seam such as `ProductQueryMatcher` or `QueryExpectationProvider` while keeping embeddings/vector infrastructure out of v0.1.

## Reusable implementation ideas

- Use only the high-level modularity lesson: keep query expectation/matching, ecommerce adapters, storefront integrations, and admin/reporting UI outside the deterministic core.
- If this research area is revisited, clone/update the missing submodules and inspect their actual API, indexing, ranking, data model, test, and adapter code before using them for roadmap decisions.

## What NOT to reuse

- Do not cite this repository as a source-code reference for embeddings, hybrid retrieval, ranking, query expansion, Medusa integration, or AI visibility; those components are not present in the checked-out source tree.
- Do not add semantic search, vector databases, model providers, or ecommerce-specific adapters to v0.1 based on this incomplete checkout.
- Do not rely on `README.md` or `architecture.png` for implementation detail.

## Possible role in our future system

- **not useful** for current source-code research, because implementation source is absent.
- **testing/reference implementation** only after the missing submodules are checked out and inspected.
- **AI visibility / expected-query conceptual reference** at a very low level, limited to the idea of separating search API/plugin/storefront/admin components.

## Concrete lessons for our roadmap

- Add a future research task to fetch and inspect the missing submodules before designing semantic product-query matching.
- Keep semantic matching as a separate future module, not part of v0.1 core.
- Define an interface seam such as `QueryExpectationProvider` or `ProductQueryMatcher` without implementing embeddings or vector storage.
- Document when a research reference is incomplete so roadmap decisions remain source-code-based.
