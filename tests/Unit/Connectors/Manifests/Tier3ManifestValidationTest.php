<?php

namespace Tests\Unit\Connectors\Manifests;

use App\Support\Connectors\ConnectorManifestValidator;
use Tests\TestCase;

class Tier3ManifestValidationTest extends TestCase
{
    private ConnectorManifestValidator $validator;

    private string $manifestsPath;

    private const TIER_3_CONNECTORS = [
        'connectwise-manage',
        'datto-autotask',
        'carelinelive',
        'person-centred-software',
        'servicem8',
        'simpro',
        'brightpearl',
        'shipstation',
        'procore',
        'sage-construction',
        'alto',
        'reapit',
        'bullhorn',
        'vincere',
        'clio',
        'leap',
        'acturis',
        'opengi',
    ];

    private const PARTNERSHIP_PENDING_CONNECTORS = [
        'carelinelive',
        'person-centred-software',
        'alto',
        'acturis',
    ];

    private const TARGET_VERTICALS = [
        'it-msp',
        'healthcare',
        'facilities',
        'logistics',
        'construction',
        'property',
        'recruitment',
        'legal',
        'insurance',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new ConnectorManifestValidator;
        $this->manifestsPath = base_path('connectors');
    }

    public function test_all_tier3_manifests_pass_validation(): void
    {
        foreach (self::TIER_3_CONNECTORS as $connector) {
            $path = "{$this->manifestsPath}/{$connector}/connector.json";
            $this->assertFileExists($path, "Manifest file missing for Tier 3 connector: {$connector}");

            $manifest = json_decode(file_get_contents($path), true);
            $this->assertNotNull($manifest, "Invalid JSON in manifest for: {$connector}");

            $result = $this->validator->validate($manifest);
            $this->assertTrue($result->valid, "Tier 3 connector '{$connector}' failed validation: ".implode(', ', $result->errors));
        }
    }

    public function test_tier3_connectors_have_industry_tags(): void
    {
        foreach (self::TIER_3_CONNECTORS as $connector) {
            $manifest = json_decode(file_get_contents("{$this->manifestsPath}/{$connector}/connector.json"), true);

            $this->assertArrayHasKey('industries', $manifest, "Tier 3 connector '{$connector}' missing industries array.");
            $this->assertNotEmpty($manifest['industries'], "Tier 3 connector '{$connector}' has empty industries array.");
            $this->assertNotContains('cross-industry', $manifest['industries'], "Tier 3 connector '{$connector}' should not use 'cross-industry' — must target specific verticals.");
        }
    }

    public function test_partnership_pending_connectors_have_correct_status(): void
    {
        foreach (self::PARTNERSHIP_PENDING_CONNECTORS as $connector) {
            $manifest = json_decode(file_get_contents("{$this->manifestsPath}/{$connector}/connector.json"), true);

            $this->assertSame(
                'partnership_pending',
                $manifest['status'],
                "Partnership-gated connector '{$connector}' should have status 'partnership_pending'."
            );
            $this->assertArrayHasKey(
                'partnership_notes',
                $manifest,
                "Partnership-gated connector '{$connector}' should include partnership_notes."
            );
        }

        $this->assertCount(4, self::PARTNERSHIP_PENDING_CONNECTORS, 'Expected exactly 4 partnership-pending connectors.');
    }

    public function test_tier3_connectors_cover_all_target_verticals(): void
    {
        $coveredVerticals = [];

        foreach (self::TIER_3_CONNECTORS as $connector) {
            $manifest = json_decode(file_get_contents("{$this->manifestsPath}/{$connector}/connector.json"), true);

            foreach ($manifest['industries'] as $industry) {
                $coveredVerticals[$industry] = true;
            }
        }

        foreach (self::TARGET_VERTICALS as $vertical) {
            $this->assertArrayHasKey(
                $vertical,
                $coveredVerticals,
                "Target vertical '{$vertical}' is not covered by any Tier 3 connector."
            );
        }
    }
}
