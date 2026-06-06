<?php

declare(strict_types=1);

namespace VisibilityDetector\Core\Page;

interface PageParser
{
    public function parse(PageSnapshot $snapshot): ParsedPage;
}
