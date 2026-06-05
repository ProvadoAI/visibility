<?php

declare(strict_types=1);

use MQuevedoB\Visibility\Visibility;
use PHPUnit\Framework\TestCase;

final class VisibilityTest extends TestCase
{
    public function test_it_returns_basic_analysis_shape(): void
    {
        $result = (new Visibility())->analyzeUrl('https://example.com/product');

        $this->assertSame('https://example.com/product', $result['url']);
        $this->assertArrayHasKey('signals', $result);
        $this->assertArrayHasKey('issues', $result);
        $this->assertArrayHasKey('recommendations', $result);
    }
}
