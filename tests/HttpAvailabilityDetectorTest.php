<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use VisibilityDetector\Core\Detector\DetectionContext;
use VisibilityDetector\Core\Detector\HttpAvailabilityDetector;
use VisibilityDetector\Core\Page\PageSnapshot;
use VisibilityDetector\Core\Product\ProductSubject;
use VisibilityDetector\Core\Search\SearchQuery;
use VisibilityDetector\Core\Search\SearchResultSet;
use VisibilityDetector\Core\Url\UrlMatch;

final class HttpAvailabilityDetectorTest extends TestCase
{
    public function test_fetch_failure_produces_finding(): void
    {
        $findings = $this->detector()->detect($this->context(
            pageSnapshot: $this->snapshot(failureType: 'timeout'),
        ));

        self::assertSame('page.fetch_failed', $findings[0]->code);
        self::assertSame('timeout', $findings[0]->evidence['pageSnapshot']['failureType']);
    }

    public function test_null_failure_type_does_not_produce_fetch_failed_finding(): void
    {
        $findings = $this->detector()->detect($this->context(
            pageSnapshot: $this->snapshot(failureType: null),
        ));

        self::assertNotContains('page.fetch_failed', $this->codes($findings));
    }

    public function test_non_2xx_http_status_produces_findings(): void
    {
        $findings = $this->detector()->detect($this->context(
            pageSnapshot: $this->snapshot(statusCode: 404),
        ));

        self::assertContains('page.http_error', $this->codes($findings));
        self::assertContains('page.http_status_not_ok', $this->codes($findings));
    }

    public function test_final_url_outside_product_url_set_produces_redirect_finding(): void
    {
        $findings = $this->detector()->detect($this->context(
            pageSnapshot: $this->snapshot(finalUrl: 'https://merchant.test/collections/widgets'),
        ));

        self::assertContains('page.redirects_elsewhere', $this->codes($findings));
    }

    public function test_acceptable_final_url_variant_does_not_produce_redirect_finding(): void
    {
        $findings = $this->detector()->detect($this->context(
            pageSnapshot: $this->snapshot(finalUrl: 'https://merchant.test/widget'),
        ));

        self::assertNotContains('page.redirects_elsewhere', $this->codes($findings));
    }

    public function test_empty_body_produces_finding(): void
    {
        $findings = $this->detector()->detect($this->context(
            pageSnapshot: $this->snapshot(body: ''),
        ));

        self::assertContains('page.empty_body', $this->codes($findings));
    }

    public function test_non_html_content_produces_finding(): void
    {
        $findings = $this->detector()->detect($this->context(
            pageSnapshot: $this->snapshot(contentType: 'application/json'),
        ));

        self::assertContains('page.non_html_content', $this->codes($findings));
    }

    private function detector(): HttpAvailabilityDetector
    {
        return new HttpAvailabilityDetector();
    }

    /**
     * @param array<int, \VisibilityDetector\Core\Report\Finding> $findings
     * @return array<int, string>
     */
    private function codes(array $findings): array
    {
        return array_map(static fn ($finding): string => $finding->code, $findings);
    }

    private function context(?PageSnapshot $pageSnapshot = null): DetectionContext
    {
        $query = new SearchQuery(text: 'widget', provider: 'static');

        return new DetectionContext(
            product: new ProductSubject(
                expectedUrl: 'https://merchant.test/products/widget',
                acceptableUrlVariants: ['https://merchant.test/widget'],
            ),
            query: $query,
            resultSet: new SearchResultSet(query: $query),
            urlMatch: new UrlMatch(
                matched: false,
                matchType: 'none',
                expectedUrl: 'https://merchant.test/products/widget',
            ),
            pageSnapshot: $pageSnapshot,
        );
    }

    private function snapshot(
        ?int $statusCode = 200,
        ?string $body = '<html><body>Widget</body></html>',
        ?string $contentType = 'text/html',
        ?string $failureType = 'none',
        ?string $finalUrl = 'https://merchant.test/products/widget',
    ): PageSnapshot {
        return new PageSnapshot(
            requestedUrl: 'https://merchant.test/products/widget',
            finalUrl: $finalUrl,
            statusCode: $statusCode,
            body: $body,
            contentType: $contentType,
            failureType: $failureType,
        );
    }
}
