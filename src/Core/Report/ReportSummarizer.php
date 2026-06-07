<?php

declare(strict_types=1);

namespace VisibilityDetector\Core\Report;

use VisibilityDetector\Core\Product\ProductSubject;

final readonly class ReportSummarizer
{
    public function __construct(private FindingPrioritizer $prioritizer = new FindingPrioritizer())
    {
    }

    public function summarizeReport(VisibilityReport $report): ReportSummary
    {
        return $this->summarize($report->product, $report->queryVisibilities);
    }

    /**
     * @param array<int, QueryVisibility> $queryVisibilities
     */
    public function summarize(ProductSubject $product, array $queryVisibilities): ReportSummary
    {
        $totalQueries = count($queryVisibilities);
        $visibleCount = $this->countStatus($queryVisibilities, 'visible');
        $notVisibleCount = $this->countStatus($queryVisibilities, 'not_visible');
        $uncertainCount = $this->countStatus($queryVisibilities, 'uncertain');
        $rankedFindings = $this->rankedFindings($product, $queryVisibilities);
        $highSeverityFindingCount = 0;

        foreach ($rankedFindings as $rankedFinding) {
            if (in_array($rankedFinding['finding']->severity, ['critical', 'high'], true)) {
                ++$highSeverityFindingCount;
            }
        }

        $overallStatus = $this->overallStatus($visibleCount, $notVisibleCount, $uncertainCount);
        $overallPriority = $this->overallPriority($product, $queryVisibilities, $rankedFindings, $overallStatus);
        $highestPriorityAffectedQuery = $this->highestPriorityAffectedQuery($product, $queryVisibilities, $rankedFindings);
        $topFindings = array_slice($rankedFindings, 0, 5);

        return new ReportSummary(
            overallStatus: $overallStatus,
            overallPriority: $overallPriority,
            message: $this->message($overallStatus, $overallPriority, $highestPriorityAffectedQuery, $totalQueries, $topFindings),
            highestPriorityAffectedQuery: $highestPriorityAffectedQuery,
            topProbableCauses: $this->topProbableCauses($topFindings),
            topRecommendedActions: $this->topRecommendedActions($topFindings, $overallStatus),
            evidenceReferences: $this->evidenceReferences($topFindings),
            totalQueries: $totalQueries,
            visibleCount: $visibleCount,
            notVisibleCount: $notVisibleCount,
            uncertainCount: $uncertainCount,
            highSeverityFindingCount: $highSeverityFindingCount,
        );
    }

    /**
     * @param array<int, QueryVisibility> $queryVisibilities
     * @return array<int, array{finding: Finding, queryVisibility: QueryVisibility, priority: array, index: int}>
     */
    private function rankedFindings(ProductSubject $product, array $queryVisibilities): array
    {
        $ranked = [];
        $index = 0;

        foreach ($queryVisibilities as $queryVisibility) {
            foreach ($queryVisibility->findings as $finding) {
                $ranked[] = [
                    'finding' => $finding,
                    'queryVisibility' => $queryVisibility,
                    'priority' => $this->prioritizer->prioritize($finding, $queryVisibility, $product),
                    'index' => $index,
                ];
                ++$index;
            }
        }

        usort($ranked, static function (array $left, array $right): int {
            if ($left['priority']['score'] !== $right['priority']['score']) {
                return $right['priority']['score'] <=> $left['priority']['score'];
            }

            if ($left['priority']['categoryRank'] !== $right['priority']['categoryRank']) {
                return $left['priority']['categoryRank'] <=> $right['priority']['categoryRank'];
            }

            if ($left['priority']['blockerRank'] !== $right['priority']['blockerRank']) {
                return $left['priority']['blockerRank'] <=> $right['priority']['blockerRank'];
            }

            if ($left['priority']['severityRank'] !== $right['priority']['severityRank']) {
                return $right['priority']['severityRank'] <=> $left['priority']['severityRank'];
            }

            if ($left['finding']->code !== $right['finding']->code) {
                return $left['finding']->code <=> $right['finding']->code;
            }

            if ($left['queryVisibility']->query->text !== $right['queryVisibility']->query->text) {
                return $left['queryVisibility']->query->text <=> $right['queryVisibility']->query->text;
            }

            return $left['index'] <=> $right['index'];
        });

        return $ranked;
    }

    /**
     * @param array<int, QueryVisibility> $queryVisibilities
     */
    private function countStatus(array $queryVisibilities, string $status): int
    {
        $count = 0;

        foreach ($queryVisibilities as $queryVisibility) {
            if ($queryVisibility->status === $status) {
                ++$count;
            }
        }

        return $count;
    }

    private function overallStatus(int $visibleCount, int $notVisibleCount, int $uncertainCount): string
    {
        if ($uncertainCount > 0) {
            return 'uncertain';
        }

        if ($notVisibleCount > 0) {
            return 'not_visible';
        }

        return 'visible';
    }

    /**
     * @param array<int, QueryVisibility> $queryVisibilities
     * @param array<int, array{finding: Finding, queryVisibility: QueryVisibility, priority: array, index: int}> $rankedFindings
     */
    private function overallPriority(ProductSubject $product, array $queryVisibilities, array $rankedFindings, string $overallStatus): string
    {
        $priorityRank = $overallStatus === 'visible' ? 1 : 2;
        $commercialRank = $this->priorityRank($product->commercialPriority);

        foreach ($queryVisibilities as $queryVisibility) {
            $queryRank = $this->priorityRank($queryVisibility->query->priority);

            if ($queryVisibility->status === 'not_visible' && $queryVisibility->query->expectedVisibility === true) {
                $priorityRank = max($priorityRank, 2);

                if ($queryRank >= 3 || $commercialRank >= 3) {
                    $priorityRank = max($priorityRank, 3);
                }

                if ($queryRank >= 4 || $commercialRank >= 4) {
                    $priorityRank = max($priorityRank, 4);
                }
            }
        }

        foreach ($rankedFindings as $rankedFinding) {
            $finding = $rankedFinding['finding'];
            $category = $rankedFinding['priority']['category'];
            $severityRank = $this->severityRank($finding->severity);

            if (in_array($category, ['availability_blocker', 'indexability_blocker', 'canonical_blocker'], true)) {
                $priorityRank = max($priorityRank, $severityRank >= 5 ? 4 : 3);
            } elseif ($category === 'visibility_quality') {
                $priorityRank = max($priorityRank, $severityRank >= 5 ? 3 : 2);
            } elseif ($severityRank >= 4) {
                $priorityRank = max($priorityRank, 2);
            }
        }

        if ($commercialRank >= 4 && ($priorityRank >= 3 || $overallStatus !== 'visible')) {
            $priorityRank = 4;
        }

        return $this->priorityLabel($priorityRank);
    }

    /**
     * @param array<int, QueryVisibility> $queryVisibilities
     * @param array<int, array{finding: Finding, queryVisibility: QueryVisibility, priority: array, index: int}> $rankedFindings
     */
    private function highestPriorityAffectedQuery(ProductSubject $product, array $queryVisibilities, array $rankedFindings): ?string
    {
        $candidates = [];
        $index = 0;

        foreach ($queryVisibilities as $queryVisibility) {
            if ($queryVisibility->status === 'not_visible' || $queryVisibility->status === 'uncertain') {
                $score = 1000 + ($queryVisibility->query->expectedVisibility === true ? 250 : 0) + ($this->priorityRank($queryVisibility->query->priority) * 100) + ($this->priorityRank($product->commercialPriority) * 25);
                $candidates[] = [$score, $queryVisibility->query->text, $index];
            }

            ++$index;
        }

        if ($candidates === [] && isset($rankedFindings[0])) {
            return $rankedFindings[0]['queryVisibility']->query->text;
        }

        if ($candidates === []) {
            return null;
        }

        usort($candidates, static fn (array $left, array $right): int => [$right[0], $left[1], $left[2]] <=> [$left[0], $right[1], $right[2]]);

        return $candidates[0][1];
    }

    /**
     * @param array<int, array{finding: Finding, queryVisibility: QueryVisibility, priority: array, index: int}> $topFindings
     * @return array<int, array{code:string,category:string,message:string,affectedQuery:string,severity:string}>
     */
    private function topProbableCauses(array $topFindings): array
    {
        $causes = [];

        foreach ($topFindings as $rankedFinding) {
            $finding = $rankedFinding['finding'];
            $causes[] = [
                'code' => $finding->code,
                'category' => $rankedFinding['priority']['category'],
                'message' => $finding->message,
                'affectedQuery' => $rankedFinding['queryVisibility']->query->text,
                'severity' => $finding->severity,
            ];
        }

        return $causes;
    }

    /**
     * @param array<int, array{finding: Finding, queryVisibility: QueryVisibility, priority: array, index: int}> $topFindings
     * @return array<int, array{code:string,category:string,action:string,affectedQuery:string}>
     */
    private function topRecommendedActions(array $topFindings, string $overallStatus): array
    {
        $actions = [];
        $seen = [];

        foreach ($topFindings as $rankedFinding) {
            $finding = $rankedFinding['finding'];
            $action = $finding->recommendation ?? $this->defaultRecommendation($finding->code);
            $key = $finding->code . "\n" . $action . "\n" . $rankedFinding['queryVisibility']->query->text;

            if ($action === null || isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $actions[] = [
                'code' => $finding->code,
                'category' => $rankedFinding['priority']['category'],
                'action' => $action,
                'affectedQuery' => $rankedFinding['queryVisibility']->query->text,
            ];
        }

        if ($actions === [] && $overallStatus === 'visible') {
            $actions[] = [
                'code' => 'product.visible_in_results',
                'category' => 'diagnostic',
                'action' => 'Monitor supplied expected queries for visibility changes.',
                'affectedQuery' => '',
            ];
        }

        return array_slice($actions, 0, 5);
    }

    /**
     * @param array<int, array{finding: Finding, queryVisibility: QueryVisibility, priority: array, index: int}> $topFindings
     * @return array<int, array{code:string,category:string,affectedQuery:string,severity:string,evidence:array}>
     */
    private function evidenceReferences(array $topFindings): array
    {
        $references = [];

        foreach ($topFindings as $rankedFinding) {
            $finding = $rankedFinding['finding'];
            $references[] = [
                'code' => $finding->code,
                'category' => $rankedFinding['priority']['category'],
                'affectedQuery' => $rankedFinding['queryVisibility']->query->text,
                'severity' => $finding->severity,
                'evidence' => $finding->evidence,
            ];
        }

        return $references;
    }

    /**
     * @param array<int, array{finding: Finding, queryVisibility: QueryVisibility, priority: array, index: int}> $topFindings
     */
    private function message(string $overallStatus, string $overallPriority, ?string $highestPriorityAffectedQuery, int $totalQueries, array $topFindings): string
    {
        if ($topFindings !== []) {
            $top = $topFindings[0];

            return sprintf(
                '%s priority: product is %s across %d supplied %s; top issue is %s for "%s".',
                ucfirst($overallPriority),
                str_replace('_', ' ', $overallStatus),
                $totalQueries,
                $totalQueries === 1 ? 'query' : 'queries',
                $top['finding']->code,
                $top['queryVisibility']->query->text,
            );
        }

        if ($highestPriorityAffectedQuery !== null) {
            return sprintf('%s priority: product is %s for "%s".', ucfirst($overallPriority), str_replace('_', ' ', $overallStatus), $highestPriorityAffectedQuery);
        }

        return sprintf('%s priority: product is %s across %d supplied %s.', ucfirst($overallPriority), str_replace('_', ' ', $overallStatus), $totalQueries, $totalQueries === 1 ? 'query' : 'queries');
    }

    private function defaultRecommendation(string $code): ?string
    {
        return match ($code) {
            'page.fetch_failed' => 'Restore crawlable product page fetch availability.',
            'page.http_status_not_ok', 'page.http_error' => 'Return a successful HTTP response for the product page.',
            'page.non_html_content', 'page.non_html_response' => 'Serve crawlable HTML content for the product page.',
            'page.noindex_meta', 'page.noindex_x_robots', 'page.robots_none' => 'Remove noindex or robots none directives from the product page when visibility is expected.',
            'canonical.points_to_other_url', 'canonical.points_to_homepage', 'canonical.invalid', 'canonical.relative' => 'Fix the canonical URL so it resolves to the intended product URL.',
            'schema.product_missing', 'page.product_schema_missing' => 'Add schema.org Product structured data for the product page.',
            'schema.offer_missing', 'page.offer_schema_missing' => 'Add Offer structured data for price and availability signals when applicable.',
            'schema.price_missing' => 'Add a price value to Offer structured data.',
            'schema.currency_missing' => 'Add a priceCurrency value to Offer structured data.',
            'schema.availability_missing' => 'Add an availability value to Offer structured data.',
            'content.description_missing', 'content.description_too_thin' => 'Improve the meta description with product-specific copy.',
            'content.title_missing_product_terms', 'content.h1_missing_product_terms', 'content.body_missing_product_terms' => 'Add expected product terms to prominent page content.',
            default => null,
        };
    }

    private function priorityRank(?string $priority): int
    {
        return match ($priority) {
            'critical' => 4,
            'high' => 3,
            'medium' => 2,
            default => 1,
        };
    }

    private function priorityLabel(int $rank): string
    {
        return match ($rank) {
            4 => 'critical',
            3 => 'high',
            2 => 'medium',
            default => 'low',
        };
    }

    private function severityRank(string $severity): int
    {
        return match ($severity) {
            'critical' => 5,
            'high' => 4,
            'medium' => 3,
            'low' => 2,
            default => 1,
        };
    }
}
