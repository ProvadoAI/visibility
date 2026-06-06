<?php

declare(strict_types=1);

namespace VisibilityDetector\Core\Detector;

use VisibilityDetector\Core\Page\ParsedPage;
use VisibilityDetector\Core\Report\Finding;
use VisibilityDetector\Core\Url\UrlNormalizer;

final readonly class CanonicalDetector implements Detector
{
    public function __construct(
        private UrlNormalizer $normalizer = new UrlNormalizer(),
    ) {
    }

    /**
     * @return array<int, Finding>
     */
    public function detect(DetectionContext $context): array
    {
        if (!$context->parsedPage instanceof ParsedPage) {
            return [];
        }

        $canonicalUrls = $this->canonicalUrls($context->parsedPage);
        $canonicalUrl = $canonicalUrls[0] ?? null;
        $baseEvidence = $this->baseEvidence($context, $canonicalUrl, $canonicalUrls);

        if ($canonicalUrl === null) {
            return [new Finding(
                code: 'canonical.missing',
                severity: 'medium',
                confidence: 0.85,
                message: 'The parsed page does not declare a canonical URL.',
                evidence: $baseEvidence + ['reason' => 'canonical_url_missing'],
                recommendation: 'Add one absolute canonical URL that points to the expected product URL or an explicitly acceptable variant.',
            )];
        }

        $findings = [];
        $normalizedCanonicalUrls = $this->normalizedAbsoluteCanonicalUrls($canonicalUrls);

        if (count(array_unique($normalizedCanonicalUrls)) > 1) {
            $findings[] = new Finding(
                code: 'canonical.multiple_conflicting',
                severity: 'high',
                confidence: 0.95,
                message: 'The parsed page declares multiple conflicting canonical URLs.',
                evidence: $baseEvidence + [
                    'normalizedCanonicalUrls' => array_values(array_unique($normalizedCanonicalUrls)),
                    'reason' => 'multiple_distinct_normalized_canonical_urls',
                ],
                recommendation: 'Keep exactly one canonical URL for the product page, or ensure duplicate canonical tags all point to the same normalized URL.',
            );
        }

        if (!$this->isParseableUrl($canonicalUrl)) {
            $findings[] = new Finding(
                code: 'canonical.invalid',
                severity: 'high',
                confidence: 0.95,
                message: 'The parsed canonical URL could not be parsed as a valid URL.',
                evidence: $baseEvidence + ['reason' => 'canonical_url_invalid'],
                recommendation: 'Replace the canonical href with a valid absolute URL for the product page.',
            );

            return $findings;
        }

        if (!$this->isAbsoluteUrl($canonicalUrl)) {
            $findings[] = new Finding(
                code: 'canonical.relative',
                severity: 'medium',
                confidence: 0.95,
                message: 'The parsed canonical URL is relative instead of absolute.',
                evidence: $baseEvidence + ['reason' => 'canonical_url_relative'],
                recommendation: 'Use a fully qualified absolute canonical URL; do not rely on resolving a relative canonical href.',
            );

            return $findings;
        }

        if (!$this->isValidAbsoluteUrl($canonicalUrl)) {
            $findings[] = new Finding(
                code: 'canonical.invalid',
                severity: 'high',
                confidence: 0.95,
                message: 'The parsed canonical URL is not a valid absolute URL.',
                evidence: $baseEvidence + ['reason' => 'canonical_absolute_url_invalid'],
                recommendation: 'Replace the canonical href with a valid absolute URL for the product page.',
            );

            return $findings;
        }

        $normalizedCanonicalUrl = $this->normalizer->normalize($canonicalUrl);
        $evidence = $baseEvidence + [
            'normalizedCanonicalUrl' => $normalizedCanonicalUrl,
        ];

        if ($this->pointsToHomepage($context, $canonicalUrl)) {
            $findings[] = new Finding(
                code: 'canonical.points_to_homepage',
                severity: 'high',
                confidence: 0.95,
                message: 'The canonical URL points to the site homepage instead of the product page path.',
                evidence: $evidence + ['reason' => 'canonical_path_is_site_root'],
                recommendation: 'Point the canonical URL at the expected product URL or an explicitly acceptable product URL variant.',
            );
        }

        if (!in_array($normalizedCanonicalUrl, $this->normalizedAcceptedUrls($context), true)) {
            $findings[] = new Finding(
                code: 'canonical.points_to_other_url',
                severity: 'high',
                confidence: 0.95,
                message: 'The canonical URL does not match the expected product URL or its acceptable variants.',
                evidence: $evidence + ['reason' => 'canonical_not_in_expected_or_acceptable_urls'],
                recommendation: 'Point the canonical URL at the expected product URL or an explicitly acceptable product URL variant.',
            );
        }

        return $findings;
    }

    /**
     * @return array<int, string>
     */
    private function canonicalUrls(ParsedPage $parsedPage): array
    {
        $urls = $parsedPage->canonicalUrls;

        if ($urls === [] && $parsedPage->canonicalUrl !== null) {
            $urls[] = $parsedPage->canonicalUrl;
        }

        return array_values(array_filter(
            $urls,
            static fn (string $url): bool => trim($url) !== '',
        ));
    }

    /**
     * @param array<int, string> $canonicalUrls
     * @return array<int, string>
     */
    private function normalizedAbsoluteCanonicalUrls(array $canonicalUrls): array
    {
        $normalized = [];

        foreach ($canonicalUrls as $url) {
            if ($this->isParseableUrl($url) && $this->isAbsoluteUrl($url) && $this->isValidAbsoluteUrl($url)) {
                $normalized[] = $this->normalizer->normalize($url);
            }
        }

        return $normalized;
    }

    private function isParseableUrl(string $url): bool
    {
        return trim($url) !== '' && !preg_match('/\s/', $url) && parse_url($url) !== false;
    }

    private function isAbsoluteUrl(string $url): bool
    {
        $parts = parse_url($url);

        return is_array($parts)
            && isset($parts['scheme'], $parts['host'])
            && is_string($parts['scheme'])
            && is_string($parts['host']);
    }

    private function isValidAbsoluteUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    private function pointsToHomepage(DetectionContext $context, string $canonicalUrl): bool
    {
        $canonicalParts = parse_url($canonicalUrl);
        $expectedParts = parse_url($context->product->expectedUrl);

        if (!is_array($canonicalParts) || !is_array($expectedParts)) {
            return false;
        }

        $canonicalHost = strtolower((string) ($canonicalParts['host'] ?? ''));
        $expectedHost = strtolower((string) ($expectedParts['host'] ?? ''));
        $canonicalPath = trim((string) ($canonicalParts['path'] ?? ''), '/');
        $expectedPath = trim((string) ($expectedParts['path'] ?? ''), '/');

        return $canonicalHost !== ''
            && $canonicalHost === $expectedHost
            && $canonicalPath === ''
            && $expectedPath !== '';
    }

    /**
     * @return array<int, string>
     */
    private function normalizedAcceptedUrls(DetectionContext $context): array
    {
        return array_values(array_unique(array_map(
            fn (string $url): string => $this->normalizer->normalize($url),
            array_merge([$context->product->expectedUrl], $context->product->acceptableUrlVariants),
        )));
    }

    /**
     * @param array<int, string> $canonicalUrls
     * @return array<string, mixed>
     */
    private function baseEvidence(DetectionContext $context, ?string $canonicalUrl, array $canonicalUrls): array
    {
        return [
            'expectedUrl' => $context->product->expectedUrl,
            'acceptableUrlVariants' => $context->product->acceptableUrlVariants,
            'canonicalUrl' => $canonicalUrl,
            'canonicalUrls' => $canonicalUrls,
            'normalizedExpectedUrl' => $this->normalizer->normalize($context->product->expectedUrl),
            'normalizedAcceptableUrlVariants' => array_values(array_unique(array_map(
                fn (string $url): string => $this->normalizer->normalize($url),
                $context->product->acceptableUrlVariants,
            ))),
        ];
    }
}
