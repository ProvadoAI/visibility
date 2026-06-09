<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use VisibilityDetector\Adapters\Static\FixturePageFetcher;
use VisibilityDetector\Adapters\Static\StaticSearchProvider;
use VisibilityDetector\Cli\ScenarioLoader;
use VisibilityDetector\Core\Analyzer\VisibilityAnalyzer;
use VisibilityDetector\Core\Detector\VisibilityResultDetector;
use VisibilityDetector\Core\Page\DomPageParser;
use VisibilityDetector\Core\Report\CompactJsonReportSerializer;
use VisibilityDetector\Core\Report\JsonReportSerializer;
use VisibilityDetector\Core\Url\DefaultUrlMatcher;

final class DiagnosticSummaryProjectorTest extends TestCase
{
    public function test_visible_clean_full_json_has_healthy_diagnostic_summary_without_failure_language(): void
    {
        $payload = $this->fullPayloadForScenario('visible-clean');
        $diagnosticSummary = $payload['diagnosticSummary'];

        self::assertSame('Visible and healthy', $diagnosticSummary['title']);
        self::assertSame('The product was visible in the supplied search evidence. No blocking technical visibility issue was detected from the supplied page evidence.', $diagnosticSummary['statusExplanation']);
        self::assertNull($diagnosticSummary['primaryIssue']);
        self::assertSame('A shopper using the supplied evidence could see the expected product result.', $diagnosticSummary['merchantExplanation']);
        self::assertSame('visible', $payload['summary']['overallStatus']);
        self::assertSame('healthy', $payload['queryVisibilities'][0]['visibilityHealth']);
        self::assertArrayHasKey('queryVisibilities', $payload);
        self::assertArrayHasKey('pageSnapshot', $payload);
        self::assertArrayHasKey('parsedPage', $payload);
        self::assertArrayHasKey('summaryFindings', $payload);
        self::assertStringNotContainsString('not visible', strtolower($diagnosticSummary['statusExplanation']));
        self::assertStringNotContainsString('absent', strtolower($diagnosticSummary['merchantExplanation']));
    }

    public function test_not_visible_summary_states_expected_product_is_absent_and_uses_existing_recommendation(): void
    {
        $payload = $this->fullPayloadForScenario('not-visible');
        $diagnosticSummary = $payload['diagnosticSummary'];

        self::assertSame('Product not visible', $diagnosticSummary['title']);
        self::assertStringContainsString('absent from the supplied search results', $diagnosticSummary['statusExplanation']);
        self::assertSame($payload['summary']['topProbableCauses'][0]['code'], $diagnosticSummary['primaryIssue']['code']);
        self::assertContains($diagnosticSummary['primaryIssue']['code'], ['product.not_found_in_results', 'query.expected_visibility_missing']);
        self::assertSame($payload['summary']['topRecommendedActions'][0]['action'], $diagnosticSummary['recommendedNextStep']['action']);
        self::assertSame($diagnosticSummary['primaryIssue']['code'], $diagnosticSummary['evidenceHighlights'][0]['topFindingCode']);
        self::assertSame('not_visible', $payload['queryVisibilities'][0]['status']);
    }

    public function test_visible_noindex_summary_states_visibility_exists_but_technical_blocker_remains(): void
    {
        $payload = $this->fullPayloadForScenario('visible-noindex');
        $diagnosticSummary = $payload['diagnosticSummary'];

        self::assertSame('Visible with technical blocker', $diagnosticSummary['title']);
        self::assertStringContainsString('appeared in the supplied search evidence', $diagnosticSummary['statusExplanation']);
        self::assertStringContainsString('technical blocker', $diagnosticSummary['statusExplanation']);
        self::assertSame('page.noindex_meta', $diagnosticSummary['primaryIssue']['code']);
        self::assertSame('blocked', $payload['queryVisibilities'][0]['visibilityHealth']);
        self::assertSame(['blocked'], $diagnosticSummary['evidenceHighlights'][0]['visibilityHealth']);
    }

    public function test_visible_canonical_mismatch_summary_uses_top_canonical_finding(): void
    {
        $payload = $this->fullPayloadForScenario('visible-canonical-mismatch');
        $diagnosticSummary = $payload['diagnosticSummary'];

        self::assertSame('Visible with technical risk', $diagnosticSummary['title']);
        self::assertStringContainsString('technical risk', $diagnosticSummary['statusExplanation']);
        self::assertSame('canonical.points_to_other_url', $diagnosticSummary['primaryIssue']['code']);
        self::assertSame($payload['urlEvidence']['canonicalUrl'], $diagnosticSummary['evidenceHighlights'][0]['canonicalUrl']);
        self::assertSame($payload['summary']['topProbableCauses'][0]['code'], $diagnosticSummary['evidenceHighlights'][0]['topFindingCode']);
    }

    public function test_visible_missing_schema_summary_uses_structured_data_finding(): void
    {
        $payload = $this->fullPayloadForScenario('visible-missing-schema');
        $diagnosticSummary = $payload['diagnosticSummary'];

        self::assertSame('Visible with technical risk', $diagnosticSummary['title']);
        self::assertSame('schema.product_missing', $diagnosticSummary['primaryIssue']['code']);
        self::assertSame('Add schema.org Product structured data for the product page.', $diagnosticSummary['recommendedNextStep']['action']);
        self::assertSame($payload['urlEvidence']['expectedUrl'], $diagnosticSummary['evidenceHighlights'][0]['expectedUrl']);
        self::assertSame($payload['urlEvidence']['matchedUrls'], $diagnosticSummary['evidenceHighlights'][0]['matchedUrls']);
    }

    public function test_visible_http_error_summary_uses_phase_one_transport_blocker(): void
    {
        $payload = $this->fullPayloadForScenario('visible-http-error');
        $diagnosticSummary = $payload['diagnosticSummary'];

        self::assertSame('Visible with technical blocker', $diagnosticSummary['title']);
        self::assertStringContainsString('technical blocker', $diagnosticSummary['merchantExplanation']);
        self::assertContains($diagnosticSummary['primaryIssue']['code'], ['page.http_status_not_ok', 'page.http_error']);
        self::assertSame('blocked', $payload['queryVisibilities'][0]['visibilityHealth']);
        self::assertSame($payload['pageSnapshot']['finalUrl'], $diagnosticSummary['evidenceHighlights'][0]['finalUrl']);
    }

    public function test_compact_json_safely_omits_diagnostic_summary(): void
    {
        $report = $this->reportForScenario('visible-clean');
        $json = (new CompactJsonReportSerializer())->serialize($report, new DateTimeImmutable('2026-06-09T00:00:00+00:00'));
        $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        self::assertArrayNotHasKey('diagnosticSummary', $payload);
    }

    /**
     * @return array<string, mixed>
     */
    private function fullPayloadForScenario(string $scenarioName): array
    {
        $json = (new JsonReportSerializer())->serialize(
            $this->reportForScenario($scenarioName),
            new DateTimeImmutable('2026-06-09T00:00:00+00:00'),
        );
        $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        self::assertArrayHasKey('diagnosticSummary', $payload);

        return $payload;
    }

    private function reportForScenario(string $scenarioName): VisibilityDetector\Core\Report\VisibilityReport
    {
        $root = dirname(__DIR__);
        $scenario = (new ScenarioLoader($root))->load($root . '/examples/scenarios/' . $scenarioName . '.json');

        return (new VisibilityAnalyzer(
            searchProvider: new StaticSearchProvider($scenario->searchResultSets),
            urlMatcher: new DefaultUrlMatcher(),
            visibilityResultDetector: new VisibilityResultDetector(),
            pageFetcher: new FixturePageFetcher($scenario->pageSnapshots),
            pageParser: new DomPageParser(),
        ))->analyze($scenario->product, $scenario->queries);
    }
}
