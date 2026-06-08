<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ExampleFixturesTest extends TestCase
{
    private const KNOWN_SUMMARY_CATEGORIES = [
        'availability_blocker',
        'indexability_blocker',
        'canonical_blocker',
        'visibility_quality',
        'content_quality',
        'diagnostic',
    ];

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

    public function test_sample_report_uses_current_summary_category_taxonomy(): void
    {
        $payload = $this->sampleReportPayload();
        $categories = $this->summaryCategories($payload);

        self::assertNotContains('structured_data_gap', $categories);

        foreach ($categories as $category) {
            self::assertContains($category, self::KNOWN_SUMMARY_CATEGORIES);
        }
    }

    public function test_schema_product_missing_uses_visibility_quality_in_summary(): void
    {
        $payload = $this->sampleReportPayload();
        $sawSchemaProductMissing = false;

        foreach ($this->summaryEntries($payload) as $entry) {
            if (($entry['code'] ?? null) !== 'schema.product_missing') {
                continue;
            }

            $sawSchemaProductMissing = true;
            self::assertSame('visibility_quality', $entry['category'] ?? null);
        }

        self::assertTrue($sawSchemaProductMissing);
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

    /**
     * @param array<string, mixed> $payload
     * @return array<int, string>
     */
    private function summaryCategories(array $payload): array
    {
        return array_values(array_filter(
            array_map(
                static fn (array $entry): ?string => isset($entry['category']) && is_string($entry['category']) ? $entry['category'] : null,
                $this->summaryEntries($payload),
            ),
            static fn (?string $category): bool => $category !== null,
        ));
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<int, array<string, mixed>>
     */
    private function summaryEntries(array $payload): array
    {
        $summary = $payload['summary'] ?? [];

        self::assertIsArray($summary);

        return array_merge(
            $this->summaryEntryList($summary, 'topProbableCauses'),
            $this->summaryEntryList($summary, 'topRecommendedActions'),
            $this->summaryEntryList($summary, 'evidenceReferences'),
        );
    }

    /**
     * @param array<string, mixed> $summary
     * @return array<int, array<string, mixed>>
     */
    private function summaryEntryList(array $summary, string $field): array
    {
        $entries = $summary[$field] ?? [];

        self::assertIsArray($entries);

        foreach ($entries as $entry) {
            self::assertIsArray($entry);
        }

        return $entries;
    }
}
