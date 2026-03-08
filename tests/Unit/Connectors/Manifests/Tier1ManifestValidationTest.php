<?php

namespace Tests\Unit\Connectors\Manifests;

use App\Support\Connectors\ConnectorManifestValidator;
use Tests\TestCase;

class Tier1ManifestValidationTest extends TestCase
{
    private ConnectorManifestValidator $validator;

    private string $manifestsPath;

    private const TIER1_CONNECTORS = [
        'xero',
        'sage',
        'salesforce',
        'hubspot',
        'microsoft-teams',
        'slack',
        'google-workspace',
        'microsoft-365',
        'stripe',
        'gocardless',
        'docusign',
        'monday',
        'notion-connector',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new ConnectorManifestValidator;
        $this->manifestsPath = base_path('connectors');
    }

    public function test_all_tier1_manifests_pass_validation(): void
    {
        foreach (self::TIER1_CONNECTORS as $connector) {
            $manifestFile = $this->manifestsPath . '/' . $connector . '/connector.json';
            $this->assertFileExists($manifestFile, "Manifest file missing for connector: {$connector}");

            $manifest = json_decode(file_get_contents($manifestFile), true);
            $this->assertNotNull($manifest, "Invalid JSON in manifest for connector: {$connector}");

            $result = $this->validator->validate($manifest);
            $this->assertTrue(
                $result->valid,
                "Manifest validation failed for {$connector}: " . implode(', ', $result->errors)
            );
        }
    }

    public function test_all_tier1_manifests_have_at_least_one_action(): void
    {
        foreach (self::TIER1_CONNECTORS as $connector) {
            $manifest = $this->loadManifest($connector);

            $this->assertNotEmpty(
                $manifest['actions'],
                "Connector {$connector} must have at least one action."
            );
        }
    }

    public function test_all_tier1_action_schemas_are_valid_json(): void
    {
        foreach (self::TIER1_CONNECTORS as $connector) {
            $actionsDir = $this->manifestsPath . '/' . $connector . '/actions';
            $this->assertDirectoryExists($actionsDir, "Actions directory missing for connector: {$connector}");

            $actionFiles = glob($actionsDir . '/*.json');
            $this->assertNotEmpty($actionFiles, "No action schema files found for connector: {$connector}");

            foreach ($actionFiles as $actionFile) {
                $content = file_get_contents($actionFile);
                $action = json_decode($content, true);

                $this->assertNotNull($action, "Invalid JSON in action file: {$actionFile}");
                $this->assertArrayHasKey('name', $action, "Missing 'name' in action file: {$actionFile}");
                $this->assertArrayHasKey('method', $action, "Missing 'method' in action file: {$actionFile}");
                $this->assertArrayHasKey('path', $action, "Missing 'path' in action file: {$actionFile}");
                $this->assertArrayHasKey('description', $action, "Missing 'description' in action file: {$actionFile}");
                $this->assertArrayHasKey('requires_approval', $action, "Missing 'requires_approval' in action file: {$actionFile}");
                $this->assertArrayHasKey('read_only', $action, "Missing 'read_only' in action file: {$actionFile}");
            }
        }
    }

    public function test_all_oauth_connectors_have_authorization_url(): void
    {
        $oauthTypes = ['oauth2_authorization_code', 'oauth2_client_credentials'];

        foreach (self::TIER1_CONNECTORS as $connector) {
            $manifest = $this->loadManifest($connector);

            if (in_array($manifest['auth_type'], $oauthTypes, true)) {
                $this->assertArrayHasKey('authorization_url', $manifest['auth_config'],
                    "OAuth connector {$connector} missing authorization_url in auth_config."
                );
                $this->assertArrayHasKey('token_url', $manifest['auth_config'],
                    "OAuth connector {$connector} missing token_url in auth_config."
                );
                $this->assertNotEmpty($manifest['auth_config']['authorization_url'],
                    "OAuth connector {$connector} has empty authorization_url."
                );
                $this->assertNotEmpty($manifest['auth_config']['token_url'],
                    "OAuth connector {$connector} has empty token_url."
                );
            }
        }
    }

    public function test_all_api_key_connectors_have_no_oauth_fields(): void
    {
        foreach (self::TIER1_CONNECTORS as $connector) {
            $manifest = $this->loadManifest($connector);

            if ($manifest['auth_type'] === 'api_key') {
                $this->assertArrayNotHasKey('authorization_url', $manifest['auth_config'],
                    "API key connector {$connector} should not have authorization_url."
                );
                $this->assertArrayNotHasKey('token_url', $manifest['auth_config'],
                    "API key connector {$connector} should not have token_url."
                );
            }
        }
    }

    public function test_xero_manifest_has_expected_actions(): void
    {
        $manifest = $this->loadManifest('xero');
        $actionNames = array_column($manifest['actions'], 'name');

        $this->assertContains('list-invoices', $actionNames, 'Xero must have list-invoices action.');
        $this->assertContains('create-invoice', $actionNames, 'Xero must have create-invoice action.');
        $this->assertContains('get-contact', $actionNames, 'Xero must have get-contact action.');
    }

    public function test_stripe_manifest_uses_api_key_auth(): void
    {
        $manifest = $this->loadManifest('stripe');

        $this->assertSame('api_key', $manifest['auth_type'],
            'Stripe connector must use api_key auth type.'
        );
    }

    public function test_salesforce_manifest_uses_oauth2(): void
    {
        $manifest = $this->loadManifest('salesforce');

        $this->assertSame('oauth2_authorization_code', $manifest['auth_type'],
            'Salesforce connector must use oauth2_authorization_code auth type.'
        );
    }

    public function test_all_write_actions_are_in_requires_approval_for(): void
    {
        foreach (self::TIER1_CONNECTORS as $connector) {
            $manifest = $this->loadManifest($connector);
            $requiresApprovalFor = $manifest['requires_approval_for'] ?? [];

            foreach ($manifest['actions'] as $action) {
                if (in_array($action['method'], ['POST', 'PUT', 'DELETE'], true)) {
                    $this->assertContains(
                        $action['name'],
                        $requiresApprovalFor,
                        "Write action '{$action['name']}' in connector {$connector} must be listed in requires_approval_for."
                    );
                }
            }
        }
    }

    public function test_all_manifests_have_unique_mcp_tool_prefix(): void
    {
        $prefixes = [];

        foreach (self::TIER1_CONNECTORS as $connector) {
            $manifest = $this->loadManifest($connector);

            $this->assertArrayHasKey('mcp_tool_prefix', $manifest,
                "Connector {$connector} must have mcp_tool_prefix."
            );

            $prefix = $manifest['mcp_tool_prefix'];
            $this->assertNotContains($prefix, $prefixes,
                "Duplicate mcp_tool_prefix '{$prefix}' found in connector {$connector}."
            );
            $prefixes[] = $prefix;
        }
    }

    private function loadManifest(string $connector): array
    {
        $manifestFile = $this->manifestsPath . '/' . $connector . '/connector.json';
        $this->assertFileExists($manifestFile, "Manifest file missing for connector: {$connector}");

        $manifest = json_decode(file_get_contents($manifestFile), true);
        $this->assertNotNull($manifest, "Invalid JSON in manifest for connector: {$connector}");

        return $manifest;
    }
}
