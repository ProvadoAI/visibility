<?php

declare(strict_types=1);

namespace VisibilityDetector\Core\Detector;

use VisibilityDetector\Core\Page\PageSnapshot;
use VisibilityDetector\Core\Page\ParsedPage;
use VisibilityDetector\Core\Report\Finding;
use VisibilityDetector\Core\Url\UrlNormalizer;

final readonly class IndexabilityDetector implements Detector
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
        if ($context->pageSnapshot === null && $context->parsedPage === null) {
            return [new Finding(
                code: 'page.indexability_uncertain',
                severity: 'medium',
                confidence: 0.4,
                message: 'No page fetch or parsed-page evidence was supplied, so indexability is uncertain.',
                evidence: $this->baseEvidence($context),
                recommendation: 'Supply PageSnapshot or ParsedPage evidence before treating the page as indexable.',
            )];
        }

        $findings = [];

        if ($context->pageSnapshot !== null) {
            $findings = array_merge($findings, $this->snapshotFindings($context));
        }

        if ($context->parsedPage !== null) {
            $findings = array_merge($findings, $this->parsedPageFindings($context));
        }

        return $findings;
    }

    /**
     * @return array<int, Finding>
     */
    private function snapshotFindings(DetectionContext $context): array
    {
        $snapshot = $context->pageSnapshot;

        if (!$snapshot instanceof PageSnapshot) {
            return [];
        }

        $findings = [];
        $evidence = $this->snapshotEvidence($context, $snapshot);

        if (is_string($snapshot->failureType) && trim($snapshot->failureType) !== '' && $snapshot->failureType !== 'none') {
            $findings[] = new Finding(
                code: 'page.fetch_failed',
                severity: 'high',
                confidence: 0.95,
                message: 'The supplied page snapshot records a fetch failure.',
                evidence: $evidence,
                recommendation: 'Resolve the fetch failure before evaluating page visibility or indexability.',
            );
        }

        if ($snapshot->statusCode !== null && ($snapshot->statusCode < 200 || $snapshot->statusCode > 299)) {
            $findings[] = new Finding(
                code: 'page.http_status_not_ok',
                severity: 'high',
                confidence: 0.95,
                message: 'The product page returned a non-2xx HTTP status in the supplied snapshot.',
                evidence: $evidence,
                recommendation: 'Ensure the product page returns a successful 2xx HTTP status for indexable requests.',
            );
        }

        if ($snapshot->body === null || trim($snapshot->body) === '') {
            $findings[] = new Finding(
                code: 'page.empty_body',
                severity: 'medium',
                confidence: 0.9,
                message: 'The supplied page snapshot has an empty response body.',
                evidence: $evidence,
                recommendation: 'Provide an HTML response body with crawlable product content.',
            );
        }

        if ($snapshot->contentType !== null && !$this->isHtmlContentType($snapshot->contentType)) {
            $findings[] = new Finding(
                code: 'page.non_html_content',
                severity: 'high',
                confidence: 0.9,
                message: 'The supplied page snapshot does not have an HTML content type.',
                evidence: $evidence,
                recommendation: 'Serve the product page as HTML for crawler and search-engine access.',
            );
        }

        return $findings;
    }

    /**
     * @return array<int, Finding>
     */
    private function parsedPageFindings(DetectionContext $context): array
    {
        $parsedPage = $context->parsedPage;

        if (!$parsedPage instanceof ParsedPage) {
            return [];
        }

        $findings = [];
        $evidence = $this->parsedPageEvidence($context, $parsedPage);

        if ($this->directivesContainNoindex($parsedPage->robotsDirectives)) {
            $findings[] = new Finding(
                code: 'page.noindex_meta',
                severity: 'high',
                confidence: 0.95,
                message: 'The parsed page contains a meta robots noindex directive.',
                evidence: $evidence + ['robotsDirectives' => $parsedPage->robotsDirectives],
                recommendation: 'Remove noindex from the page meta robots directives if the product page should be indexed.',
            );
        }

        if ($this->directivesContainNoindex($parsedPage->xRobotsDirectives)) {
            $findings[] = new Finding(
                code: 'page.noindex_x_robots',
                severity: 'high',
                confidence: 0.95,
                message: 'The parsed page contains an X-Robots-Tag noindex directive.',
                evidence: $evidence + ['xRobotsDirectives' => $parsedPage->xRobotsDirectives],
                recommendation: 'Remove noindex from X-Robots-Tag headers if the product page should be indexed.',
            );
        }

        if ($parsedPage->canonicalUrl !== null && trim($parsedPage->canonicalUrl) !== '' && !$this->canonicalMatchesProduct($context, $parsedPage->canonicalUrl)) {
            $findings[] = new Finding(
                code: 'page.canonical_mismatch',
                severity: 'medium',
                confidence: 0.9,
                message: 'The parsed canonical URL does not match the expected product URL or its acceptable variants.',
                evidence: $evidence + [
                    'canonicalUrl' => $parsedPage->canonicalUrl,
                    'normalizedCanonicalUrl' => $this->normalizer->normalize($parsedPage->canonicalUrl),
                    'normalizedAcceptedUrls' => $this->acceptedCanonicalUrls($context),
                ],
                recommendation: 'Point the canonical URL at the expected product URL or an explicitly acceptable variant.',
            );
        }

        return $findings;
    }

    /**
     * @param array<int, mixed> $directives
     */
    private function directivesContainNoindex(array $directives): bool
    {
        foreach ($directives as $directive) {
            if ($this->isNoindexDirective($directive)) {
                return true;
            }
        }

        return false;
    }

    private function isNoindexDirective(mixed $directive): bool
    {
        if (!is_string($directive)) {
            return false;
        }

        $directive = strtolower(trim($directive));

        if ($directive === 'noindex') {
            return true;
        }

        $parts = explode(':', $directive, 2);

        return count($parts) === 2 && trim($parts[0]) !== '' && trim($parts[1]) === 'noindex';
    }

    private function canonicalMatchesProduct(DetectionContext $context, string $canonicalUrl): bool
    {
        return in_array($this->normalizer->normalize($canonicalUrl), $this->acceptedCanonicalUrls($context), true);
    }

    /**
     * @return array<int, string>
     */
    private function acceptedCanonicalUrls(DetectionContext $context): array
    {
        return array_values(array_unique(array_map(
            fn (string $url): string => $this->normalizer->normalize($url),
            array_merge([$context->product->expectedUrl], $context->product->acceptableUrlVariants),
        )));
    }

    private function isHtmlContentType(string $contentType): bool
    {
        $type = strtolower(trim(explode(';', $contentType, 2)[0]));

        return $type === 'text/html' || $type === 'application/xhtml+xml';
    }

    /**
     * @return array<string, mixed>
     */
    private function baseEvidence(DetectionContext $context): array
    {
        return [
            'product' => [
                'expectedUrl' => $context->product->expectedUrl,
                'acceptableUrlVariants' => $context->product->acceptableUrlVariants,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshotEvidence(DetectionContext $context, PageSnapshot $snapshot): array
    {
        return $this->baseEvidence($context) + [
            'pageSnapshot' => [
                'requestedUrl' => $snapshot->requestedUrl,
                'finalUrl' => $snapshot->finalUrl,
                'statusCode' => $snapshot->statusCode,
                'contentType' => $snapshot->contentType,
                'failureType' => $snapshot->failureType,
                'warnings' => $snapshot->warnings,
                'bodyLength' => $snapshot->body === null ? null : strlen($snapshot->body),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function parsedPageEvidence(DetectionContext $context, ParsedPage $parsedPage): array
    {
        return $this->baseEvidence($context) + [
            'parsedPage' => [
                'url' => $parsedPage->url,
                'canonicalUrl' => $parsedPage->canonicalUrl,
                'parserWarnings' => $parsedPage->parserWarnings,
            ],
        ];
    }
}
