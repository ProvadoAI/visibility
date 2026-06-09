<?php

declare(strict_types=1);

namespace VisibilityDetector\Cli;

use Throwable;
use VisibilityDetector\Adapters\Static\FixturePageFetcher;
use VisibilityDetector\Adapters\Static\StaticSearchProvider;
use VisibilityDetector\Core\Analyzer\VisibilityAnalyzer;
use VisibilityDetector\Core\Detector\VisibilityResultDetector;
use VisibilityDetector\Core\Page\DomPageParser;
use VisibilityDetector\Core\Report\JsonReportSerializer;
use VisibilityDetector\Core\Url\DefaultUrlMatcher;

final readonly class VisibilityCli
{
    public function __construct(
        private ScenarioLoader $scenarioLoader,
    ) {
    }

    /**
     * @param array<int, string> $argv
     * @param resource|null $stdout
     * @param resource|null $stderr
     */
    public function run(array $argv, mixed $stdout = null, mixed $stderr = null): int
    {
        $stdout ??= STDOUT;
        $stderr ??= STDERR;
        $command = $argv[1] ?? null;

        if ($command !== 'analyze') {
            $this->write($stderr, $this->usage($command === null ? 'Missing command.' : 'Unknown command: ' . $command));

            return 1;
        }

        $scenarioPath = $argv[2] ?? null;

        if ($scenarioPath === null || trim($scenarioPath) === '') {
            $this->write($stderr, $this->usage('Missing scenario JSON path.'));

            return 1;
        }

        if (isset($argv[3])) {
            $this->write($stderr, $this->usage('Too many arguments.'));

            return 1;
        }

        try {
            $scenario = $this->scenarioLoader->load($scenarioPath);
            $report = (new VisibilityAnalyzer(
                searchProvider: new StaticSearchProvider($scenario->searchResultSets),
                urlMatcher: new DefaultUrlMatcher(),
                visibilityResultDetector: new VisibilityResultDetector(),
                pageFetcher: new FixturePageFetcher($scenario->pageSnapshots),
                pageParser: new DomPageParser(),
            ))->analyze($scenario->product, $scenario->queries);

            $this->write($stdout, (new JsonReportSerializer())->serialize($report) . PHP_EOL);

            return 0;
        } catch (Throwable $throwable) {
            $this->write($stderr, 'Error: ' . $throwable->getMessage() . PHP_EOL);

            return 1;
        }
    }

    private function usage(string $message): string
    {
        return $message . PHP_EOL
            . 'Usage: visibility analyze <scenario-json-path>' . PHP_EOL;
    }

    /**
     * @param resource $stream
     */
    private function write(mixed $stream, string $message): void
    {
        fwrite($stream, $message);
    }
}
