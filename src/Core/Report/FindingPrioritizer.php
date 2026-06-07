<?php

declare(strict_types=1);

namespace VisibilityDetector\Core\Report;

use VisibilityDetector\Core\Product\ProductSubject;

final readonly class FindingPrioritizer
{
    private const SEVERITY_SCORES = [
        'critical' => 500,
        'high' => 400,
        'medium' => 300,
        'low' => 200,
        'info' => 100,
    ];

    private const SEVERITY_RANKS = [
        'critical' => 5,
        'high' => 4,
        'medium' => 3,
        'low' => 2,
        'info' => 1,
    ];

    private const PRIORITY_SCORES = [
        'critical' => 160,
        'high' => 100,
        'medium' => 40,
        'low' => 0,
    ];

    private const BLOCKER_RANKS = [
        'page.fetch_failed' => 1,
        'page.http_status_not_ok' => 2,
        'page.http_error' => 3,
        'page.non_html_content' => 4,
        'page.non_html_response' => 4,
        'page.noindex_meta' => 5,
        'page.noindex_x_robots' => 6,
        'page.robots_none' => 7,
        'page.unavailable_after_expired' => 8,
        'canonical.points_to_other_url' => 9,
        'canonical.points_to_homepage' => 10,
        'canonical.invalid' => 11,
        'canonical.relative' => 12,
        'schema.product_missing' => 13,
        'page.product_schema_missing' => 13,
        'schema.offer_missing' => 14,
        'page.offer_schema_missing' => 14,
        'schema.price_missing' => 15,
        'schema.currency_missing' => 15,
        'schema.availability_missing' => 15,
        'content.description_missing' => 16,
        'content.description_too_thin' => 16,
        'content.title_missing_product_terms' => 16,
        'content.h1_missing_product_terms' => 16,
        'content.body_missing_product_terms' => 16,
    ];

    private const CATEGORY_RANKS = [
        'availability_blocker' => 1,
        'indexability_blocker' => 2,
        'canonical_blocker' => 3,
        'visibility_quality' => 4,
        'content_quality' => 5,
        'diagnostic' => 6,
    ];

    private const CATEGORY_SCORES = [
        'availability_blocker' => 6000,
        'indexability_blocker' => 5000,
        'canonical_blocker' => 4000,
        'visibility_quality' => 3000,
        'content_quality' => 2000,
        'diagnostic' => 1000,
    ];

    /**
     * @return array{score:int,blockerRank:int,severityRank:int,category:string,categoryRank:int,code:string}
     */
    public function prioritize(Finding $finding, ?QueryVisibility $queryVisibility = null, ?ProductSubject $product = null): array
    {
        $blockerRank = self::BLOCKER_RANKS[$finding->code] ?? 100;
        $category = $this->category($finding->code);
        $score = (self::CATEGORY_SCORES[$category] ?? 0) + (self::SEVERITY_SCORES[$finding->severity] ?? 0) + max(0, 120 - ($blockerRank * 6));

        if ($queryVisibility instanceof QueryVisibility) {
            if ($queryVisibility->status === 'not_visible' && $queryVisibility->query->expectedVisibility === true) {
                $score += 180;
            }

            $score += self::PRIORITY_SCORES[$queryVisibility->query->priority ?? 'low'] ?? 0;
        }

        if ($product instanceof ProductSubject) {
            $score += self::PRIORITY_SCORES[$product->commercialPriority ?? 'low'] ?? 0;
        }

        return [
            'score' => $score,
            'blockerRank' => $blockerRank,
            'severityRank' => self::SEVERITY_RANKS[$finding->severity] ?? 0,
            'category' => $category,
            'categoryRank' => self::CATEGORY_RANKS[$category],
            'code' => $finding->code,
        ];
    }

    public function category(string $code): string
    {
        if (in_array($code, ['page.fetch_failed', 'page.http_status_not_ok', 'page.http_error', 'page.non_html_content', 'page.non_html_response'], true)) {
            return 'availability_blocker';
        }

        if (in_array($code, ['page.noindex_meta', 'page.noindex_x_robots', 'page.robots_none', 'page.unavailable_after_expired'], true)) {
            return 'indexability_blocker';
        }

        if (str_starts_with($code, 'canonical.')) {
            return 'canonical_blocker';
        }

        if (str_starts_with($code, 'schema.') || in_array($code, ['page.product_schema_missing', 'page.offer_schema_missing'], true)) {
            return 'visibility_quality';
        }

        if (str_starts_with($code, 'content.')) {
            return 'content_quality';
        }

        return 'diagnostic';
    }
}
