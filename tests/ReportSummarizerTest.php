<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use VisibilityDetector\Core\Product\ProductSubject;
use VisibilityDetector\Core\Report\Finding;
use VisibilityDetector\Core\Report\FindingPrioritizer;
use VisibilityDetector\Core\Report\QueryVisibility;
use VisibilityDetector\Core\Report\ReportSummarizer;
use VisibilityDetector\Core\Search\SearchQuery;
use VisibilityDetector\Core\Url\UrlMatch;

final class ReportSummarizerTest extends TestCase
{
    public function test_visible_product_has_low_priority_summary(): void
    {
        $summary = $this->summarizer()->summarize($this->product(), [
            $this->queryVisibility(status: 'visible'),
        ]);

        self::assertSame('visible', $summary->overallStatus);
        self::assertSame('low', $summary->overallPriority);
        self::assertNull($summary->highestPriorityAffectedQuery);
        self::assertSame('product.visible_in_results', $summary->topRecommendedActions[0]['code']);
    }

    public function test_not_visible_expected_query_increases_priority(): void
    {
        $summary = $this->summarizer()->summarize($this->product(), [
            $this->queryVisibility(
                query: $this->query(text: 'buy widget', expectedVisibility: true, priority: 'high'),
                status: 'not_visible',
            ),
        ]);

        self::assertSame('not_visible', $summary->overallStatus);
        self::assertSame('high', $summary->overallPriority);
        self::assertSame('buy widget', $summary->highestPriorityAffectedQuery);
    }

    public function test_critical_product_commercial_priority_increases_overall_priority(): void
    {
        $summary = $this->summarizer()->summarize($this->product(commercialPriority: 'critical'), [
            $this->queryVisibility(
                query: $this->query(expectedVisibility: true),
                status: 'not_visible',
            ),
        ]);

        self::assertSame('critical', $summary->overallPriority);
    }

    public function test_noindex_outranks_weak_content_finding(): void
    {
        $summary = $this->summarizer()->summarize($this->product(), [
            $this->queryVisibility(findings: [
                $this->finding('content.description_too_thin', 'medium'),
                $this->finding('page.noindex_meta', 'medium'),
            ]),
        ]);

        self::assertSame('page.noindex_meta', $summary->topProbableCauses[0]['code']);
        self::assertSame('indexability_blocker', $summary->topProbableCauses[0]['category']);
    }

    public function test_canonical_issue_outranks_missing_description(): void
    {
        $summary = $this->summarizer()->summarize($this->product(), [
            $this->queryVisibility(findings: [
                $this->finding('content.description_missing', 'high'),
                $this->finding('canonical.points_to_other_url', 'medium'),
            ]),
        ]);

        self::assertSame('canonical.points_to_other_url', $summary->topProbableCauses[0]['code']);
        self::assertSame('canonical_blocker', $summary->topProbableCauses[0]['category']);
    }

    public function test_http_fetch_issue_outranks_noindex(): void
    {
        $summary = $this->summarizer()->summarize($this->product(), [
            $this->queryVisibility(findings: [
                $this->finding('page.noindex_meta', 'critical'),
                $this->finding('page.fetch_failed', 'high'),
            ]),
        ]);

        self::assertSame('page.fetch_failed', $summary->topProbableCauses[0]['code']);
        self::assertSame('availability_blocker', $summary->topProbableCauses[0]['category']);
    }

    public function test_structured_data_issue_appears_as_visibility_quality_issue(): void
    {
        $summary = $this->summarizer()->summarize($this->product(), [
            $this->queryVisibility(findings: [
                $this->finding('schema.product_missing', 'medium'),
            ]),
        ]);

        self::assertSame('schema.product_missing', $summary->topProbableCauses[0]['code']);
        self::assertSame('visibility_quality', $summary->topProbableCauses[0]['category']);
    }

    public function test_deterministic_ordering_when_findings_have_equal_priority(): void
    {
        $summary = $this->summarizer()->summarize($this->product(), [
            $this->queryVisibility(findings: [
                $this->finding('content.h1_missing_product_terms', 'medium'),
                $this->finding('content.description_missing', 'medium'),
                $this->finding('content.body_missing_product_terms', 'medium'),
            ]),
        ]);

        self::assertSame([
            'content.body_missing_product_terms',
            'content.description_missing',
            'content.h1_missing_product_terms',
        ], array_column($summary->topProbableCauses, 'code'));
    }

    public function test_summary_includes_top_recommended_actions(): void
    {
        $summary = $this->summarizer()->summarize($this->product(), [
            $this->queryVisibility(findings: [
                $this->finding('canonical.points_to_homepage', 'high', recommendation: 'Point canonical to the product page.'),
            ]),
        ]);

        self::assertSame('canonical.points_to_homepage', $summary->topRecommendedActions[0]['code']);
        self::assertSame('Point canonical to the product page.', $summary->topRecommendedActions[0]['action']);
    }

    public function test_summary_includes_evidence_references(): void
    {
        $summary = $this->summarizer()->summarize($this->product(), [
            $this->queryVisibility(findings: [
                $this->finding('page.http_status_not_ok', 'high', evidence: ['statusCode' => 404]),
            ]),
        ]);

        self::assertSame('page.http_status_not_ok', $summary->evidenceReferences[0]['code']);
        self::assertSame('widget', $summary->evidenceReferences[0]['affectedQuery']);
        self::assertSame(['statusCode' => 404], $summary->evidenceReferences[0]['evidence']);
    }

    public function test_prioritizer_does_not_mutate_findings(): void
    {
        $finding = $this->finding('page.fetch_failed', 'high', evidence: ['failureType' => 'timeout']);
        $before = $finding->toArray();

        (new FindingPrioritizer())->prioritize($finding, $this->queryVisibility(), $this->product());

        self::assertSame($before, $finding->toArray());
    }

    private function summarizer(): ReportSummarizer
    {
        return new ReportSummarizer();
    }

    private function product(?string $commercialPriority = null): ProductSubject
    {
        return new ProductSubject(
            expectedUrl: 'https://merchant.test/products/widget',
            name: 'Widget',
            commercialPriority: $commercialPriority,
        );
    }

    private function query(string $text = 'widget', ?bool $expectedVisibility = null, ?string $priority = null): SearchQuery
    {
        return new SearchQuery(
            text: $text,
            provider: 'google',
            expectedVisibility: $expectedVisibility,
            priority: $priority,
        );
    }

    /**
     * @param array<int, Finding> $findings
     */
    private function queryVisibility(?SearchQuery $query = null, string $status = 'visible', array $findings = []): QueryVisibility
    {
        return new QueryVisibility(
            query: $query ?? $this->query(),
            status: $status,
            urlMatch: new UrlMatch(
                matched: $status === 'visible',
                matchType: $status === 'visible' ? 'exact' : 'none',
                expectedUrl: 'https://merchant.test/products/widget',
                matchedUrl: $status === 'visible' ? 'https://merchant.test/products/widget' : null,
                matchedPosition: $status === 'visible' ? 1 : null,
            ),
            findings: $findings,
        );
    }

    private function finding(string $code, string $severity, array $evidence = [], ?string $recommendation = null): Finding
    {
        return new Finding(
            code: $code,
            severity: $severity,
            confidence: 1.0,
            message: 'Finding ' . $code,
            evidence: $evidence,
            recommendation: $recommendation,
        );
    }
}
