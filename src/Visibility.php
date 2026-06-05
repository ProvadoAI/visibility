<?php

declare(strict_types=1);

namespace MQuevedoB\Visibility;

final class Visibility
{
    public function analyzeUrl(string $url): array
    {
        return [
            'url' => $url,
            'visibility_status' => 'unknown',
            'signals' => [],
            'issues' => [],
            'recommendations' => [],
        ];
    }
}
