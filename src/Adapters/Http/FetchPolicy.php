<?php

declare(strict_types=1);

namespace VisibilityDetector\Adapters\Http;

use InvalidArgumentException;

/**
 * Deterministic safety policy for live HTTP fetching.
 *
 * Bounds and gates what {@see Psr18PageFetcher} is allowed to do: how long a
 * fetch may take, how many redirects to follow, how large a response body to
 * read, which User-Agent to send, and which hosts may be requested at all.
 *
 * The defaults are deliberately conservative so that opting into live fetching
 * is safe without further configuration.
 *
 * Note on timeouts: PSR-18 has no transport-level configuration hook and the
 * fetcher cannot interrupt an in-flight request, so the caller MUST apply
 * {@see $connectTimeoutSeconds}/{@see $timeoutSeconds} to their concrete client;
 * a client timeout surfaces as a network exception that the fetcher maps to
 * `timeout`. In addition, the fetcher enforces {@see $timeoutSeconds} as a
 * wall-clock budget *between* requests, so a redirect chain that has already
 * exceeded the budget is stopped with `timeout` before the next hop is sent.
 */
final readonly class FetchPolicy
{
    /** Default total fetch budget, in seconds. */
    public const DEFAULT_TIMEOUT_SECONDS = 10.0;

    /** Default connect timeout, in seconds (advisory; applied by the caller's client). */
    public const DEFAULT_CONNECT_TIMEOUT_SECONDS = 5.0;

    /** Default maximum number of redirects to follow. */
    public const DEFAULT_MAX_REDIRECTS = 5;

    /** Default maximum response body size to read, in bytes (5 MiB). */
    public const DEFAULT_MAX_BODY_BYTES = 5_242_880;

    /** Default User-Agent sent with every request. */
    public const DEFAULT_USER_AGENT = 'visibility-detector/0.4';

    /**
     * @param list<string> $allowedHosts Exact host names permitted. Empty means "any host" (subject to the denylist).
     * @param list<string> $deniedHosts  Exact host names always refused. Takes precedence over the allowlist.
     */
    public function __construct(
        public float $timeoutSeconds = self::DEFAULT_TIMEOUT_SECONDS,
        public float $connectTimeoutSeconds = self::DEFAULT_CONNECT_TIMEOUT_SECONDS,
        public int $maxRedirects = self::DEFAULT_MAX_REDIRECTS,
        public int $maxBodyBytes = self::DEFAULT_MAX_BODY_BYTES,
        public string $userAgent = self::DEFAULT_USER_AGENT,
        public array $allowedHosts = [],
        public array $deniedHosts = [],
    ) {
        if ($timeoutSeconds <= 0) {
            throw new InvalidArgumentException('timeoutSeconds must be greater than 0.');
        }

        if ($connectTimeoutSeconds < 0) {
            throw new InvalidArgumentException('connectTimeoutSeconds must be greater than or equal to 0.');
        }

        if ($maxRedirects < 0) {
            throw new InvalidArgumentException('maxRedirects must be greater than or equal to 0.');
        }

        if ($maxBodyBytes <= 0) {
            throw new InvalidArgumentException('maxBodyBytes must be greater than 0.');
        }

        if (trim($userAgent) === '') {
            throw new InvalidArgumentException('userAgent must not be empty.');
        }

        $this->assertHostList($allowedHosts, 'allowedHosts');
        $this->assertHostList($deniedHosts, 'deniedHosts');
    }

    public static function default(): self
    {
        return new self();
    }

    /**
     * Decide whether a host may be requested under this policy.
     *
     * A request to a host with no parseable name, or one on the denylist, is
     * always refused. When an allowlist is configured, only hosts on it are
     * permitted; an empty allowlist permits any host not on the denylist.
     */
    public function isHostAllowed(string $host): bool
    {
        $host = strtolower(trim($host));

        if ($host === '') {
            return false;
        }

        if (in_array($host, $this->normalizedHosts($this->deniedHosts), true)) {
            return false;
        }

        if ($this->allowedHosts === []) {
            return true;
        }

        return in_array($host, $this->normalizedHosts($this->allowedHosts), true);
    }

    /**
     * @param array<int, mixed> $hosts
     */
    private function assertHostList(array $hosts, string $field): void
    {
        foreach ($hosts as $host) {
            if (!is_string($host) || trim($host) === '') {
                throw new InvalidArgumentException($field . ' must contain only non-empty strings.');
            }
        }
    }

    /**
     * @param list<string> $hosts
     * @return list<string>
     */
    private function normalizedHosts(array $hosts): array
    {
        return array_map(static fn (string $host): string => strtolower(trim($host)), $hosts);
    }
}
