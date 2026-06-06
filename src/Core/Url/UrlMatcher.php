<?php

declare(strict_types=1);

namespace VisibilityDetector\Core\Url;

use VisibilityDetector\Core\Product\ProductSubject;
use VisibilityDetector\Core\Search\SearchResultSet;

interface UrlMatcher
{
    public function match(ProductSubject $product, SearchResultSet $resultSet): UrlMatch;
}
