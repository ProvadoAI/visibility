<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use VisibilityDetector\Visibility;

final class VisibilityTest extends TestCase
{
    public function test_package_smoke_test(): void
    {
        self::assertTrue(class_exists(Visibility::class));
    }
}
