<?php

declare(strict_types=1);

require __DIR__ . '/run-analysis.php';

runExampleAnalysis(
    __DIR__ . '/fixtures/search-results-acceptable-variant.json',
    ['https://example.test/p/aurora-trail-shoe'],
);
