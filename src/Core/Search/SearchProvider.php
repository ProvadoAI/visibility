<?php

declare(strict_types=1);

namespace VisibilityDetector\Core\Search;

use VisibilityDetector\Core\Product\ProductSubject;

interface SearchProvider
{
    /**
     * @param array<int, SearchQuery> $queries
     * @return array<int, SearchResultSet>
     */
    public function search(ProductSubject $product, array $queries): array;
}
