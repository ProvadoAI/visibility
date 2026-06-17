<?php

declare(strict_types=1);

namespace VisibilityDetector\Core\Robots;

/**
 * Outcome of evaluating a single URL against parsed robots.txt rules for a
 * specific User-Agent.
 *
 * Deterministic, network-free evidence: it records whether the URL is allowed,
 * which User-Agent group applied, and the rule (if any) that decided the result.
 */
final readonly class RobotsEvidence
{
    /**
     * @param string      $url          The URL that was evaluated.
     * @param string      $userAgent    The User-Agent the evaluation was made for.
     * @param bool        $allowed      Whether crawling the URL is allowed.
     * @param string|null $matchedGroup The User-Agent token whose group applied (e.g. `googlebot` or `*`), or null when no group matched.
     * @param string|null $directive    The deciding directive: `allow`, `disallow`, or null when no rule matched.
     * @param string|null $matchedPattern The deciding rule pattern (e.g. `/private`), or null when no rule matched.
     * @param list<string> $warnings    Non-fatal parser warnings carried alongside the evidence.
     */
    public function __construct(
        public string $url,
        public string $userAgent,
        public bool $allowed,
        public ?string $matchedGroup = null,
        public ?string $directive = null,
        public ?string $matchedPattern = null,
        public array $warnings = [],
    ) {
    }

    /**
     * The deciding rule rendered as a robots.txt line (e.g. `Disallow: /private`),
     * or null when no rule matched.
     */
    public function matchedRule(): ?string
    {
        if ($this->directive === null || $this->matchedPattern === null) {
            return null;
        }

        return ucfirst($this->directive) . ': ' . $this->matchedPattern;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'url' => $this->url,
            'userAgent' => $this->userAgent,
            'allowed' => $this->allowed,
            'matchedGroup' => $this->matchedGroup,
            'directive' => $this->directive,
            'matchedPattern' => $this->matchedPattern,
            'matchedRule' => $this->matchedRule(),
            'warnings' => $this->warnings,
        ];
    }
}
