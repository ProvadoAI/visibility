<?php

declare(strict_types=1);

namespace VisibilityDetector\Cli;

use VisibilityDetector\Core\Page\PageSnapshot;
use VisibilityDetector\Core\Product\ProductSubject;
use VisibilityDetector\Core\Search\SearchQuery;
use VisibilityDetector\Core\Search\SearchResultSet;

final readonly class Scenario
{
    /**
     * @param array<int, SearchQuery> $queries
     * @param array<int, SearchResultSet> $searchResultSets
     * @param array<int, PageSnapshot> $pageSnapshots
     */
    public function __construct(
        public ProductSubject $product,
        public array $queries,
        public array $searchResultSets,
        public array $pageSnapshots,
    ) {
    }
}
