<?php

declare(strict_types=1);

namespace Tests\Feature\Connectors;

use App\Models\AgentConnector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConnectorCommandsTest extends TestCase
{
    use RefreshDatabase;

    private string $fixturesPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixturesPath = sys_get_temp_dir().'/connector-cmd-test-'.uniqid();
        mkdir($this->fixturesPath, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->cleanupFixtures($this->fixturesPath);
        parent::tearDown();
    }

    public function test_connector_list_command_shows_available_connectors(): void
    {
        AgentConnector::factory()->create(['name' => 'xero-accounting', 'display_name' => 'Xero']);
        AgentConnector::factory()->create(['name' => 'hubspot-crm', 'display_name' => 'HubSpot']);

        $this->artisan('connector:list')
            ->expectsOutputToContain('xero-accounting')
            ->expectsOutputToContain('hubspot-crm')
            ->assertSuccessful();
    }

    public function test_connector_list_command_shows_empty_state(): void
    {
        $this->artisan('connector:list')
            ->expectsOutputToContain('No connectors')
            ->assertSuccessful();
    }

    public function test_connector_sync_command_runs_loader(): void
    {
        $this->writeManifest('test-sync', [
            'name' => 'test-sync',
            'display_name' => 'Test Sync',
            'description' => 'Sync test connector.',
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
        ]);

        $this->artisan('connector:sync', ['--path' => $this->fixturesPath])
            ->assertSuccessful();

        $this->assertDatabaseHas('agent_connectors', ['name' => 'test-sync']);
    }

    public function test_connector_sync_command_outputs_summary(): void
    {
        $this->artisan('connector:sync', ['--path' => $this->fixturesPath])
            ->expectsOutputToContain('0 created')
            ->assertSuccessful();
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
