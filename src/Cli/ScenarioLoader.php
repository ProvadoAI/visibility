<?php

declare(strict_types=1);

namespace VisibilityDetector\Cli;

use InvalidArgumentException;
use JsonException;
use RuntimeException;
use VisibilityDetector\Core\Page\PageSnapshot;
use VisibilityDetector\Core\Product\ProductSubject;
use VisibilityDetector\Core\Search\SearchQuery;
use VisibilityDetector\Core\Search\SearchResultSet;

final readonly class ScenarioLoader
{
    public function __construct(
        private string $projectRoot,
    ) {
    }

    public function load(string $scenarioPath): Scenario
    {
        $resolvedScenarioPath = $this->resolveExistingPath($scenarioPath, getcwd() ?: $this->projectRoot, 'Scenario file');
        $scenarioDirectory = dirname($resolvedScenarioPath);
        $payload = $this->decodeJsonFile($resolvedScenarioPath, 'Scenario file');

        if (!isset($payload['product']) || !is_array($payload['product'])) {
            throw new InvalidArgumentException('Scenario product must be an object.');
        }

        if (!isset($payload['queries']) || !is_array($payload['queries']) || $payload['queries'] === []) {
            throw new InvalidArgumentException('Scenario queries must be a non-empty array.');
        }

        $product = ProductSubject::fromArray($payload['product']);
        $queries = array_map(
            static fn (mixed $query): SearchQuery => is_array($query) ? SearchQuery::fromArray($query) : throw new InvalidArgumentException('Scenario queries must contain only objects.'),
            $payload['queries'],
        );

        return new Scenario(
            product: $product,
            queries: $queries,
            searchResultSets: $this->loadSearchResultSets($payload, $scenarioDirectory),
            pageSnapshots: $this->loadPageSnapshots($payload, $scenarioDirectory),
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<int, SearchResultSet>
     */
    private function loadSearchResultSets(array $payload, string $scenarioDirectory): array
    {
        $resultSets = [];
        $searchResultFixtures = $payload['searchResultFixtures'] ?? [];

        if (!is_array($searchResultFixtures)) {
            throw new InvalidArgumentException('searchResultFixtures must be an array.');
        }

        foreach ($searchResultFixtures as $fixturePath) {
            if (!is_string($fixturePath) || trim($fixturePath) === '') {
                throw new InvalidArgumentException('searchResultFixtures must contain only non-empty strings.');
            }

            $resolvedFixturePath = $this->resolveExistingPath($fixturePath, $scenarioDirectory, 'Search result fixture');
            $fixturePayload = $this->decodeJsonFile($resolvedFixturePath, 'Search result fixture');

            if ($this->isList($fixturePayload)) {
                foreach ($fixturePayload as $fixtureResultSet) {
                    if (!is_array($fixtureResultSet)) {
                        throw new InvalidArgumentException('Search result fixture arrays must contain only result-set objects.');
                    }

                    $resultSets[] = SearchResultSet::fromArray($fixtureResultSet);
                }
            } else {
                $resultSets[] = SearchResultSet::fromArray($fixturePayload);
            }
        }

        $searchResults = $payload['searchResults'] ?? [];

        if (!is_array($searchResults)) {
            throw new InvalidArgumentException('searchResults must be an array.');
        }

        foreach ($searchResults as $inlineResultSet) {
            if (!is_array($inlineResultSet)) {
                throw new InvalidArgumentException('searchResults must contain only result-set objects.');
            }

            $resultSets[] = SearchResultSet::fromArray($inlineResultSet);
        }

        if ($resultSets === []) {
            throw new InvalidArgumentException('Scenario must define searchResultFixtures or searchResults.');
        }

        return $resultSets;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<int, PageSnapshot>
     */
    private function loadPageSnapshots(array $payload, string $scenarioDirectory): array
    {
        if (!isset($payload['pageFixtures']) || !is_array($payload['pageFixtures']) || $payload['pageFixtures'] === []) {
            throw new InvalidArgumentException('Scenario pageFixtures must be a non-empty array.');
        }

        $snapshots = [];

        foreach ($payload['pageFixtures'] as $pageFixture) {
            if (!is_array($pageFixture)) {
                throw new InvalidArgumentException('pageFixtures must contain only objects.');
            }

            if (array_key_exists('htmlFixture', $pageFixture)) {
                if (!is_string($pageFixture['htmlFixture']) || trim($pageFixture['htmlFixture']) === '') {
                    throw new InvalidArgumentException('pageFixtures htmlFixture must be a non-empty string.');
                }

                $htmlFixturePath = $this->resolveExistingPath($pageFixture['htmlFixture'], $scenarioDirectory, 'Page HTML fixture');
                $body = file_get_contents($htmlFixturePath);

                if (!is_string($body)) {
                    throw new RuntimeException('Page HTML fixture could not be read: ' . $pageFixture['htmlFixture']);
                }

                $pageFixture['body'] = $body;
                unset($pageFixture['htmlFixture']);
            } elseif (!array_key_exists('body', $pageFixture)) {
                throw new InvalidArgumentException('pageFixtures must define htmlFixture or body.');
            }

            if (!array_key_exists('headers', $pageFixture) && isset($pageFixture['contentType']) && is_string($pageFixture['contentType'])) {
                $pageFixture['headers'] = ['content-type' => [$pageFixture['contentType']]];
            }

            $snapshots[] = PageSnapshot::fromArray($pageFixture);
        }

        return $snapshots;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonFile(string $path, string $label): array
    {
        $json = file_get_contents($path);

        if (!is_string($json)) {
            throw new RuntimeException($label . ' could not be read: ' . $path);
        }

        try {
            $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException($label . ' contains invalid JSON: ' . $exception->getMessage(), 0, $exception);
        }

        if (!is_array($payload)) {
            throw new InvalidArgumentException($label . ' must contain a JSON object or array.');
        }

        return $payload;
    }

    private function resolveExistingPath(string $path, string $baseDirectory, string $label): string
    {
        $candidates = [];

        if ($this->isAbsolutePath($path)) {
            $candidates[] = $path;
        } else {
            $candidates[] = $baseDirectory . DIRECTORY_SEPARATOR . $path;
            $candidates[] = $this->projectRoot . DIRECTORY_SEPARATOR . $path;
            $candidates[] = (getcwd() ?: $this->projectRoot) . DIRECTORY_SEPARATOR . $path;
        }

        foreach (array_unique($candidates) as $candidate) {
            if (is_file($candidate)) {
                $resolvedPath = realpath($candidate);

                return is_string($resolvedPath) ? $resolvedPath : $candidate;
            }
        }

        throw new RuntimeException($label . ' does not exist: ' . $path);
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
    }

    /**
     * @param array<mixed> $value
     */
    private function isList(array $value): bool
    {
        return array_is_list($value);
    }
}
