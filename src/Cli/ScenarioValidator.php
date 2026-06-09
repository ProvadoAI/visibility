<?php

declare(strict_types=1);

namespace VisibilityDetector\Cli;

use InvalidArgumentException;
use VisibilityDetector\Core\Search\SearchQuery;
use VisibilityDetector\Core\Search\SearchResultSet;

final readonly class ScenarioValidator
{
    /**
     * @param array<string, mixed> $payload
     */
    public function validatePayload(array $payload): void
    {
        $this->validateProductPayload($payload);
        $this->validateQueriesPayload($payload);
        $this->validateSearchEvidencePayload($payload);
        $this->validatePageFixturesPayload($payload);
    }

    public function validateScenario(Scenario $scenario): void
    {
        $this->validateSearchEvidenceMatchesQueries($scenario->queries, $scenario->searchResultSets);
        $this->validatePageEvidenceMatchesProduct($scenario);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function validateProductPayload(array $payload): void
    {
        if (!isset($payload['product']) || !is_array($payload['product'])) {
            throw new InvalidArgumentException('Scenario product must be an object.');
        }

        if (!array_key_exists('expectedUrl', $payload['product']) || !is_string($payload['product']['expectedUrl']) || trim($payload['product']['expectedUrl']) === '') {
            throw new InvalidArgumentException('Scenario product expectedUrl is required and must be a non-empty string.');
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function validateQueriesPayload(array $payload): void
    {
        if (!isset($payload['queries']) || !is_array($payload['queries']) || $payload['queries'] === []) {
            throw new InvalidArgumentException('Scenario queries must be a non-empty array.');
        }

        foreach ($payload['queries'] as $index => $query) {
            if (!is_array($query)) {
                throw new InvalidArgumentException('Scenario queries must contain only objects.');
            }

            if (!array_key_exists('text', $query) || !is_string($query['text']) || trim($query['text']) === '') {
                throw new InvalidArgumentException('Scenario queries[' . $index . '] text is required and must be a non-empty string.');
            }

            if (!array_key_exists('provider', $query) || !is_string($query['provider']) || trim($query['provider']) === '') {
                throw new InvalidArgumentException('Scenario queries[' . $index . '] provider is required and must be a non-empty string.');
            }
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function validateSearchEvidencePayload(array $payload): void
    {
        $searchResultFixtures = $payload['searchResultFixtures'] ?? [];
        $searchResults = $payload['searchResults'] ?? [];

        if (!is_array($searchResultFixtures)) {
            throw new InvalidArgumentException('searchResultFixtures must be an array.');
        }

        if (!is_array($searchResults)) {
            throw new InvalidArgumentException('searchResults must be an array.');
        }

        if ($searchResultFixtures === [] && $searchResults === []) {
            throw new InvalidArgumentException('Scenario must define searchResultFixtures or searchResults.');
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function validatePageFixturesPayload(array $payload): void
    {
        if (!isset($payload['pageFixtures']) || !is_array($payload['pageFixtures']) || $payload['pageFixtures'] === []) {
            throw new InvalidArgumentException('Scenario pageFixtures must be a non-empty array.');
        }

        foreach ($payload['pageFixtures'] as $index => $pageFixture) {
            if (!is_array($pageFixture)) {
                throw new InvalidArgumentException('Scenario pageFixtures[' . $index . '] must be an object.');
            }

            if (!array_key_exists('requestedUrl', $pageFixture) || !is_string($pageFixture['requestedUrl']) || trim($pageFixture['requestedUrl']) === '') {
                throw new InvalidArgumentException('Scenario pageFixtures[' . $index . '] requestedUrl is required and must be a non-empty string.');
            }

            $hasHtmlFixture = array_key_exists('htmlFixture', $pageFixture);
            $hasBody = array_key_exists('body', $pageFixture);

            if (!$hasHtmlFixture && !$hasBody) {
                throw new InvalidArgumentException('Scenario pageFixtures[' . $index . '] must define htmlFixture or body.');
            }

            if ($hasHtmlFixture && (!is_string($pageFixture['htmlFixture']) || trim($pageFixture['htmlFixture']) === '')) {
                throw new InvalidArgumentException('Scenario pageFixtures[' . $index . '] htmlFixture must be a non-empty string.');
            }

            if ($hasBody && $pageFixture['body'] !== null && !is_string($pageFixture['body'])) {
                throw new InvalidArgumentException('Scenario pageFixtures[' . $index . '] body must be a string when provided.');
            }
        }
    }

    /**
     * @param array<int, SearchQuery> $queries
     * @param array<int, SearchResultSet> $resultSets
     */
    private function validateSearchEvidenceMatchesQueries(array $queries, array $resultSets): void
    {
        $queryKeys = [];

        foreach ($queries as $query) {
            $queryKeys[$this->keyForQuery($query)] = $query;
        }

        $evidenceCounts = [];

        foreach ($resultSets as $resultSet) {
            $key = $this->keyForQuery($resultSet->query);

            if (!array_key_exists($key, $queryKeys)) {
                throw new InvalidArgumentException('Search result evidence query/provider does not match any scenario query: ' . $this->describeQuery($resultSet->query));
            }

            $evidenceCounts[$key] = ($evidenceCounts[$key] ?? 0) + 1;

            if ($evidenceCounts[$key] > 1) {
                throw new InvalidArgumentException('Ambiguous duplicate search result evidence for scenario query: ' . $this->describeQuery($resultSet->query));
            }
        }

        foreach ($queries as $query) {
            $key = $this->keyForQuery($query);

            if (($evidenceCounts[$key] ?? 0) === 0) {
                throw new InvalidArgumentException('Missing search result evidence for scenario query: ' . $this->describeQuery($query));
            }
        }
    }

    private function validatePageEvidenceMatchesProduct(Scenario $scenario): void
    {
        foreach ($scenario->pageSnapshots as $snapshot) {
            if ($snapshot->requestedUrl === $scenario->product->expectedUrl) {
                return;
            }
        }

        throw new InvalidArgumentException('Missing page fixture evidence for product expectedUrl: ' . $scenario->product->expectedUrl);
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

    private function describeQuery(SearchQuery $query): string
    {
        return 'text="' . $query->text . '", provider="' . $query->provider . '", locale="' . ($query->locale ?? '') . '", device="' . ($query->device ?? '') . '"';
    }
}
