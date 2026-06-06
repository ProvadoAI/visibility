<?php

declare(strict_types=1);

namespace VisibilityDetector\Core\Page;

interface PageFetcher
{
    public function fetch(string $url): PageSnapshot;
}
