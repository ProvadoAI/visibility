<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use VisibilityDetector\Adapters\Static\StaticSearchProvider;
use VisibilityDetector\Core\Product\ProductSubject;
use VisibilityDetector\Core\Search\SearchProvider;
use VisibilityDetector\Core\Search\SearchQuery;
use VisibilityDetector\Core\Search\SearchResult;
use VisibilityDetector\Core\Search\SearchResultSet;

final class StaticSearchProviderTest extends TestCase
{
    public function test_one_query_returns_configured_results(): void
    {
        $query = new SearchQuery(
            text: 'buy widget',
            provider: 'google',
            locale: 'en_US',
            device: 'desktop',
        );
        $configuredResultSet = new SearchResultSet(
            query: $query,
            results: [
                new SearchResult(position: 1, url: 'https://merchant.test/products/widget', title: 'Widget'),
            ],
        );
        $provider = new StaticSearchProvider([$configuredResultSet]);

        $resultSets = $provider->search($this->product(), [$query]);

        self::assertInstanceOf(SearchProvider::class, $provider);
        self::assertSame([$configuredResultSet], $resultSets);
        self::assertSame('https://merchant.test/products/widget', $resultSets[0]->results[0]->url);
    }

    public function test_one_query_with_no_configured_results_returns_empty_result_set(): void
    {
        $query = new SearchQuery(text: 'missing widget', provider: 'google');
        $provider = new StaticSearchProvider();

        $resultSets = $provider->search($this->product(), [$query]);

        self::assertCount(1, $resultSets);
        self::assertSame($query, $resultSets[0]->query);
        self::assertSame([], $resultSets[0]->results);
        self::assertSame([], $resultSets[0]->warnings);
        self::assertSame([], $resultSets[0]->limitations);
    }

    public function test_multiple_queries_return_matching_result_sets_in_query_order(): void
    {
        $desktopQuery = new SearchQuery(
            text: 'buy widget',
            provider: 'google',
            locale: 'en_US',
            device: 'desktop',
        );
        $mobileQuery = new SearchQuery(
            text: 'buy widget',
            provider: 'google',
            locale: 'en_US',
            device: 'mobile',
        );
        $configuredResultSets = [
            new SearchResultSet(
                query: $mobileQuery,
                results: [new SearchResult(position: 1, url: 'https://m.merchant.test/products/widget')],
            ),
            new SearchResultSet(
                query: $desktopQuery,
                results: [new SearchResult(position: 2, url: 'https://merchant.test/products/widget')],
            ),
        ];
        $provider = new StaticSearchProvider($configuredResultSets);

        $resultSets = $provider->search($this->product(), [$desktopQuery, $mobileQuery]);

        self::assertSame($desktopQuery, $resultSets[0]->query);
        self::assertSame('https://merchant.test/products/widget', $resultSets[0]->results[0]->url);
        self::assertSame($mobileQuery, $resultSets[1]->query);
        self::assertSame('https://m.merchant.test/products/widget', $resultSets[1]->results[0]->url);
    }

    public function test_warnings_and_limitations_passthrough_from_configured_arrays(): void
    {
        $query = new SearchQuery(text: 'buy widget', provider: 'google');
        $provider = new StaticSearchProvider([
            [
                'query' => $query,
                'results' => [],
                'warnings' => ['fixture is static'],
                'limitations' => ['local demo evidence only'],
            ],
        ]);

        $resultSets = $provider->search($this->product(), [$query]);

        self::assertSame(['fixture is static'], $resultSets[0]->warnings);
        self::assertSame(['local demo evidence only'], $resultSets[0]->limitations);
    }

    public function test_invalid_query_input_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new StaticSearchProvider())->search($this->product(), ['buy widget']);
    }

    private function product(): ProductSubject
    {
        return new ProductSubject(expectedUrl: 'https://merchant.test/products/widget');
    }
}
