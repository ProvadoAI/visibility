<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ExampleFixturesTest extends TestCase
{
    public function test_sample_report_is_valid_json_with_required_demo_sections(): void
    {
        $payload = $this->sampleReportPayload();

        self::assertSame('2026-01-01T00:00:00+00:00', $payload['generatedAt'] ?? null);
        self::assertArrayHasKey('product', $payload);
        self::assertArrayHasKey('queryVisibilities', $payload);
        self::assertArrayHasKey('summary', $payload);
        self::assertArrayHasKey('topProbableCauses', $payload['summary']);
        self::assertArrayHasKey('topRecommendedActions', $payload['summary']);
    }

    public function test_sample_report_demonstrates_local_fixture_only_visibility_gap(): void
    {
        $payload = $this->sampleReportPayload();

        self::assertSame('not_visible', $payload['summary']['overallStatus']);
        self::assertSame('critical', $payload['summary']['overallPriority']);
        self::assertSame('https://example.test/products/aurora-trail-shoe', $payload['product']['expectedUrl']);
        self::assertTrue($payload['queryVisibilities'][0]['query']['expectedVisibility']);
        self::assertSame('static-fixture', $payload['queryVisibilities'][0]['provider']);
        self::assertContains('page.noindex_meta', array_column($payload['queryVisibilities'][0]['findings'], 'code'));
        self::assertContains('canonical.points_to_other_url', array_column($payload['queryVisibilities'][0]['findings'], 'code'));
    }

    public function test_example_script_uses_static_fixtures_instead_of_external_services(): void
    {
        $script = file_get_contents(__DIR__ . '/../examples/basic-analysis.php');

        self::assertIsString($script);
        self::assertStringContainsString('StaticSearchProvider', $script);
        self::assertStringContainsString('FixturePageFetcher', $script);
        self::assertStringNotContainsString('curl_', $script);
        self::assertStringNotContainsString('HttpClient', $script);
        self::assertStringNotContainsString('https://www.google.', $script);
        self::assertStringNotContainsString('https://www.bing.', $script);
    }

    /**
     * @return array<string, mixed>
     */
    private function sampleReportPayload(): array
    {
        $json = file_get_contents(__DIR__ . '/../examples/sample-report.json');

        self::assertIsString($json);
        self::assertJson($json);

        $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        self::assertIsArray($payload);

        return $payload;
    }
}
