<?php

declare(strict_types=1);

namespace VisibilityDetector\Core\Page;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Throwable;

final class DomPageParser implements PageParser
{
    private const BODY_SUMMARY_LIMIT = 500;

    public function parse(PageSnapshot $snapshot): ParsedPage
    {
        $url = $snapshot->finalUrl ?: $snapshot->requestedUrl;
        $warnings = [];
        $xRobotsDirectives = $this->extractXRobotsDirectives($snapshot->headers);

        if ($snapshot->body === null || trim($snapshot->body) === '') {
            return $this->emptyParsedPage($url, ['PageSnapshot body is empty; no HTML was available to parse.'], $xRobotsDirectives);
        }

        if ($snapshot->contentType !== null && !$this->isHtmlContentType($snapshot->contentType)) {
            return $this->emptyParsedPage($url, ['PageSnapshot contentType is not HTML: ' . $snapshot->contentType], $xRobotsDirectives);
        }

        if (!class_exists(DOMDocument::class) || !class_exists(DOMXPath::class)) {
            return $this->emptyParsedPage($url, ['PHP DOM extension is not available; HTML could not be parsed.'], $xRobotsDirectives);
        }

        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            $loaded = $document->loadHTML('<?xml encoding="UTF-8">' . $snapshot->body, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
            if (!$loaded) {
                $warnings[] = 'DOMDocument could not fully parse the supplied HTML.';
            }

            $htmlErrors = libxml_get_errors();
            if ($htmlErrors !== []) {
                $warnings[] = 'DOMDocument reported ' . count($htmlErrors) . ' HTML parse issue(s).';
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $xpath = new DOMXPath($document);
        [$jsonLdBlocks, $schemaTypes, $productCandidates, $offerCandidates, $jsonWarnings] = $this->extractJsonLd($xpath);
        $warnings = array_merge($warnings, $jsonWarnings);

        return new ParsedPage(
            url: $url,
            title: $this->firstText($xpath, '//title'),
            metaDescription: $this->firstMetaContent($xpath, 'description'),
            canonicalUrl: $this->firstAttribute($xpath, '//link[contains(concat(" ", normalize-space(translate(@rel, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")), " "), " canonical ")]', 'href'),
            robotsDirectives: $this->splitDirectives($this->firstMetaContent($xpath, 'robots')),
            xRobotsDirectives: $xRobotsDirectives,
            hreflangLinks: $this->extractHreflangLinks($xpath),
            h1: $this->firstText($xpath, '//h1'),
            headings: $this->extractHeadings($xpath),
            links: $this->extractLinks($xpath),
            jsonLdBlocks: $jsonLdBlocks,
            schemaTypes: $schemaTypes,
            productSchemaCandidates: $productCandidates,
            offerSchemaCandidates: $offerCandidates,
            bodyTextSummary: $this->bodyTextSummary($xpath),
            parserWarnings: $warnings,
        );
    }

    /**
     * @return array{0: array<int, mixed>, 1: array<int, string>, 2: array<int, array<string, mixed>>, 3: array<int, array<string, mixed>>, 4: array<int, string>}
     */
    private function extractJsonLd(DOMXPath $xpath): array
    {
        $blocks = [];
        $schemaTypes = [];
        $products = [];
        $offers = [];
        $warnings = [];

        foreach ($xpath->query('//script[contains(translate(@type, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "application/ld+json")]') ?: [] as $index => $script) {
            $json = trim($script->textContent ?? '');

            if ($json === '') {
                continue;
            }

            try {
                $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            } catch (Throwable $exception) {
                $warnings[] = 'Malformed JSON-LD block at index ' . $index . ': ' . $exception->getMessage();
                continue;
            }

            if (!is_array($decoded)) {
                $warnings[] = 'JSON-LD block at index ' . $index . ' did not decode to an object or array.';
                continue;
            }

            $blocks[] = $decoded;
            $this->collectSchemaEvidence($decoded, $schemaTypes, $products, $offers);
        }

        return [$blocks, array_values(array_unique($schemaTypes)), $products, $offers, $warnings];
    }

    /**
     * @param mixed $node
     * @param array<int, string> $schemaTypes
     * @param array<int, array<string, mixed>> $products
     * @param array<int, array<string, mixed>> $offers
     */
    private function collectSchemaEvidence(mixed $node, array &$schemaTypes, array &$products, array &$offers): void
    {
        if (!is_array($node)) {
            return;
        }

        if ($this->isList($node)) {
            foreach ($node as $item) {
                $this->collectSchemaEvidence($item, $schemaTypes, $products, $offers);
            }

            return;
        }

        $types = $this->schemaTypesFromNode($node);
        foreach ($types as $type) {
            $schemaTypes[] = $type;
        }

        if ($this->containsType($types, 'Product')) {
            $products[] = $node;
        }

        if ($this->containsType($types, 'Offer')) {
            $offers[] = $node;
        }

        foreach ($node as $value) {
            $this->collectSchemaEvidence($value, $schemaTypes, $products, $offers);
        }
    }

    /**
     * @param array<string, mixed> $node
     * @return array<int, string>
     */
    private function schemaTypesFromNode(array $node): array
    {
        if (!array_key_exists('@type', $node)) {
            return [];
        }

        $type = $node['@type'];
        if (is_string($type) && trim($type) !== '') {
            return [trim($type)];
        }

        if (is_array($type)) {
            return array_values(array_filter(array_map(
                static fn (mixed $value): ?string => is_string($value) && trim($value) !== '' ? trim($value) : null,
                $type,
            )));
        }

        return [];
    }

    /**
     * @param array<int, string> $types
     */
    private function containsType(array $types, string $expected): bool
    {
        foreach ($types as $type) {
            $shortType = strtolower(str_contains($type, '/') ? basename($type) : $type);
            if ($shortType === strtolower($expected)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, array{hreflang: string, url: string}>
     */
    private function extractHreflangLinks(DOMXPath $xpath): array
    {
        $links = [];
        foreach ($xpath->query('//link[contains(concat(" ", normalize-space(translate(@rel, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")), " "), " alternate ")][@hreflang][@href]') ?: [] as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }

            $hreflang = trim($node->getAttribute('hreflang'));
            $href = trim($node->getAttribute('href'));
            if ($hreflang !== '' && $href !== '') {
                $links[] = ['hreflang' => $hreflang, 'url' => $href];
            }
        }

        return $links;
    }

    /**
     * @return array<int, array{level: int, text: string}>
     */
    private function extractHeadings(DOMXPath $xpath): array
    {
        $headings = [];
        foreach ($xpath->query('//h1 | //h2 | //h3 | //h4 | //h5 | //h6') ?: [] as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }

            $text = $this->normalizeText($node->textContent ?? '');
            if ($text !== '') {
                $headings[] = ['level' => (int) substr($node->tagName, 1), 'text' => $text];
            }
        }

        return $headings;
    }

    /**
     * @return array<int, array{url: string, text: string, rel: ?string}>
     */
    private function extractLinks(DOMXPath $xpath): array
    {
        $links = [];
        foreach ($xpath->query('//a[@href]') ?: [] as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }

            $href = trim($node->getAttribute('href'));
            if ($href === '') {
                continue;
            }

            $rel = trim($node->getAttribute('rel'));
            $links[] = [
                'url' => $href,
                'text' => $this->normalizeText($node->textContent ?? ''),
                'rel' => $rel !== '' ? $rel : null,
            ];
        }

        return $links;
    }

    /**
     * @param array<string, mixed> $headers
     * @return array<int, string>
     */
    private function extractXRobotsDirectives(array $headers): array
    {
        $directives = [];

        foreach ($headers as $name => $values) {
            if (strtolower((string) $name) !== 'x-robots-tag') {
                continue;
            }

            foreach ((array) $values as $value) {
                if (is_scalar($value)) {
                    $directives = array_merge($directives, $this->splitDirectives((string) $value));
                }
            }
        }

        return array_values(array_unique($directives));
    }

    private function firstMetaContent(DOMXPath $xpath, string $name): ?string
    {
        return $this->firstAttribute($xpath, '//meta[translate(@name, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz") = "' . strtolower($name) . '"]', 'content');
    }

    private function firstText(DOMXPath $xpath, string $query): ?string
    {
        $nodes = $xpath->query($query);
        if ($nodes === false || $nodes->length === 0) {
            return null;
        }

        $text = $this->normalizeText($nodes->item(0)?->textContent ?? '');

        return $text !== '' ? $text : null;
    }

    private function firstAttribute(DOMXPath $xpath, string $query, string $attribute): ?string
    {
        $nodes = $xpath->query($query);
        if ($nodes === false || $nodes->length === 0) {
            return null;
        }

        $node = $nodes->item(0);
        if (!$node instanceof DOMElement) {
            return null;
        }

        $value = trim($node->getAttribute($attribute));

        return $value !== '' ? $value : null;
    }

    /**
     * @return array<int, string>
     */
    private function splitDirectives(?string $value): array
    {
        if ($value === null || trim($value) === '') {
            return [];
        }

        $directives = [];
        $current = '';
        $unavailableAfterDateCommaSeen = false;

        foreach (str_split($value) as $character) {
            if ($character === ',' || $character === ';') {
                if ($this->isUnavailableAfterDirectivePrefix($current) && $character === ',' && !$unavailableAfterDateCommaSeen) {
                    $current .= $character;
                    $unavailableAfterDateCommaSeen = true;
                    continue;
                }

                $directives[] = trim($current);
                $current = '';
                $unavailableAfterDateCommaSeen = false;
                continue;
            }

            $current .= $character;
        }

        $directives[] = trim($current);

        return array_values(array_unique(array_filter($directives)));
    }

    private function isUnavailableAfterDirectivePrefix(string $directive): bool
    {
        $normalized = strtolower(trim($directive));

        if (str_starts_with($normalized, 'unavailable_after:')) {
            return true;
        }

        $parts = explode(':', $normalized, 2);

        return count($parts) === 2
            && trim($parts[0]) !== ''
            && str_starts_with(trim($parts[1]), 'unavailable_after:');
    }

    private function bodyTextSummary(DOMXPath $xpath): ?string
    {
        foreach ($xpath->query('//script | //style | //noscript') ?: [] as $node) {
            $node->parentNode?->removeChild($node);
        }

        $text = $this->firstText($xpath, '//body');
        if ($text === null) {
            return null;
        }

        if (strlen($text) <= self::BODY_SUMMARY_LIMIT) {
            return $text;
        }

        return rtrim(substr($text, 0, self::BODY_SUMMARY_LIMIT));
    }

    private function normalizeText(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }

    private function isHtmlContentType(string $contentType): bool
    {
        return str_contains(strtolower($contentType), 'html');
    }

    /**
     * @param array<int, string> $warnings
     */
    private function emptyParsedPage(string $url, array $warnings, array $xRobotsDirectives = []): ParsedPage
    {
        return new ParsedPage(url: $url, xRobotsDirectives: $xRobotsDirectives, parserWarnings: $warnings);
    }

    /**
     * @param array<mixed> $array
     */
    private function isList(array $array): bool
    {
        return array_is_list($array);
    }
}
