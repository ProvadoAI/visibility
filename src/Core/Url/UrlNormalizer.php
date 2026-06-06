<?php

declare(strict_types=1);

namespace VisibilityDetector\Core\Url;

final readonly class UrlNormalizer
{
    private const TRACKING_PARAMETERS = [
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
        'gclid',
        'fbclid',
    ];

    public function normalize(string $url): string
    {
        $parts = parse_url($url);

        if ($parts === false) {
            return $url;
        }

        unset($parts['fragment']);

        if (isset($parts['scheme'])) {
            $parts['scheme'] = strtolower($parts['scheme']);
        }

        if (isset($parts['host'])) {
            $parts['host'] = strtolower($parts['host']);
        }

        if (isset($parts['path'])) {
            $parts['path'] = rtrim($parts['path'], '/');
        }

        if (isset($parts['query'])) {
            $parts['query'] = $this->normalizeQuery($parts['query']);

            if ($parts['query'] === '') {
                unset($parts['query']);
            }
        }

        return $this->buildUrl($parts);
    }

    private function normalizeQuery(string $query): string
    {
        $parameters = [];

        foreach (explode('&', $query) as $parameter) {
            $parts = explode('=', $parameter, 2);
            $name = $parts[0];
            $value = $parts[1] ?? '';
            $decodedName = rawurldecode($name);

            if (in_array(strtolower($decodedName), self::TRACKING_PARAMETERS, true)) {
                continue;
            }

            $parameters[] = [
                'raw' => $parameter,
                'name' => $name,
                'value' => $value,
                'decodedName' => $decodedName,
                'decodedValue' => rawurldecode($value),
            ];
        }

        usort(
            $parameters,
            static function (array $left, array $right): int {
                return strcmp($left['decodedName'], $right['decodedName'])
                    ?: strcmp($left['decodedValue'], $right['decodedValue'])
                    ?: strcmp($left['name'], $right['name'])
                    ?: strcmp($left['value'], $right['value'])
                    ?: strcmp($left['raw'], $right['raw']);
            },
        );

        return implode(
            '&',
            array_map(
                static fn (array $parameter): string => $parameter['raw'],
                $parameters,
            ),
        );
    }

    /**
     * @param array<string, mixed> $parts
     */
    private function buildUrl(array $parts): string
    {
        $url = '';

        if (isset($parts['scheme'])) {
            $url .= $parts['scheme'] . ':';
        }

        if (isset($parts['host'])) {
            $url .= '//';

            if (isset($parts['user'])) {
                $url .= $parts['user'];

                if (isset($parts['pass'])) {
                    $url .= ':' . $parts['pass'];
                }

                $url .= '@';
            }

            $url .= $parts['host'];

            if (isset($parts['port'])) {
                $url .= ':' . $parts['port'];
            }
        }

        $url .= $parts['path'] ?? '';

        if (isset($parts['query'])) {
            $url .= '?' . $parts['query'];
        }

        return $url;
    }
}
