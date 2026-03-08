<?php

declare(strict_types=1);

namespace Tests\Unit\Connectors\Manifests;

use App\Models\AgentConnector;
use App\Support\Connectors\ConnectorManifestValidator;
use App\Support\Connectors\ConnectorRegistryLoader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FullRegistrySyncTest extends TestCase
{
    use RefreshDatabase;

    private ConnectorRegistryLoader $loader;

    private string $manifestsPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loader = new ConnectorRegistryLoader(new ConnectorManifestValidator);
        $this->manifestsPath = base_path('connectors');
    }

    public function test_sync_loads_all_45_connectors(): void
    {
        $result = $this->loader->sync($this->manifestsPath);

        $this->assertSame(45, $result->created, 'Expected 45 connectors to be created (13 Tier 1 + 14 Tier 2 + 18 Tier 3).');
        $this->assertSame(0, $result->errors, 'Expected no errors during sync.');
        $this->assertSame(45, AgentConnector::count(), 'Expected 45 AgentConnector records in database.');
    }

    public function test_no_duplicate_names_across_all_tiers(): void
    {
        $this->loader->sync($this->manifestsPath);

        $names = AgentConnector::pluck('name')->toArray();
        $uniqueNames = array_unique($names);

        $this->assertCount(
            count($names),
            $uniqueNames,
            'Duplicate connector names found: '.implode(', ', array_diff_assoc($names, $uniqueNames))
        );
    }

    public function test_no_duplicate_mcp_tool_prefixes(): void
    {
        $this->loader->sync($this->manifestsPath);

        $prefixes = AgentConnector::whereNotNull('mcp_tool_prefix')
            ->pluck('mcp_tool_prefix')
            ->toArray();
        $uniquePrefixes = array_unique($prefixes);

        $this->assertCount(
            count($prefixes),
            $uniquePrefixes,
            'Duplicate mcp_tool_prefix values found: '.implode(', ', array_diff_assoc($prefixes, $uniquePrefixes))
        );
    }
}
