<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use VisibilityDetector\Core\Detector\DetectionContext;
use VisibilityDetector\Core\Detector\IndexabilityDetector;
use VisibilityDetector\Core\Page\PageSnapshot;
use VisibilityDetector\Core\Page\ParsedPage;
use VisibilityDetector\Core\Product\ProductSubject;
use VisibilityDetector\Core\Search\SearchQuery;
use VisibilityDetector\Core\Search\SearchResultSet;
use VisibilityDetector\Core\Url\UrlMatch;

final class IndexabilityDetectorTest extends TestCase
{
    public function test_fetch_failure_produces_finding(): void
    {
        $findings = (new IndexabilityDetector())->detect($this->context(
            pageSnapshot: $this->snapshot(failureType: 'timeout'),
        ));

        self::assertSame('page.fetch_failed', $findings[0]->code);
        self::assertSame('timeout', $findings[0]->evidence['pageSnapshot']['failureType']);
    }

    public function test_non_2xx_http_status_produces_finding(): void
    {
        $findings = (new IndexabilityDetector())->detect($this->context(
            pageSnapshot: $this->snapshot(statusCode: 404),
        ));

        self::assertContains('page.http_status_not_ok', $this->codes($findings));
    }

    public function test_empty_body_produces_finding(): void
    {
        $findings = (new IndexabilityDetector())->detect($this->context(
            pageSnapshot: $this->snapshot(body: ''),
        ));

        self::assertContains('page.empty_body', $this->codes($findings));
    }

    public function test_non_html_content_produces_finding(): void
    {
        $findings = (new IndexabilityDetector())->detect($this->context(
            pageSnapshot: $this->snapshot(contentType: 'application/json'),
        ));

        self::assertContains('page.non_html_content', $this->codes($findings));
    }

    public function test_meta_noindex_produces_finding(): void
    {
        $findings = (new IndexabilityDetector())->detect($this->context(
            parsedPage: $this->parsedPage(robotsDirectives: ['index', 'noindex']),
        ));

        self::assertSame('page.noindex_meta', $findings[0]->code);
    }

    public function test_x_robots_noindex_produces_finding(): void
    {
        $findings = (new IndexabilityDetector())->detect($this->context(
            parsedPage: $this->parsedPage(xRobotsDirectives: ['noindex']),
        ));

        self::assertSame('page.noindex_x_robots', $findings[0]->code);
    }

    public function test_canonical_mismatch_produces_finding(): void
    {
        $findings = (new IndexabilityDetector())->detect($this->context(
            parsedPage: $this->parsedPage(canonicalUrl: 'https://merchant.test/products/other-widget'),
        ));

        self::assertSame('page.canonical_mismatch', $findings[0]->code);
        self::assertSame('https://merchant.test/products/other-widget', $findings[0]->evidence['canonicalUrl']);
    }

    public function test_no_page_evidence_produces_uncertain(): void
    {
        $findings = (new IndexabilityDetector())->detect($this->context());

        self::assertSame('page.indexability_uncertain', $findings[0]->code);
    }

    /**
     * @param array<int, \VisibilityDetector\Core\Report\Finding> $findings
     * @return array<int, string>
     */
    private function codes(array $findings): array
    {
        return array_map(static fn ($finding): string => $finding->code, $findings);
    }

    private function context(?PageSnapshot $pageSnapshot = null, ?ParsedPage $parsedPage = null): DetectionContext
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
            parsedPage: $parsedPage,
        );
    }

    private function snapshot(
        ?int $statusCode = 200,
        ?string $body = '<html><body>Widget</body></html>',
        ?string $contentType = 'text/html',
        string $failureType = 'none',
    ): PageSnapshot {
        return new PageSnapshot(
            requestedUrl: 'https://merchant.test/products/widget',
            finalUrl: 'https://merchant.test/products/widget',
            statusCode: $statusCode,
            body: $body,
            contentType: $contentType,
            failureType: $failureType,
        );
    }

    private function parsedPage(
        ?string $canonicalUrl = null,
        array $robotsDirectives = [],
        array $xRobotsDirectives = [],
    ): ParsedPage {
        return new ParsedPage(
            url: 'https://merchant.test/products/widget',
            canonicalUrl: $canonicalUrl,
            robotsDirectives: $robotsDirectives,
            xRobotsDirectives: $xRobotsDirectives,
        );
    }
}
