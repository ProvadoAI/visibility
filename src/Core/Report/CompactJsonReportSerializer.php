<?php

declare(strict_types=1);

namespace VisibilityDetector\Core\Report;

use JsonException;

final readonly class CompactJsonReportSerializer implements ReportSerializer
{
    private const ENCODE_OPTIONS = JSON_THROW_ON_ERROR
        | JSON_PRETTY_PRINT
        | JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE;

    /**
     * @throws JsonException
     */
    public function serialize(VisibilityReport $report, ?\DateTimeImmutable $generatedAt = null): string
    {
        return json_encode($this->project($report), self::ENCODE_OPTIONS);
    }

    /**
     * @return array<string, mixed>
     */
    private function project(VisibilityReport $report): array
    {
        return [
            'product' => $this->productIdentity($report),
            'overallStatus' => $report->summary?->overallStatus,
            'overallPriority' => $report->summary?->overallPriority,
            'queries' => array_map(
                static fn (QueryVisibility $queryVisibility): array => [
                    'query' => [
                        'text' => $queryVisibility->query->text,
                        'provider' => $queryVisibility->query->provider,
                        'locale' => $queryVisibility->query->locale,
                        'device' => $queryVisibility->query->device,
                        'priority' => $queryVisibility->query->priority,
                    ],
                    'status' => $queryVisibility->status,
                    'visibilityHealth' => $queryVisibility->visibilityHealth,
                    'urlMatch' => [
                        'matched' => $queryVisibility->urlMatch->matched,
                        'matchType' => $queryVisibility->urlMatch->matchType,
                        'matchedUrl' => $queryVisibility->urlMatch->matchedUrl,
                        'matchedPosition' => $queryVisibility->urlMatch->matchedPosition,
                    ],
                    'warnings' => $queryVisibility->warnings,
                ],
                $report->queryVisibilities,
            ),
            'topProbableCauses' => $report->summary?->topProbableCauses ?? [],
            'topRecommendedActions' => $report->summary?->topRecommendedActions ?? [],
            'urlEvidence' => $this->urlEvidence($report),
            'warnings' => $report->warnings,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function productIdentity(VisibilityReport $report): array
    {
        return [
            'expectedUrl' => $report->product->expectedUrl,
            'id' => $report->product->id,
            'name' => $report->product->name,
            'brand' => $report->product->brand,
            'sku' => $report->product->sku,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function urlEvidence(VisibilityReport $report): array
    {
        $matchedUrls = [];

        foreach ($report->queryVisibilities as $queryVisibility) {
            if ($queryVisibility->urlMatch->matchedUrl !== null) {
                $matchedUrls[] = $queryVisibility->urlMatch->matchedUrl;
            }
        }

        return [
            'expectedUrl' => $report->product->expectedUrl,
            'acceptableUrlVariants' => $report->product->acceptableUrlVariants,
            'matchedUrls' => array_values(array_unique($matchedUrls)),
            'requestedUrl' => $report->pageSnapshot?->requestedUrl,
            'finalUrl' => $report->pageSnapshot?->finalUrl,
            'canonicalUrl' => $report->parsedPage?->canonicalUrl,
        ];
    }
}
