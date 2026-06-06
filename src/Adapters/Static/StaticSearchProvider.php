<?php

declare(strict_types=1);

namespace VisibilityDetector\Adapters\Static;

use InvalidArgumentException;
use VisibilityDetector\Core\Product\ProductSubject;
use VisibilityDetector\Core\Search\SearchProvider;
use VisibilityDetector\Core\Search\SearchQuery;
use VisibilityDetector\Core\Search\SearchResultSet;

final class StaticSearchProvider implements SearchProvider
{
    /** @var array<string, SearchResultSet> */
    private array $resultSetsByQueryContext = [];

    /**
     * @param array<int, SearchResultSet|array<string, mixed>> $resultSets
     */
    public function __construct(array $resultSets = [])
    {
        foreach ($resultSets as $resultSet) {
            if (is_array($resultSet)) {
                $resultSet = SearchResultSet::fromArray($resultSet);
            }

            if (!$resultSet instanceof SearchResultSet) {
                throw new InvalidArgumentException('resultSets must contain only SearchResultSet objects or arrays.');
            }

            $this->resultSetsByQueryContext[$this->keyForQuery($resultSet->query)] = $resultSet;
        }
    }

    /**
     * @param array<int, SearchQuery> $queries
     * @return array<int, SearchResultSet>
     */
    public function search(ProductSubject $product, array $queries): array
    {
        $resultSets = [];

        foreach ($queries as $query) {
            if (!$query instanceof SearchQuery) {
                throw new InvalidArgumentException('queries must contain only SearchQuery objects.');
            }

            $resultSets[] = $this->resultSetsByQueryContext[$this->keyForQuery($query)]
                ?? new SearchResultSet(query: $query);
        }

        return $resultSets;
    }

    private function keyForQuery(SearchQuery $query): string
    {
        return implode("\n", [
            $query->text,
            $query->provider,
            $query->locale ?? '',
            $query->device ?? '',
        ]);
    }
}
