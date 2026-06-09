<?php

declare(strict_types=1);

namespace VisibilityDetector\Core\Detector;

use VisibilityDetector\Core\Page\PageSnapshot;
use VisibilityDetector\Core\Report\Finding;
use VisibilityDetector\Core\Url\UrlNormalizer;

final readonly class HttpAvailabilityDetector implements Detector
{
    private UrlNormalizer $normalizer;

    public function __construct(?UrlNormalizer $normalizer = null)
    {
        $this->normalizer = $normalizer ?? new UrlNormalizer();
    }

    /**
     * @return array<int, Finding>
     */
    public function detect(DetectionContext $context): array
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
                code: 'page.http_error',
                severity: 'high',
                confidence: 0.95,
                message: 'The product page returned an HTTP error status in the supplied snapshot.',
                evidence: $evidence + ['reason' => 'non_2xx_status_code'],
                recommendation: 'Ensure the product page returns a successful 2xx HTTP status for indexable requests.',
            );

            $findings[] = new Finding(
                code: 'page.http_status_not_ok',
                severity: 'high',
                confidence: 0.95,
                message: 'The product page returned a non-2xx HTTP status in the supplied snapshot.',
                evidence: $evidence,
                recommendation: 'Ensure the product page returns a successful 2xx HTTP status for indexable requests.',
            );
        }

        if ($snapshot->finalUrl !== null && trim($snapshot->finalUrl) !== '' && !$this->finalUrlMatchesProduct($context, $snapshot->finalUrl)) {
            $findings[] = new Finding(
                code: 'page.redirects_elsewhere',
                severity: 'high',
                confidence: 0.95,
                message: 'The product page request resolved to a final URL outside the expected product URL set.',
                evidence: $evidence + [
                    'normalizedFinalUrl' => $this->normalizer->normalize($snapshot->finalUrl),
                    'normalizedAcceptedUrls' => $this->acceptedUrls($context),
                    'reason' => 'final_url_not_in_expected_or_acceptable_urls',
                ],
                recommendation: 'Ensure product page redirects resolve to the expected product URL or an explicitly acceptable product URL variant.',
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

    private function finalUrlMatchesProduct(DetectionContext $context, string $finalUrl): bool
    {
        return in_array($this->normalizer->normalize($finalUrl), $this->acceptedUrls($context), true);
    }

    /**
     * @return array<int, string>
     */
    private function acceptedUrls(DetectionContext $context): array
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
                'redirects' => $snapshot->redirects,
                'warnings' => $snapshot->warnings,
                'bodyLength' => $snapshot->body === null ? null : strlen($snapshot->body),
            ],
        ];
    }
}
