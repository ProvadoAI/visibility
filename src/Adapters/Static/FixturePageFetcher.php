<?php

declare(strict_types=1);

namespace VisibilityDetector\Adapters\Static;

use InvalidArgumentException;
use VisibilityDetector\Core\Page\PageFetcher;
use VisibilityDetector\Core\Page\PageSnapshot;

final class FixturePageFetcher implements PageFetcher
{
    /** @var array<string, PageSnapshot> */
    private array $snapshotsByRequestedUrl = [];

    /**
     * @param array<int|string, PageSnapshot|array<string, mixed>> $fixtures
     */
    public function __construct(array $fixtures = [])
    {
        foreach ($fixtures as $url => $fixture) {
            if (is_array($fixture)) {
                if (is_string($url) && !array_key_exists('requestedUrl', $fixture)) {
                    $fixture['requestedUrl'] = $url;
                }

                $fixture = PageSnapshot::fromArray($fixture);
            }

            if (!$fixture instanceof PageSnapshot) {
                throw new InvalidArgumentException('fixtures must contain only PageSnapshot objects or arrays.');
            }

            $this->snapshotsByRequestedUrl[$fixture->requestedUrl] = $fixture;
        }
    }

    public function fetch(string $url): PageSnapshot
    {
        if (trim($url) === '') {
            throw new InvalidArgumentException('url must not be empty.');
        }

        return $this->snapshotsByRequestedUrl[$url] ?? new PageSnapshot(
            requestedUrl: $url,
            finalUrl: null,
            statusCode: null,
            headers: [],
            body: null,
            contentType: null,
            redirects: [],
            durationMs: null,
            failureType: 'unknown',
            warnings: ['No page fixture was configured for requested URL: ' . $url],
        );
    }
}
