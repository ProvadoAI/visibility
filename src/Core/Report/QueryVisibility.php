<?php

declare(strict_types=1);

namespace VisibilityDetector\Core\Report;

use InvalidArgumentException;
use VisibilityDetector\Core\Search\SearchQuery;
use VisibilityDetector\Core\Search\SearchResult;
use VisibilityDetector\Core\Url\UrlMatch;

final readonly class QueryVisibility
{
    public string $visibilityHealth;

    public function __construct(
        public SearchQuery $query,
        public string $status,
        public UrlMatch $urlMatch,
        public ?SearchResult $matchedResult = null,
        public array $findings = [],
        public array $warnings = [],
        ?string $visibilityHealth = null,
    ) {
        if (!in_array($status, ['visible', 'not_visible', 'uncertain'], true)) {
            throw new InvalidArgumentException('status is invalid.');
        }

        foreach ($findings as $finding) {
            if (!$finding instanceof Finding) {
                throw new InvalidArgumentException('findings must contain only Finding objects.');
            }
        }

        if ($visibilityHealth !== null && !in_array($visibilityHealth, ['healthy', 'at_risk', 'blocked', 'unknown'], true)) {
            throw new InvalidArgumentException('visibilityHealth is invalid.');
        }

        $this->visibilityHealth = $visibilityHealth ?? self::visibilityHealthFor($status, $findings);
    }

    public static function fromArray(array $data): self
    {
        $query = $data['query'] ?? null;
        $urlMatch = $data['urlMatch'] ?? null;
        $matchedResult = $data['matchedResult'] ?? null;

        if (is_array($query)) {
            $query = SearchQuery::fromArray($query);
        }

        if (is_array($urlMatch)) {
            $urlMatch = UrlMatch::fromArray($urlMatch);
        }

        if (is_array($matchedResult)) {
            $matchedResult = SearchResult::fromArray($matchedResult);
        }

        if (!$query instanceof SearchQuery) {
            throw new InvalidArgumentException('query is required.');
        }

        if (!$urlMatch instanceof UrlMatch) {
            throw new InvalidArgumentException('urlMatch is required.');
        }

        return new self(
            query: $query,
            status: self::requiredString($data, 'status'),
            urlMatch: $urlMatch,
            matchedResult: $matchedResult,
            findings: array_map(
                static fn (mixed $finding): Finding => is_array($finding) ? Finding::fromArray($finding) : $finding,
                $data['findings'] ?? [],
            ),
            warnings: $data['warnings'] ?? [],
            visibilityHealth: isset($data['visibilityHealth']) && is_string($data['visibilityHealth']) ? $data['visibilityHealth'] : null,
        );
    }

    public function toArray(): array
    {
        return [
            'query' => $this->query->toArray(),
            'provider' => $this->query->provider,
            'locale' => $this->query->locale,
            'device' => $this->query->device,
            'status' => $this->status,
            'visibilityHealth' => $this->visibilityHealth,
            'urlMatch' => $this->urlMatch->toArray(),
            'matchedResult' => $this->matchedResult?->toArray(),
            'findings' => array_map(
                static fn (Finding $finding): array => $finding->toArray(),
                $this->findings,
            ),
            'warnings' => $this->warnings,
        ];
    }

    /**
     * @param array<int, Finding> $findings
     */
    private static function visibilityHealthFor(string $status, array $findings): string
    {
        $findingCodes = array_map(static fn (Finding $finding): string => $finding->code, $findings);

        foreach ($findingCodes as $code) {
            if (self::isBlockingFinding($code)) {
                return 'blocked';
            }
        }

        foreach ($findingCodes as $code) {
            if (self::isAtRiskFinding($code)) {
                return 'at_risk';
            }
        }

        if ($status === 'visible') {
            return 'healthy';
        }

        return 'unknown';
    }

    private static function isBlockingFinding(string $code): bool
    {
        return in_array($code, [
            'page.noindex_meta',
            'page.noindex_header',
            'page.noindex_x_robots',
            'page.robots_none',
            'page.unavailable_after_expired',
            'page.fetch_failed',
            'page.fetch_skipped',
            'page.parse_skipped',
            'analyzer.page_fetch_skipped',
            'analyzer.page_parse_skipped',
            'page.http_status_not_ok',
            'page.http_error',
            'page.non_html_content',
            'page.non_html_response',
        ], true);
    }

    private static function isAtRiskFinding(string $code): bool
    {
        return in_array($code, [
            'canonical.points_to_other_url',
            'canonical.points_to_homepage',
            'schema.product_missing',
            'page.product_schema_missing',
            'schema.offer_missing',
            'page.offer_schema_missing',
            'content.description_missing',
            'content.description_too_thin',
            'content.title_missing_product_terms',
            'content.h1_missing_product_terms',
            'content.body_missing_product_terms',
        ], true);
    }

    private static function requiredString(array $data, string $field): string
    {
        if (!array_key_exists($field, $data) || !is_string($data[$field]) || trim($data[$field]) === '') {
            throw new InvalidArgumentException($field . ' is required.');
        }

        return $data[$field];
    }
}
