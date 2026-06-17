<?php

declare(strict_types=1);

namespace VisibilityDetector\Core\Robots;

/**
 * Deterministic, network-free parser for robots.txt content.
 *
 * Produces a {@see RobotsTxt} value object. Parsing is tolerant: comments are
 * stripped, unknown directives (Crawl-delay, Host, …) are ignored, and malformed
 * or empty input yields an empty rule set with warnings rather than throwing.
 *
 * Grouping follows the Robots Exclusion Protocol: consecutive `User-agent` lines
 * share the rules that follow them, and a `User-agent` line after a rule starts a
 * new group. Rules for a repeated User-Agent token are merged.
 */
final class RobotsTxtParser
{
    public function parse(string $content): RobotsTxt
    {
        /** @var array<string, list<array{type: string, pattern: string}>> $groups */
        $groups = [];
        /** @var list<string> $sitemaps */
        $sitemaps = [];
        /** @var list<string> $warnings */
        $warnings = [];

        /** @var list<string> $currentTokens */
        $currentTokens = [];
        $sawRuleSinceUserAgent = false;

        foreach (preg_split('/\r\n|\r|\n/', $content) ?: [] as $rawLine) {
            $line = $this->stripComment($rawLine);

            if (trim($line) === '') {
                continue;
            }

            $colon = strpos($line, ':');

            if ($colon === false) {
                $warnings[] = 'Ignored unparseable robots.txt line: ' . trim($line);

                continue;
            }

            $field = strtolower(trim(substr($line, 0, $colon)));
            $value = trim(substr($line, $colon + 1));

            switch ($field) {
                case 'user-agent':
                    if ($value === '') {
                        $warnings[] = 'Ignored User-agent line with an empty value.';

                        break;
                    }

                    $token = strtolower($value);

                    // A User-agent line following a rule begins a new group.
                    if ($sawRuleSinceUserAgent) {
                        $currentTokens = [];
                        $sawRuleSinceUserAgent = false;
                    }

                    if (!in_array($token, $currentTokens, true)) {
                        $currentTokens[] = $token;
                    }

                    if (!array_key_exists($token, $groups)) {
                        $groups[$token] = [];
                    }

                    break;

                case 'allow':
                case 'disallow':
                    if ($currentTokens === []) {
                        $warnings[] = 'Ignored ' . $field . ' rule that appears before any User-agent line.';

                        break;
                    }

                    $sawRuleSinceUserAgent = true;

                    // An empty Allow/Disallow value imposes no restriction.
                    if ($value === '') {
                        break;
                    }

                    foreach ($currentTokens as $token) {
                        $groups[$token][] = ['type' => $field, 'pattern' => $value];
                    }

                    break;

                case 'sitemap':
                    if ($value !== '') {
                        $sitemaps[] = $value;
                    }

                    break;

                default:
                    // Crawl-delay, Host, and unknown directives do not affect
                    // allow/disallow evaluation and are intentionally ignored.
                    break;
            }
        }

        return new RobotsTxt($groups, array_values(array_unique($sitemaps)), $warnings);
    }

    private function stripComment(string $line): string
    {
        $hash = strpos($line, '#');

        return $hash === false ? $line : substr($line, 0, $hash);
    }
}
