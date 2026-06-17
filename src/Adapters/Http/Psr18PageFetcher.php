<?php

declare(strict_types=1);

namespace VisibilityDetector\Adapters\Http;

use Closure;
use InvalidArgumentException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Client\RequestExceptionInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriFactoryInterface;
use Throwable;
use VisibilityDetector\Core\Page\PageFetcher;
use VisibilityDetector\Core\Page\PageSnapshot;

/**
 * Real HTTP page fetcher built on PSR HTTP abstractions only.
 *
 * The caller injects any PSR-18 client (Guzzle, Symfony HttpClient, etc.) and
 * PSR-17 factories; this class never depends on a concrete client. It captures
 * transport evidence into a PageSnapshot and never throws on a network or HTTP
 * outcome — failures are mapped onto the PageSnapshot failureType taxonomy so the
 * detectors can interpret them.
 *
 * Live fetching is bounded and gated by a deterministic {@see FetchPolicy}:
 * a redirect cap, a maximum body size, a configurable User-Agent, an optional
 * host allow/deny list, and a wall-clock time budget enforced *between* requests
 * (an in-flight request is bounded by the client's own timeout, surfaced as a
 * network exception mapped to `timeout`). Exceeding a bound produces a controlled
 * PageSnapshot (warnings and/or an appropriate failureType), never an uncaught
 * exception. Redirects are followed manually (not delegated to the client) so the
 * chain is captured deterministically and stays bounded.
 */
final readonly class Psr18PageFetcher implements PageFetcher
{
    private const DEFAULT_ACCEPT = 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8';

    private const REDIRECT_STATUS_CODES = [301, 302, 303, 307, 308];

    private const BODY_READ_CHUNK_BYTES = 8192;

    private Closure $clock;

    /**
     * @param callable():float|null $clock Returns the current time in seconds (for the time budget). Defaults to microtime(true).
     */
    public function __construct(
        private ClientInterface $client,
        private RequestFactoryInterface $requestFactory,
        private UriFactoryInterface $uriFactory,
        private FetchPolicy $policy = new FetchPolicy(),
        ?callable $clock = null,
    ) {
        $this->clock = $clock !== null
            ? Closure::fromCallable($clock)
            : static fn (): float => microtime(true);
    }

    public function fetch(string $url): PageSnapshot
    {
        if (trim($url) === '') {
            throw new InvalidArgumentException('url must not be empty.');
        }

        $startedAt = ($this->clock)();
        $requestedUrl = $url;
        $currentUrl = $url;
        $redirects = [];
        $warnings = [];
        $redirectCount = 0;

        while (true) {
            $host = $this->hostOf($currentUrl);

            if ($host === '') {
                return $this->failureSnapshot(
                    requestedUrl: $requestedUrl,
                    redirects: $redirects,
                    durationMs: $this->durationMs($startedAt),
                    failureType: 'invalid_response',
                    warnings: $warnings,
                    message: sprintf('Could not determine a host for URL: %s', $currentUrl),
                );
            }

            if (!$this->policy->isHostAllowed($host)) {
                return $this->blockedSnapshot($requestedUrl, $currentUrl, $redirects, $this->durationMs($startedAt), $host);
            }

            if ($this->elapsedSeconds($startedAt) >= $this->policy->timeoutSeconds) {
                return $this->failureSnapshot(
                    requestedUrl: $requestedUrl,
                    redirects: $redirects,
                    durationMs: $this->durationMs($startedAt),
                    failureType: 'timeout',
                    warnings: $warnings,
                    message: sprintf('Total fetch time budget of %ss exceeded before requesting %s.', $this->policy->timeoutSeconds, $currentUrl),
                );
            }

            try {
                $request = $this->requestFactory
                    ->createRequest('GET', $this->uriFactory->createUri($currentUrl))
                    ->withHeader('User-Agent', $this->policy->userAgent)
                    ->withHeader('Accept', self::DEFAULT_ACCEPT);

                $response = $this->client->sendRequest($request);
            } catch (ClientExceptionInterface $exception) {
                return $this->failureSnapshot(
                    requestedUrl: $requestedUrl,
                    redirects: $redirects,
                    durationMs: $this->durationMs($startedAt),
                    failureType: $this->classifyException($exception),
                    warnings: $warnings,
                    message: $exception->getMessage(),
                );
            }

            $statusCode = $response->getStatusCode();
            $location = trim($response->getHeaderLine('Location'));

            if ($this->isRedirect($statusCode) && $location !== '') {
                if ($redirectCount >= $this->policy->maxRedirects) {
                    $warnings[] = sprintf(
                        'Maximum redirect count (%d) reached; stopped following redirects at %s.',
                        $this->policy->maxRedirects,
                        $currentUrl,
                    );

                    return $this->responseSnapshot($requestedUrl, $currentUrl, $response, $redirects, $this->durationMs($startedAt), $warnings);
                }

                $target = $this->resolveLocation($currentUrl, $location);
                $redirects[] = [
                    'from' => $currentUrl,
                    'to' => $target,
                    'statusCode' => $statusCode,
                ];
                $currentUrl = $target;
                ++$redirectCount;

                continue;
            }

            return $this->responseSnapshot($requestedUrl, $currentUrl, $response, $redirects, $this->durationMs($startedAt), $warnings);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $redirects
     * @param array<int, string> $warnings
     */
    private function responseSnapshot(string $requestedUrl, string $finalUrl, ResponseInterface $response, array $redirects, int $durationMs, array $warnings): PageSnapshot
    {
        $contentType = trim($response->getHeaderLine('Content-Type'));

        try {
            $body = $this->readBoundedBody($response->getBody(), $warnings);
        } catch (Throwable $exception) {
            // PSR-7 streams may throw while reading the body (e.g. a connection
            // reset mid-transfer). Capture it as transport evidence rather than
            // letting it escape fetch().
            return $this->failureSnapshot(
                requestedUrl: $requestedUrl,
                redirects: $redirects,
                durationMs: $durationMs,
                failureType: 'invalid_response',
                warnings: $warnings,
                message: 'Failed to read response body: ' . $exception->getMessage(),
            );
        }

        return new PageSnapshot(
            requestedUrl: $requestedUrl,
            finalUrl: $finalUrl,
            statusCode: $response->getStatusCode(),
            headers: $this->normalizeHeaders($response->getHeaders()),
            body: $body,
            contentType: $contentType === '' ? null : $contentType,
            redirects: $redirects,
            durationMs: $durationMs,
            // Transport succeeded even for non-2xx; the HttpAvailabilityDetector
            // interprets the statusCode. failureType is reserved for transport failures.
            failureType: 'none',
            warnings: array_values($warnings),
        );
    }

    /**
     * @param array<int, array<string, mixed>> $redirects
     * @param array<int, string> $warnings
     */
    private function failureSnapshot(string $requestedUrl, array $redirects, int $durationMs, string $failureType, array $warnings, string $message): PageSnapshot
    {
        $warnings[] = sprintf('HTTP fetch failed (%s): %s', $failureType, $message);

        return new PageSnapshot(
            requestedUrl: $requestedUrl,
            finalUrl: null,
            statusCode: null,
            headers: [],
            body: null,
            contentType: null,
            redirects: $redirects,
            durationMs: $durationMs,
            failureType: $failureType,
            warnings: array_values($warnings),
        );
    }

    /**
     * @param array<int, array<string, mixed>> $redirects
     */
    private function blockedSnapshot(string $requestedUrl, string $refusedUrl, array $redirects, int $durationMs, string $host): PageSnapshot
    {
        return new PageSnapshot(
            requestedUrl: $requestedUrl,
            finalUrl: null,
            statusCode: null,
            headers: [],
            body: null,
            contentType: null,
            redirects: $redirects,
            durationMs: $durationMs,
            failureType: 'blocked',
            warnings: [sprintf(
                'Host "%s" was refused by the fetch policy; no request was made to %s.',
                $host,
                $refusedUrl,
            )],
        );
    }

    /**
     * Read at most maxBodyBytes from the response body, recording a warning when
     * the body is larger and gets truncated.
     *
     * @param array<int, string> $warnings
     */
    private function readBoundedBody(StreamInterface $stream, array &$warnings): ?string
    {
        $maxBytes = $this->policy->maxBodyBytes;

        if ($stream->isSeekable()) {
            $stream->rewind();
        }

        $buffer = '';

        while (!$stream->eof() && strlen($buffer) <= $maxBytes) {
            $chunk = $stream->read(self::BODY_READ_CHUNK_BYTES);

            if ($chunk === '') {
                break;
            }

            $buffer .= $chunk;
        }

        if (strlen($buffer) > $maxBytes) {
            $buffer = substr($buffer, 0, $maxBytes);
            $warnings[] = sprintf('Response body exceeded the maximum of %d bytes and was truncated.', $maxBytes);
        }

        return $buffer === '' ? null : $buffer;
    }

    /**
     * @param array<string, array<int, string>> $headers
     * @return array<string, array<int, string>>
     */
    private function normalizeHeaders(array $headers): array
    {
        $normalized = [];

        foreach ($headers as $name => $values) {
            $normalized[strtolower((string) $name)] = array_values((array) $values);
        }

        return $normalized;
    }

    private function classifyException(ClientExceptionInterface $exception): string
    {
        if ($exception instanceof NetworkExceptionInterface) {
            return $this->classifyNetworkMessage($exception->getMessage());
        }

        if ($exception instanceof RequestExceptionInterface) {
            return 'invalid_response';
        }

        return 'unknown';
    }

    private function classifyNetworkMessage(string $message): string
    {
        $normalized = strtolower($message);

        return match (true) {
            str_contains($normalized, 'timed out'), str_contains($normalized, 'timeout') => 'timeout',
            str_contains($normalized, 'could not resolve'),
            str_contains($normalized, 'name resolution'),
            str_contains($normalized, 'name or service not known'),
            str_contains($normalized, 'no address associated'),
            str_contains($normalized, 'dns') => 'dns_not_found',
            str_contains($normalized, 'connection refused') => 'connection_refused',
            str_contains($normalized, 'ssl'),
            str_contains($normalized, 'certificate'),
            str_contains($normalized, 'tls') => 'ssl_error',
            default => 'unknown',
        };
    }

    private function isRedirect(int $statusCode): bool
    {
        return in_array($statusCode, self::REDIRECT_STATUS_CODES, true);
    }

    private function hostOf(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) ? $host : '';
    }

    private function resolveLocation(string $base, string $location): string
    {
        $parts = parse_url($location);

        if (is_array($parts) && isset($parts['scheme'])) {
            return $location;
        }

        $baseParts = parse_url($base);

        if ($baseParts === false) {
            return $location;
        }

        $scheme = $baseParts['scheme'] ?? 'https';

        if (str_starts_with($location, '//')) {
            return $scheme . ':' . $location;
        }

        $authority = $scheme . '://' . ($baseParts['host'] ?? '');

        if (isset($baseParts['port'])) {
            $authority .= ':' . $baseParts['port'];
        }

        if (str_starts_with($location, '/')) {
            return $authority . $location;
        }

        $basePath = $baseParts['path'] ?? '/';
        $slashPosition = strrpos($basePath, '/');
        $directory = $slashPosition === false ? '/' : substr($basePath, 0, $slashPosition + 1);

        if ($directory === '') {
            $directory = '/';
        }

        return $authority . $directory . $location;
    }

    private function elapsedSeconds(float $startedAt): float
    {
        return ($this->clock)() - $startedAt;
    }

    private function durationMs(float $startedAt): int
    {
        return max(0, (int) round($this->elapsedSeconds($startedAt) * 1000));
    }
}
