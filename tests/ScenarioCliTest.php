<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use VisibilityDetector\Cli\ScenarioLoader;
use VisibilityDetector\Cli\VisibilityCli;

final class ScenarioCliTest extends TestCase
{
    public function test_valid_scenario_execution_writes_json_report(): void
    {
        [$exitCode, $stdout, $stderr] = $this->runCli(['visibility', 'analyze', $this->projectRoot() . '/examples/scenarios/visible-clean.json']);

        self::assertSame(0, $exitCode);
        self::assertSame('', $stderr);
        self::assertJson($stdout);

        $payload = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('visible', $payload['summary']['overallStatus']);
        self::assertSame('healthy', $payload['queryVisibilities'][0]['visibilityHealth']);
        self::assertSame('https://example.test/products/aurora-trail-shoe', $payload['product']['expectedUrl']);
    }

    public function test_invalid_scenario_file_path_returns_error(): void
    {
        [$exitCode, $stdout, $stderr] = $this->runCli(['visibility', 'analyze', $this->projectRoot() . '/examples/scenarios/missing.json']);

        self::assertSame(1, $exitCode);
        self::assertSame('', $stdout);
        self::assertStringContainsString('Scenario file does not exist', $stderr);
    }

    public function test_invalid_json_returns_error(): void
    {
        $scenario = $this->writeTempFile('invalid-json', '{');

        [$exitCode, $stdout, $stderr] = $this->runCli(['visibility', 'analyze', $scenario]);

        self::assertSame(1, $exitCode);
        self::assertSame('', $stdout);
        self::assertStringContainsString('Scenario file contains invalid JSON', $stderr);
    }

    public function test_missing_search_result_fixture_reference_returns_error(): void
    {
        $scenario = $this->writeTempScenario([
            'product' => $this->productPayload(),
            'queries' => [$this->queryPayload()],
            'searchResultFixtures' => ['missing-search-results.json'],
            'pageFixtures' => [$this->pageFixturePayload('examples/fixtures/product-page-clean.html')],
        ]);

        [$exitCode, $stdout, $stderr] = $this->runCli(['visibility', 'analyze', $scenario]);

        self::assertSame(1, $exitCode);
        self::assertSame('', $stdout);
        self::assertStringContainsString('Search result fixture does not exist', $stderr);
    }

    public function test_missing_page_fixture_reference_returns_error(): void
    {
        $scenario = $this->writeTempScenario([
            'product' => $this->productPayload(),
            'queries' => [$this->queryPayload()],
            'searchResults' => [$this->searchResultPayload()],
            'pageFixtures' => [$this->pageFixturePayload('missing-page.html')],
        ]);

        [$exitCode, $stdout, $stderr] = $this->runCli(['visibility', 'analyze', $scenario]);

        self::assertSame(1, $exitCode);
        self::assertSame('', $stdout);
        self::assertStringContainsString('Page HTML fixture does not exist', $stderr);
    }

    public function test_missing_product_expected_url_returns_validation_error(): void
    {
        $product = $this->productPayload();
        unset($product['expectedUrl']);

        $scenario = $this->writeTempScenario([
            'product' => $product,
            'queries' => [$this->queryPayload()],
            'searchResults' => [$this->searchResultPayload()],
            'pageFixtures' => [$this->pageFixturePayload('examples/fixtures/product-page-clean.html')],
        ]);

        [$exitCode, $stdout, $stderr] = $this->runCli(['visibility', 'analyze', $scenario]);

        self::assertSame(1, $exitCode);
        self::assertSame('', $stdout);
        self::assertStringContainsString('Scenario product expectedUrl is required', $stderr);
    }

    public function test_missing_queries_returns_validation_error(): void
    {
        $scenario = $this->writeTempScenario([
            'product' => $this->productPayload(),
            'searchResults' => [$this->searchResultPayload()],
            'pageFixtures' => [$this->pageFixturePayload('examples/fixtures/product-page-clean.html')],
        ]);

        [$exitCode, $stdout, $stderr] = $this->runCli(['visibility', 'analyze', $scenario]);

        self::assertSame(1, $exitCode);
        self::assertSame('', $stdout);
        self::assertStringContainsString('Scenario queries must be a non-empty array', $stderr);
    }

    public function test_empty_queries_returns_validation_error(): void
    {
        $scenario = $this->writeTempScenario([
            'product' => $this->productPayload(),
            'queries' => [],
            'searchResults' => [$this->searchResultPayload()],
            'pageFixtures' => [$this->pageFixturePayload('examples/fixtures/product-page-clean.html')],
        ]);

        [$exitCode, $stdout, $stderr] = $this->runCli(['visibility', 'analyze', $scenario]);

        self::assertSame(1, $exitCode);
        self::assertSame('', $stdout);
        self::assertStringContainsString('Scenario queries must be a non-empty array', $stderr);
    }

    public function test_empty_query_text_returns_validation_error(): void
    {
        $query = $this->queryPayload();
        $query['text'] = '';

        $scenario = $this->writeTempScenario([
            'product' => $this->productPayload(),
            'queries' => [$query],
            'searchResults' => [$this->searchResultPayload()],
            'pageFixtures' => [$this->pageFixturePayload('examples/fixtures/product-page-clean.html')],
        ]);

        [$exitCode, $stdout, $stderr] = $this->runCli(['visibility', 'analyze', $scenario]);

        self::assertSame(1, $exitCode);
        self::assertSame('', $stdout);
        self::assertStringContainsString('Scenario queries[0] text is required', $stderr);
    }

    public function test_missing_search_result_evidence_for_query_returns_validation_error(): void
    {
        $secondQuery = $this->queryPayload();
        $secondQuery['text'] = 'aurora trail shoe review';

        $scenario = $this->writeTempScenario([
            'product' => $this->productPayload(),
            'queries' => [$this->queryPayload(), $secondQuery],
            'searchResults' => [$this->searchResultPayload()],
            'pageFixtures' => [$this->pageFixturePayload('examples/fixtures/product-page-clean.html')],
        ]);

        [$exitCode, $stdout, $stderr] = $this->runCli(['visibility', 'analyze', $scenario]);

        self::assertSame(1, $exitCode);
        self::assertSame('', $stdout);
        self::assertStringContainsString('Missing search result evidence for scenario query', $stderr);
    }

    public function test_malformed_page_fixture_definition_returns_validation_error(): void
    {
        $scenario = $this->writeTempScenario([
            'product' => $this->productPayload(),
            'queries' => [$this->queryPayload()],
            'searchResults' => [$this->searchResultPayload()],
            'pageFixtures' => [[
                'requestedUrl' => 'https://example.test/products/aurora-trail-shoe',
                'statusCode' => 200,
            ]],
        ]);

        [$exitCode, $stdout, $stderr] = $this->runCli(['visibility', 'analyze', $scenario]);

        self::assertSame(1, $exitCode);
        self::assertSame('', $stdout);
        self::assertStringContainsString('Scenario pageFixtures[0] must define htmlFixture or body', $stderr);
    }

    public function test_ambiguous_duplicate_search_result_evidence_returns_validation_error(): void
    {
        $scenario = $this->writeTempScenario([
            'product' => $this->productPayload(),
            'queries' => [$this->queryPayload()],
            'searchResults' => [$this->searchResultPayload(), $this->searchResultPayload()],
            'pageFixtures' => [$this->pageFixturePayload('examples/fixtures/product-page-clean.html')],
        ]);

        [$exitCode, $stdout, $stderr] = $this->runCli(['visibility', 'analyze', $scenario]);

        self::assertSame(1, $exitCode);
        self::assertSame('', $stdout);
        self::assertStringContainsString('Ambiguous duplicate search result evidence', $stderr);
    }

    public function test_mismatched_search_result_evidence_returns_validation_error(): void
    {
        $searchResult = $this->searchResultPayload();
        $searchResult['query']['provider'] = 'other-provider';

        $scenario = $this->writeTempScenario([
            'product' => $this->productPayload(),
            'queries' => [$this->queryPayload()],
            'searchResults' => [$searchResult],
            'pageFixtures' => [$this->pageFixturePayload('examples/fixtures/product-page-clean.html')],
        ]);

        [$exitCode, $stdout, $stderr] = $this->runCli(['visibility', 'analyze', $scenario]);

        self::assertSame(1, $exitCode);
        self::assertSame('', $stdout);
        self::assertStringContainsString('Search result evidence query/provider does not match any scenario query', $stderr);
    }

    public function test_existing_fixture_backed_scenario_still_runs_after_validation(): void
    {
        [$exitCode, $stdout, $stderr] = $this->runCli(['visibility', 'analyze', $this->projectRoot() . '/examples/scenarios/not-visible.json']);

        self::assertSame(0, $exitCode);
        self::assertSame('', $stderr);
        self::assertJson($stdout);
        self::assertStringContainsString('product.not_visible_in_results', $stdout);
    }

    public function test_unknown_command_returns_usage_error(): void
    {
        [$exitCode, $stdout, $stderr] = $this->runCli(['visibility', 'inspect', $this->projectRoot() . '/examples/scenarios/visible-clean.json']);

        self::assertSame(1, $exitCode);
        self::assertSame('', $stdout);
        self::assertStringContainsString('Unknown command: inspect', $stderr);
        self::assertStringContainsString('Usage: visibility analyze <scenario-json-path> [--compact]', $stderr);
    }

    public function test_output_is_existing_json_report_format(): void
    {
        [$exitCode, $stdout] = $this->runCli(['visibility', 'analyze', $this->projectRoot() . '/examples/scenarios/not-visible.json']);

        self::assertSame(0, $exitCode);

        $payload = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);

        self::assertArrayHasKey('generatedAt', $payload);
        self::assertArrayHasKey('product', $payload);
        self::assertArrayHasKey('queryVisibilities', $payload);
        self::assertArrayHasKey('urlEvidence', $payload);
        self::assertArrayHasKey('pageSnapshot', $payload);
        self::assertArrayHasKey('parsedPage', $payload);
        self::assertArrayHasKey('summary', $payload);
        self::assertArrayHasKey('body', $payload['pageSnapshot']);
        self::assertArrayHasKey('jsonLdBlocks', $payload['parsedPage']);
        self::assertSame('not_visible', $payload['queryVisibilities'][0]['status']);
    }


    public function test_compact_output_contains_key_diagnostics_and_omits_large_evidence(): void
    {
        [$exitCode, $stdout, $stderr] = $this->runCli(['visibility', 'analyze', $this->projectRoot() . '/examples/scenarios/visible-clean.json', '--compact']);

        self::assertSame(0, $exitCode);
        self::assertSame('', $stderr);
        self::assertJson($stdout);

        $payload = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('https://example.test/products/aurora-trail-shoe', $payload['product']['expectedUrl']);
        self::assertSame('Aurora Trail Shoe', $payload['product']['name']);
        self::assertSame('visible', $payload['overallStatus']);
        self::assertSame('low', $payload['overallPriority']);
        self::assertSame('acme waterproof trail running shoes', $payload['queries'][0]['query']['text']);
        self::assertSame('visible', $payload['queries'][0]['status']);
        self::assertSame('healthy', $payload['queries'][0]['visibilityHealth']);
        self::assertSame('https://example.test/products/aurora-trail-shoe', $payload['urlEvidence']['expectedUrl']);
        self::assertSame(['https://example.test/products/aurora-trail-shoe'], $payload['urlEvidence']['matchedUrls']);
        self::assertArrayHasKey('topProbableCauses', $payload);
        self::assertArrayHasKey('topRecommendedActions', $payload);
        self::assertArrayHasKey('warnings', $payload);
        self::assertArrayNotHasKey('pageSnapshot', $payload);
        self::assertArrayNotHasKey('parsedPage', $payload);
        self::assertArrayNotHasKey('queryVisibilities', $payload);
        self::assertStringNotContainsString('pageSnapshot', $stdout);
        self::assertStringNotContainsString('bodyTextSummary', $stdout);
        self::assertStringNotContainsString('jsonLdBlocks', $stdout);
        self::assertStringNotContainsString('<html', $stdout);
    }

    public function test_unknown_compact_option_returns_usage_error(): void
    {
        [$exitCode, $stdout, $stderr] = $this->runCli(['visibility', 'analyze', $this->projectRoot() . '/examples/scenarios/visible-clean.json', '--verbose']);

        self::assertSame(1, $exitCode);
        self::assertSame('', $stdout);
        self::assertStringContainsString('Unknown option: --verbose', $stderr);
        self::assertStringContainsString('Usage: visibility analyze <scenario-json-path> [--compact]', $stderr);
    }

    public function test_cli_uses_only_static_scenario_evidence_without_external_services(): void
    {
        $scenario = (new ScenarioLoader($this->projectRoot()))->load($this->projectRoot() . '/examples/scenarios/visible-noindex.json');

        self::assertCount(1, $scenario->searchResultSets);
        self::assertCount(1, $scenario->pageSnapshots);
        self::assertStringContainsString('<html', $scenario->pageSnapshots[0]->body ?? '');

        [$exitCode, $stdout, $stderr] = $this->runCli(['visibility', 'analyze', $this->projectRoot() . '/examples/scenarios/visible-noindex.json']);

        self::assertSame(0, $exitCode);
        self::assertSame('', $stderr);
        self::assertJson($stdout);
        self::assertStringContainsString('page.noindex_meta', $stdout);
    }

    public function test_http_error_scenario_emits_expected_finding_code(): void
    {
        $this->assertScenarioEmitsFindingCode('examples/scenarios/visible-http-error.json', 'page.http_error');
    }

    public function test_redirect_elsewhere_scenario_emits_expected_finding_code(): void
    {
        $this->assertScenarioEmitsFindingCode('examples/scenarios/visible-redirects-elsewhere.json', 'page.redirects_elsewhere');
    }

    public function test_x_robots_noindex_scenario_emits_expected_finding_code(): void
    {
        $this->assertScenarioEmitsFindingCode('examples/scenarios/visible-x-robots-noindex.json', 'page.noindex_x_robots');
    }

    public function test_canonical_homepage_scenario_emits_expected_finding_code(): void
    {
        $this->assertScenarioEmitsFindingCode('examples/scenarios/visible-canonical-homepage.json', 'canonical.points_to_homepage');
    }

    public function test_schema_offer_missing_scenario_emits_expected_finding_code(): void
    {
        $this->assertScenarioEmitsFindingCode('examples/scenarios/visible-schema-offer-missing.json', 'schema.offer_missing');
    }

    public function test_schema_price_missing_scenario_emits_expected_finding_code(): void
    {
        $this->assertScenarioEmitsFindingCode('examples/scenarios/visible-schema-price-missing.json', 'schema.price_missing');
    }

    public function test_schema_currency_missing_scenario_emits_expected_finding_code(): void
    {
        $this->assertScenarioEmitsFindingCode('examples/scenarios/visible-schema-currency-missing.json', 'schema.currency_missing');
    }

    public function test_schema_availability_missing_scenario_emits_expected_finding_code(): void
    {
        $this->assertScenarioEmitsFindingCode('examples/scenarios/visible-schema-availability-missing.json', 'schema.availability_missing');
    }

    public function test_weak_content_alignment_scenario_emits_expected_finding_codes(): void
    {
        $this->assertScenarioEmitsFindingCodes('examples/scenarios/visible-weak-content-alignment.json', [
            'content.title_missing_product_terms',
            'content.h1_missing_product_terms',
            'content.body_missing_product_terms',
        ]);
    }

    private function assertScenarioEmitsFindingCode(string $scenarioPath, string $expectedCode): void
    {
        $this->assertScenarioEmitsFindingCodes($scenarioPath, [$expectedCode]);
    }

    /**
     * @param array<int, string> $expectedCodes
     */
    private function assertScenarioEmitsFindingCodes(string $scenarioPath, array $expectedCodes): void
    {
        [$exitCode, $stdout, $stderr] = $this->runCli(['visibility', 'analyze', $this->projectRoot() . '/' . $scenarioPath]);

        self::assertSame(0, $exitCode);
        self::assertSame('', $stderr);
        self::assertJson($stdout);

        foreach ($expectedCodes as $expectedCode) {
            self::assertStringContainsString($expectedCode, $stdout);
        }
    }

    /**
     * @param array<int, string> $argv
     * @return array{0: int, 1: string, 2: string}
     */
    private function runCli(array $argv): array
    {
        $stdout = fopen('php://temp', 'w+');
        $stderr = fopen('php://temp', 'w+');

        self::assertIsResource($stdout);
        self::assertIsResource($stderr);

        $exitCode = (new VisibilityCli(new ScenarioLoader($this->projectRoot())))->run($argv, $stdout, $stderr);

        rewind($stdout);
        rewind($stderr);

        return [
            $exitCode,
            stream_get_contents($stdout) ?: '',
            stream_get_contents($stderr) ?: '',
        ];
    }

    private function projectRoot(): string
    {
        return dirname(__DIR__);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function writeTempScenario(array $payload): string
    {
        return $this->writeTempFile('scenario', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    private function writeTempFile(string $name, string $contents): string
    {
        $directory = sys_get_temp_dir() . '/visibility-scenario-cli-' . bin2hex(random_bytes(6));

        mkdir($directory);

        $path = $directory . '/' . $name . '.json';
        file_put_contents($path, $contents);

        return $path;
    }

    /**
     * @return array<string, mixed>
     */
    private function productPayload(): array
    {
        return [
            'expectedUrl' => 'https://example.test/products/aurora-trail-shoe',
            'name' => 'Aurora Trail Shoe',
            'brand' => 'Acme Outdoor',
            'acceptableUrlVariants' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function queryPayload(): array
    {
        return [
            'text' => 'acme waterproof trail running shoes',
            'provider' => 'static-fixture',
            'locale' => 'en_US',
            'device' => 'desktop',
            'expectedVisibility' => true,
            'priority' => 'critical',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function searchResultPayload(): array
    {
        return [
            'query' => $this->queryPayload(),
            'results' => [[
                'position' => 1,
                'url' => 'https://example.test/products/aurora-trail-shoe',
            ]],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function pageFixturePayload(string $htmlFixture): array
    {
        return [
            'requestedUrl' => 'https://example.test/products/aurora-trail-shoe',
            'finalUrl' => 'https://example.test/products/aurora-trail-shoe',
            'htmlFixture' => $htmlFixture,
            'statusCode' => 200,
            'contentType' => 'text/html; charset=utf-8',
        ];
    }
}
