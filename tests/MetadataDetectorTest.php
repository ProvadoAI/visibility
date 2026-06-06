<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use VisibilityDetector\Core\Detector\DetectionContext;
use VisibilityDetector\Core\Detector\MetadataDetector;
use VisibilityDetector\Core\Page\ParsedPage;
use VisibilityDetector\Core\Product\ProductSubject;
use VisibilityDetector\Core\Search\SearchQuery;
use VisibilityDetector\Core\Search\SearchResultSet;
use VisibilityDetector\Core\Url\UrlMatch;

final class MetadataDetectorTest extends TestCase
{
    public function test_missing_title_produces_finding(): void
    {
        $findings = (new MetadataDetector())->detect($this->context(
            parsedPage: $this->parsedPage(title: null),
        ));

        self::assertContains('page.title_missing', $this->codes($findings));
    }

    public function test_missing_meta_description_produces_finding(): void
    {
        $findings = (new MetadataDetector())->detect($this->context(
            parsedPage: $this->parsedPage(metaDescription: null),
        ));

        self::assertContains('page.meta_description_missing', $this->codes($findings));
    }

    public function test_missing_h1_produces_finding(): void
    {
        $findings = (new MetadataDetector())->detect($this->context(
            parsedPage: $this->parsedPage(h1: null),
        ));

        self::assertContains('page.h1_missing', $this->codes($findings));
    }

    public function test_missing_product_schema_produces_finding(): void
    {
        $findings = (new MetadataDetector())->detect($this->context(
            parsedPage: $this->parsedPage(productSchemaCandidates: []),
        ));

        self::assertContains('page.product_schema_missing', $this->codes($findings));
    }

    public function test_missing_offer_schema_produces_finding(): void
    {
        $findings = (new MetadataDetector())->detect($this->context(
            parsedPage: $this->parsedPage(offerSchemaCandidates: []),
        ));

        self::assertContains('page.offer_schema_missing', $this->codes($findings));
    }

    public function test_expected_terms_missing_produces_finding(): void
    {
        $findings = (new MetadataDetector())->detect($this->context(
            expectedTerms: ['Widget', 'blue'],
            parsedPage: $this->parsedPage(title: 'Widget', bodyTextSummary: 'Durable product for kitchens.'),
        ));

        self::assertContains('page.expected_terms_missing', $this->codes($findings));
        self::assertSame(['blue'], $this->findingByCode($findings, 'page.expected_terms_missing')->evidence['missingTerms']);
    }

    public function test_expected_terms_present_does_not_emit_missing_finding(): void
    {
        $findings = (new MetadataDetector())->detect($this->context(
            expectedTerms: ['Widget', 'blue', 'shipping'],
            parsedPage: $this->parsedPage(
                title: 'Widget',
                metaDescription: 'Blue product details',
                headings: [['level' => 2, 'text' => 'Shipping details']],
                bodyTextSummary: 'Durable product for kitchens.',
            ),
        ));

        self::assertNotContains('page.expected_terms_missing', $this->codes($findings));
    }

    public function test_missing_parsed_page_produces_metadata_uncertain(): void
    {
        $findings = (new MetadataDetector())->detect($this->context(parsedPage: null));

        self::assertSame('page.metadata_uncertain', $findings[0]->code);
    }

    /**
     * @param array<int, \VisibilityDetector\Core\Report\Finding> $findings
     * @return array<int, string>
     */
    private function codes(array $findings): array
    {
        return array_map(static fn ($finding): string => $finding->code, $findings);
    }

    /**
     * @param array<int, \VisibilityDetector\Core\Report\Finding> $findings
     */
    private function findingByCode(array $findings, string $code): object
    {
        foreach ($findings as $finding) {
            if ($finding->code === $code) {
                return $finding;
            }
        }

        self::fail('Finding was not emitted: ' . $code);
    }

    private function context(array $expectedTerms = [], ?ParsedPage $parsedPage = null): DetectionContext
    {
        $query = new SearchQuery(text: 'widget', provider: 'static');

        return new DetectionContext(
            product: new ProductSubject(
                expectedUrl: 'https://merchant.test/products/widget',
                expectedTerms: $expectedTerms,
            ),
            query: $query,
            resultSet: new SearchResultSet(query: $query),
            urlMatch: new UrlMatch(
                matched: false,
                matchType: 'none',
                expectedUrl: 'https://merchant.test/products/widget',
            ),
            parsedPage: $parsedPage,
        );
    }

    private function parsedPage(
        ?string $title = 'Widget title',
        ?string $metaDescription = 'Widget description',
        ?string $h1 = 'Widget H1',
        array $headings = [['level' => 2, 'text' => 'Widget details']],
        array $productSchemaCandidates = [['@type' => 'Product', 'name' => 'Widget']],
        array $offerSchemaCandidates = [['@type' => 'Offer', 'price' => '19.99']],
        ?string $bodyTextSummary = 'Widget content summary',
    ): ParsedPage {
        return new ParsedPage(
            url: 'https://merchant.test/products/widget',
            title: $title,
            metaDescription: $metaDescription,
            h1: $h1,
            headings: $headings,
            productSchemaCandidates: $productSchemaCandidates,
            offerSchemaCandidates: $offerSchemaCandidates,
            bodyTextSummary: $bodyTextSummary,
        );
    }
}
