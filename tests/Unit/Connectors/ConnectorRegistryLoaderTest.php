<?php

namespace Tests\Unit\Connectors;

use App\Models\AgentConnector;
use App\Support\Connectors\ConnectorManifestValidator;
use App\Support\Connectors\ConnectorRegistryLoader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ConnectorRegistryLoaderTest extends TestCase
{
    use RefreshDatabase;

    private ConnectorRegistryLoader $loader;

    private string $fixturesPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loader = new ConnectorRegistryLoader(new ConnectorManifestValidator);
        $this->fixturesPath = sys_get_temp_dir().'/connector-test-'.uniqid();
        mkdir($this->fixturesPath, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->cleanupFixtures($this->fixturesPath);
        parent::tearDown();
    }

    public function test_sync_creates_new_connector_from_manifest(): void
    {
        $this->writeManifest('test-connector', $this->validManifest());

        $result = $this->loader->sync($this->fixturesPath);

        $this->assertEquals(1, $result->created);
        $this->assertDatabaseHas('agent_connectors', [
            'name' => 'test-connector',
            'display_name' => 'Test Connector',
            'version' => '1.0.0',
        ]);
    }

    public function test_sync_updates_existing_connector_when_version_changes(): void
    {
        AgentConnector::factory()->create([
            'name' => 'test-connector',
            'version' => '1.0.0',
            'display_name' => 'Test Connector',
            'description' => 'Old description',
            'auth_type' => 'api_key',
            'auth_config' => ['header' => 'X-API-Key'],
            'base_url' => 'https://api.example.com',
            'rate_limits' => ['requests_per_minute' => 60, 'daily_limit' => 10000, 'concurrent_limit' => 5],
            'actions' => [['name' => 'list-items', 'method' => 'GET', 'path' => '/items']],
        ]);

        $manifest = $this->validManifest();
        $manifest['version'] = '2.0.0';
        $this->writeManifest('test-connector', $manifest);

        $result = $this->loader->sync($this->fixturesPath);

        $this->assertEquals(1, $result->updated);
        $this->assertDatabaseHas('agent_connectors', [
            'name' => 'test-connector',
            'version' => '2.0.0',
        ]);
    }

    public function test_sync_skips_unchanged_connector(): void
    {
        $manifest = $this->validManifest();
        $this->writeManifest('test-connector', $manifest);

        // First sync creates
        $this->loader->sync($this->fixturesPath);

        $connector = AgentConnector::where('name', 'test-connector')->first();
        $originalUpdatedAt = $connector->updated_at;

        // Brief pause to ensure timestamp would differ if updated
        usleep(100000);

        // Second sync should skip
        $result = $this->loader->sync($this->fixturesPath);

        $this->assertEquals(1, $result->skipped);
        $connector->refresh();
        $this->assertEquals($originalUpdatedAt->toDateTimeString(), $connector->updated_at->toDateTimeString());
    }

    public function test_sync_marks_removed_connectors_as_deprecated(): void
    {
        AgentConnector::factory()->create([
            'name' => 'old-connector',
            'status' => AgentConnector::STATUS_AVAILABLE,
        ]);

        // Create a different connector manifest
        $this->writeManifest('new-connector', $this->validManifest(['name' => 'new-connector']));

        $result = $this->loader->sync($this->fixturesPath);

        $this->assertEquals(1, $result->deprecated);
        $this->assertDatabaseHas('agent_connectors', [
            'name' => 'old-connector',
            'status' => AgentConnector::STATUS_DEPRECATED,
        ]);
    }

    public function test_sync_handles_invalid_manifest_gracefully(): void
    {
        // Write invalid JSON
        $dir = $this->fixturesPath.'/bad-connector';
        mkdir($dir, 0755, true);
        file_put_contents($dir.'/connector.json', '{invalid json!!!}');

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn ($msg) => str_contains($msg, 'Invalid JSON'));

        $result = $this->loader->sync($this->fixturesPath);

        $this->assertEquals(1, $result->errors);
    }

    public function test_sync_returns_summary_with_counts(): void
    {
        $this->writeManifest('connector-one', $this->validManifest(['name' => 'connector-one']));
        $this->writeManifest('connector-two', $this->validManifest(['name' => 'connector-two']));

        $result = $this->loader->sync($this->fixturesPath);

        $this->assertEquals(2, $result->created);
        $this->assertEquals(0, $result->updated);
        $this->assertEquals(0, $result->skipped);
        $this->assertEquals(0, $result->deprecated);
        $this->assertEquals(0, $result->errors);
        $this->assertEquals(2, $result->total());
    }

    private function validManifest(array $overrides = []): array
    {
        return array_merge([
            'name' => 'test-connector',
            'display_name' => 'Test Connector',
            'description' => 'A test connector.',
            'category' => 'crm',
            'version' => '1.0.0',
            'auth_type' => 'api_key',
            'auth_config' => ['header' => 'X-API-Key'],
            'base_url' => 'https://api.example.com',
            'rate_limits' => [
                'requests_per_minute' => 60,
                'daily_limit' => 10000,
                'concurrent_limit' => 5,
            ],
            'actions' => [
                ['name' => 'list-items', 'method' => 'GET', 'path' => '/items'],
            ],
        ], $overrides);
    }

    private function writeManifest(string $name, array $manifest): void
    {
        $dir = $this->fixturesPath.'/'.$name;
        mkdir($dir, 0755, true);
        file_put_contents($dir.'/connector.json', json_encode($manifest, JSON_PRETTY_PRINT));
    }

    private function cleanupFixtures(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($path);
    }
}
