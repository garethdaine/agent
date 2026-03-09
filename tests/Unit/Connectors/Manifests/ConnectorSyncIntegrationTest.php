<?php

declare(strict_types=1);

namespace Tests\Unit\Connectors\Manifests;

use App\Models\AgentConnector;
use App\Support\Connectors\ConnectorManifestValidator;
use App\Support\Connectors\ConnectorRegistryLoader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConnectorSyncIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private ConnectorRegistryLoader $loader;

    private string $manifestsPath;

    private const EXPECTED_CATEGORIES = [
        'xero' => 'accounting',
        'sage' => 'accounting',
        'salesforce' => 'crm',
        'hubspot' => 'crm',
        'microsoft-teams' => 'communication',
        'slack' => 'communication',
        'google-workspace' => 'productivity',
        'microsoft-365' => 'productivity',
        'stripe' => 'payments',
        'gocardless' => 'payments',
        'docusign' => 'document-signing',
        'monday' => 'project-management',
        'notion-connector' => 'knowledge-base',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->loader = new ConnectorRegistryLoader(new ConnectorManifestValidator);
        $this->manifestsPath = base_path('connectors');
    }

    public function test_sync_loads_all_tier1_connectors_into_database(): void
    {
        $result = $this->loader->sync($this->manifestsPath);

        $expectedCount = count(glob($this->manifestsPath.'/*/connector.json'));
        $this->assertGreaterThan(0, $expectedCount, 'Expected at least one connector manifest to exist.');
        $this->assertSame($expectedCount, $result->created, "Expected {$expectedCount} connectors to be created.");
        $this->assertSame(0, $result->errors, 'Expected no errors during sync.');
        $this->assertSame($expectedCount, AgentConnector::count(), "Expected {$expectedCount} AgentConnector records in database.");
    }

    public function test_synced_connectors_have_correct_categories(): void
    {
        $this->loader->sync($this->manifestsPath);

        foreach (self::EXPECTED_CATEGORIES as $name => $expectedCategory) {
            $connector = AgentConnector::where('name', $name)->first();

            $this->assertNotNull($connector, "Connector '{$name}' not found in database after sync.");
            $this->assertSame(
                $expectedCategory,
                $connector->category,
                "Connector '{$name}' has incorrect category. Expected '{$expectedCategory}', got '{$connector->category}'."
            );
        }
    }
}
