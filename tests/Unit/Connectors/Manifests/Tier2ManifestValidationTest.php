<?php

declare(strict_types=1);

namespace Tests\Unit\Connectors\Manifests;

use App\Support\Connectors\ConnectorManifestValidator;
use Tests\TestCase;

class Tier2ManifestValidationTest extends TestCase
{
    private ConnectorManifestValidator $validator;

    private string $manifestsPath;

    private const TIER_2_CONNECTORS = [
        'brighthr',
        'breathe-hr',
        'zendesk',
        'freshdesk',
        'mailchimp',
        'activecampaign',
        'calendly',
        'jira',
        'asana',
        'quickbooks',
        'freeagent',
        'shopify',
        'trello',
        'dropbox',
    ];

    private const EXPECTED_CATEGORIES = [
        'brighthr' => 'hr',
        'breathe-hr' => 'hr',
        'zendesk' => 'customer-support',
        'freshdesk' => 'customer-support',
        'mailchimp' => 'email-marketing',
        'activecampaign' => 'marketing-automation',
        'calendly' => 'scheduling',
        'jira' => 'issue-tracking',
        'asana' => 'project-management',
        'quickbooks' => 'accounting',
        'freeagent' => 'accounting',
        'shopify' => 'e-commerce',
        'trello' => 'project-management',
        'dropbox' => 'file-storage',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new ConnectorManifestValidator;
        $this->manifestsPath = base_path('connectors');
    }

    public function test_all_tier2_manifests_pass_validation(): void
    {
        foreach (self::TIER_2_CONNECTORS as $connector) {
            $path = "{$this->manifestsPath}/{$connector}/connector.json";
            $this->assertFileExists($path, "Manifest file missing for Tier 2 connector: {$connector}");

            $manifest = json_decode(file_get_contents($path), true);
            $this->assertNotNull($manifest, "Invalid JSON in manifest for: {$connector}");

            $result = $this->validator->validate($manifest);
            $this->assertTrue($result->valid, "Tier 2 connector '{$connector}' failed validation: ".implode(', ', $result->errors));
        }
    }

    public function test_all_tier2_manifests_have_actions(): void
    {
        foreach (self::TIER_2_CONNECTORS as $connector) {
            $manifest = json_decode(file_get_contents("{$this->manifestsPath}/{$connector}/connector.json"), true);

            $this->assertNotEmpty($manifest['actions'], "Tier 2 connector '{$connector}' has no actions defined.");

            $actionsDir = "{$this->manifestsPath}/{$connector}/actions";
            $this->assertDirectoryExists($actionsDir, "Actions directory missing for: {$connector}");

            $actionFiles = glob("{$actionsDir}/*.json");
            $this->assertNotEmpty($actionFiles, "No action schema files found for: {$connector}");
            $this->assertCount(
                count($manifest['actions']),
                $actionFiles,
                "Action count mismatch for '{$connector}': manifest declares ".count($manifest['actions']).' but found '.count($actionFiles).' schema files.'
            );
        }
    }

    public function test_tier2_connectors_have_correct_categories(): void
    {
        foreach (self::EXPECTED_CATEGORIES as $connector => $expectedCategory) {
            $manifest = json_decode(file_get_contents("{$this->manifestsPath}/{$connector}/connector.json"), true);

            $this->assertSame(
                $expectedCategory,
                $manifest['category'],
                "Tier 2 connector '{$connector}' has incorrect category. Expected '{$expectedCategory}', got '{$manifest['category']}'."
            );
        }
    }
}
