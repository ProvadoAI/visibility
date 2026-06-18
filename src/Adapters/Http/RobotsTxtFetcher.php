<?php

declare(strict_types=1);

namespace VisibilityDetector\Adapters\Http;

use VisibilityDetector\Core\Page\PageFetcher;
use VisibilityDetector\Core\Robots\RobotsEvidence;
use VisibilityDetector\Core\Robots\RobotsEvidenceProvider;
use VisibilityDetector\Core\Robots\RobotsTxtParser;

/**
 * Acquires robots.txt over HTTP and evaluates whether a URL is crawlable.
 *
 * Reuses any {@see PageFetcher} (typically {@see Psr18PageFetcher} with a
 * {@see FetchPolicy}) to fetch `scheme://host/robots.txt`, then parses and
 * evaluates it with the deterministic {@see RobotsTxtParser}. A missing,
 * failed, non-2xx, or empty robots.txt is treated as "not blocked" with a
 * warning — never a fatal error — matching the convention that the absence of
 * robots.txt permits crawling.
 *
 * The User-Agent used for evaluation should match the one the page fetcher
 * sends (i.e. the FetchPolicy User-Agent) so robots groups are selected for the
 * same crawler identity.
 */
final readonly class RobotsTxtFetcher implements RobotsEvidenceProvider
{
    private RobotsTxtParser $parser;

    public function __construct(
        private PageFetcher $pageFetcher,
        private string $userAgent = FetchPolicy::DEFAULT_USER_AGENT,
        ?RobotsTxtParser $parser = null,
    ) {
        $this->parser = $parser ?? new RobotsTxtParser();
    }

    public function evidenceFor(string $url): RobotsEvidence
    {
        $robotsUrl = $this->robotsUrlFor($url);

        if ($robotsUrl === null) {
            return new RobotsEvidence(
                url: $url,
                userAgent: $this->userAgent,
                allowed: true,
                warnings: ['Could not derive a robots.txt URL for: ' . $url],
            );
        }

        $snapshot = $this->pageFetcher->fetch($robotsUrl);

        if (!$this->isFetchSuccessful($snapshot->failureType, $snapshot->statusCode)) {
            return new RobotsEvidence(
                url: $url,
                userAgent: $this->userAgent,
                allowed: true,
                warnings: [sprintf(
                    'robots.txt at %s was unavailable (status: %s, failure: %s); treated as not blocked.',
                    $robotsUrl,
                    $snapshot->statusCode ?? 'none',
                    $snapshot->failureType ?? 'none',
                )],
            );
        }

        // A successful fetch with an empty body is a valid "allow all" robots.txt,
        // so it flows through the parser (which yields no rules -> allowed) rather
        // than being misreported as unavailable.
        return $this->parser->parse((string) ($snapshot->body ?? ''))->evaluate($url, $this->userAgent);
    }

    private function isFetchSuccessful(?string $failureType, ?int $statusCode): bool
    {
        if ($failureType !== null && $failureType !== 'none') {
            return false;
        }

        return $statusCode !== null && $statusCode >= 200 && $statusCode < 300;
    }

    private function robotsUrlFor(string $url): ?string
    {
        $parts = parse_url($url);

        if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $authority = $parts['scheme'] . '://' . $parts['host'];

        if (isset($parts['port'])) {
            $authority .= ':' . $parts['port'];
        }

        return $authority . '/robots.txt';
    }
}
