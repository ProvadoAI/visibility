<?php

declare(strict_types=1);

namespace VisibilityDetector\Core\Report;

final readonly class DiagnosticSummaryProjector
{
    /**
     * @return array<string, mixed>
     */
    public function project(VisibilityReport $report): array
    {
        $summary = $report->summary;
        $overallStatus = $summary?->overallStatus ?? $this->overallStatus($report->queryVisibilities);
        $health = $this->overallHealth($report->queryVisibilities);
        $primaryIssue = $this->primaryIssue($summary);

        return [
            'title' => $this->title($overallStatus, $health),
            'statusExplanation' => $this->statusExplanation($overallStatus, $health),
            'primaryIssue' => $primaryIssue,
            'merchantExplanation' => $this->merchantExplanation($overallStatus, $health),
            'recommendedNextStep' => $this->recommendedNextStep($summary, $overallStatus, $health),
            'evidenceHighlights' => $this->evidenceHighlights($report, $primaryIssue),
        ];
    }

    /**
     * @param array<int, QueryVisibility> $queryVisibilities
     */
    private function overallStatus(array $queryVisibilities): string
    {
        foreach ($queryVisibilities as $queryVisibility) {
            if ($queryVisibility->status === 'uncertain') {
                return 'uncertain';
            }
        }

        foreach ($queryVisibilities as $queryVisibility) {
            if ($queryVisibility->status === 'not_visible') {
                return 'not_visible';
            }
        }

        return 'visible';
    }

    /**
     * @param array<int, QueryVisibility> $queryVisibilities
     */
    private function overallHealth(array $queryVisibilities): string
    {
        foreach (['blocked', 'at_risk', 'unknown'] as $health) {
            foreach ($queryVisibilities as $queryVisibility) {
                if ($queryVisibility->visibilityHealth === $health) {
                    return $health;
                }
            }
        }

        return 'healthy';
    }

    private function title(string $overallStatus, string $health): string
    {
        if ($overallStatus === 'visible') {
            return match ($health) {
                'blocked' => 'Visible with technical blocker',
                'at_risk' => 'Visible with technical risk',
                default => 'Visible and healthy',
            };
        }

        if ($overallStatus === 'not_visible') {
            return 'Product not visible';
        }

        return 'Visibility uncertain';
    }

    private function statusExplanation(string $overallStatus, string $health): string
    {
        if ($overallStatus === 'visible') {
            return match ($health) {
                'blocked' => 'The product appeared in the supplied search evidence. The supplied page evidence also shows a technical blocker that can prevent reliable discovery.',
                'at_risk' => 'The product appeared in the supplied search evidence. The supplied page evidence also shows a technical risk that should be reviewed.',
                default => 'The product was visible in the supplied search evidence. No blocking technical visibility issue was detected from the supplied page evidence.',
            };
        }

        if ($overallStatus === 'not_visible') {
            return 'The expected product URL was absent from the supplied search results. The product was not visible for at least one expected query in the supplied local evidence.';
        }

        return 'The supplied search evidence was incomplete or limited. The product visibility result should be treated as uncertain until complete local evidence is supplied.';
    }

    /**
     * @return array<string, mixed>|null
     */
    private function primaryIssue(?ReportSummary $summary): ?array
    {
        if (!$summary instanceof ReportSummary) {
            return null;
        }

        foreach ($summary->topProbableCauses as $cause) {
            if (!is_array($cause) || ($cause['code'] ?? null) === 'product.visible_in_results') {
                continue;
            }

            return [
                'code' => $cause['code'] ?? null,
                'category' => $cause['category'] ?? null,
                'message' => $cause['message'] ?? null,
                'severity' => $cause['severity'] ?? null,
                'affectedQuery' => $cause['affectedQuery'] ?? null,
                'affectedQueries' => $cause['affectedQueries'] ?? [],
            ];
        }

        return null;
    }

    private function merchantExplanation(string $overallStatus, string $health): string
    {
        if ($overallStatus === 'visible') {
            return match ($health) {
                'blocked' => 'A shopper could see the supplied result, but a technical blocker may prevent crawlers or search systems from indexing or using the product page.',
                'at_risk' => 'A shopper could see the supplied result, but page signals may reduce confidence that this is the preferred product page.',
                default => 'A shopper using the supplied evidence could see the expected product result.',
            };
        }

        if ($overallStatus === 'not_visible') {
            return 'A shopper using the supplied query evidence would not see the expected product result in this result set.';
        }

        return 'The supplied evidence is not complete enough to make a confident merchant-facing visibility call.';
    }

    /**
     * @return array<string, mixed>|null
     */
    private function recommendedNextStep(?ReportSummary $summary, string $overallStatus, string $health): ?array
    {
        if ($summary instanceof ReportSummary) {
            foreach ($summary->topRecommendedActions as $action) {
                if (!is_array($action)) {
                    continue;
                }

                if ($overallStatus === 'visible' && $health === 'healthy' && ($action['code'] ?? null) === 'product.visible_in_results') {
                    return [
                        'code' => $action['code'] ?? null,
                        'category' => $action['category'] ?? null,
                        'action' => $action['action'] ?? null,
                        'affectedQuery' => $action['affectedQuery'] ?? null,
                        'affectedQueries' => $action['affectedQueries'] ?? [],
                    ];
                }

                if (($action['code'] ?? null) !== 'product.visible_in_results') {
                    return [
                        'code' => $action['code'] ?? null,
                        'category' => $action['category'] ?? null,
                        'action' => $action['action'] ?? null,
                        'affectedQuery' => $action['affectedQuery'] ?? null,
                        'affectedQueries' => $action['affectedQueries'] ?? [],
                    ];
                }
            }
        }

        if ($overallStatus === 'visible' && $health === 'healthy') {
            return null;
        }

        return null;
    }

    /**
     * @param array<string, mixed>|null $primaryIssue
     * @return array<int, array<string, mixed>>
     */
    private function evidenceHighlights(VisibilityReport $report, ?array $primaryIssue): array
    {
        $matchedUrls = [];
        $visibilityHealth = [];
        $statuses = [];

        foreach ($report->queryVisibilities as $queryVisibility) {
            if ($queryVisibility->urlMatch->matchedUrl !== null) {
                $matchedUrls[] = $queryVisibility->urlMatch->matchedUrl;
            }

            $visibilityHealth[] = $queryVisibility->visibilityHealth;
            $statuses[] = $queryVisibility->status;
        }

        return [[
            'expectedUrl' => $report->product->expectedUrl,
            'matchedUrls' => array_values(array_unique($matchedUrls)),
            'requestedUrl' => $report->pageSnapshot?->requestedUrl,
            'finalUrl' => $report->pageSnapshot?->finalUrl,
            'canonicalUrl' => $report->parsedPage?->canonicalUrl,
            'overallStatus' => $report->summary?->overallStatus ?? $this->overallStatus($report->queryVisibilities),
            'visibilityHealth' => array_values(array_unique($visibilityHealth)),
            'queryStatuses' => array_values(array_unique($statuses)),
            'topFindingCode' => $primaryIssue['code'] ?? null,
        ]];
    }
}
