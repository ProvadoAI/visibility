<?php

declare(strict_types=1);

namespace VisibilityDetector\Core\Robots;

/**
 * Parsed, immutable representation of a robots.txt document.
 *
 * Holds the rule groups keyed by lowercased User-Agent token, any discovered
 * Sitemap URLs, and non-fatal parser warnings. Evaluation is deterministic and
 * network-free: {@see evaluate()} decides whether a URL is allowed for a given
 * User-Agent using REP-style precedence (most-specific group, longest-match
 * rule, Allow wins ties), honoring `*` wildcards and `$` end-anchors.
 */
final readonly class RobotsTxt
{
    /**
     * @param array<string, list<array{type: string, pattern: string}>> $groups Rules keyed by lowercased User-Agent token.
     * @param list<string> $sitemaps Sitemap URLs declared in the document.
     * @param list<string> $warnings Non-fatal parser warnings.
     */
    public function __construct(
        private array $groups,
        public array $sitemaps = [],
        public array $warnings = [],
    ) {
    }

    public function evaluate(string $url, string $userAgent): RobotsEvidence
    {
        $path = $this->pathOf($url);
        [$matchedGroup, $rules] = $this->selectGroup($userAgent);

        /** @var array{type: string, pattern: string, length: int}|null $best */
        $best = null;

        foreach ($rules as $rule) {
            if (!$this->matches($rule['pattern'], $path)) {
                continue;
            }

            $length = $this->patternLength($rule['pattern']);

            // Longest-match wins; on an equal-length tie an Allow overrides a Disallow.
            if (
                $best === null
                || $length > $best['length']
                || ($length === $best['length'] && $rule['type'] === 'allow' && $best['type'] === 'disallow')
            ) {
                $best = ['type' => $rule['type'], 'pattern' => $rule['pattern'], 'length' => $length];
            }
        }

        $disallowed = $best !== null && $best['type'] === 'disallow';

        return new RobotsEvidence(
            url: $url,
            userAgent: $userAgent,
            allowed: !$disallowed,
            matchedGroup: $matchedGroup,
            directive: $best['type'] ?? null,
            matchedPattern: $best['pattern'] ?? null,
            warnings: $this->warnings,
        );
    }

    public function isAllowed(string $url, string $userAgent): bool
    {
        return $this->evaluate($url, $userAgent)->allowed;
    }

    /**
     * @return list<string>
     */
    public function userAgents(): array
    {
        return array_keys($this->groups);
    }

    /**
     * Select the most specific group for a User-Agent: the longest token that is
     * a case-insensitive prefix of the User-Agent product token, falling back to
     * the `*` group, then to no rules.
     *
     * @return array{0: string|null, 1: list<array{type: string, pattern: string}>}
     */
    private function selectGroup(string $userAgent): array
    {
        $needle = strtolower($userAgent);
        $bestToken = null;
        $bestLength = -1;

        foreach ($this->groups as $token => $rules) {
            if ($token === '*' || $token === '') {
                continue;
            }

            if (str_starts_with($needle, $token) && strlen($token) > $bestLength) {
                $bestToken = $token;
                $bestLength = strlen($token);
            }
        }

        if ($bestToken !== null) {
            return [$bestToken, $this->groups[$bestToken]];
        }

        if (array_key_exists('*', $this->groups)) {
            return ['*', $this->groups['*']];
        }

        return [null, []];
    }

    private function pathOf(string $url): string
    {
        $parts = parse_url($url);

        if ($parts === false) {
            return '/';
        }

        $path = $parts['path'] ?? '/';

        if ($path === '') {
            $path = '/';
        }

        if (isset($parts['query']) && $parts['query'] !== '') {
            $path .= '?' . $parts['query'];
        }

        return $path;
    }

    /**
     * Match a robots.txt pattern against a URL path. An empty pattern matches
     * nothing (an empty Disallow imposes no restriction). `*` matches any run of
     * characters; a trailing `$` anchors the match to the end of the path.
     */
    private function matches(string $pattern, string $path): bool
    {
        if ($pattern === '') {
            return false;
        }

        $anchored = str_ends_with($pattern, '$');
        $core = $anchored ? substr($pattern, 0, -1) : $pattern;

        $quoted = preg_quote($core, '#');
        $quoted = str_replace('\*', '.*', $quoted);
        $regex = '#^' . $quoted . ($anchored ? '$' : '') . '#';

        return preg_match($regex, $path) === 1;
    }

    /**
     * Length used for longest-match precedence. The trailing `$` end-anchor is an
     * operator, not matched path content, so it must not inflate specificity
     * (otherwise `Disallow: /p$` would outrank an equal `Allow: /p`).
     */
    private function patternLength(string $pattern): int
    {
        return strlen(str_ends_with($pattern, '$') ? substr($pattern, 0, -1) : $pattern);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'groups' => $this->groups,
            'sitemaps' => $this->sitemaps,
            'warnings' => $this->warnings,
        ];
    }
}
