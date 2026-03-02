<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use App\Support\Documentation\DocsSearchIndexClient;
use App\Support\Documentation\DocsSearchService;
use App\Support\Documentation\DocsSearchUnavailableException;
use RuntimeException;
use Tests\TestCase;

class DocsSearchServiceTest extends TestCase
{
    public function test_it_filters_by_domain_and_boosts_route_affinity(): void
    {
        $client = new class implements DocsSearchIndexClient
        {
            public function search(string $query, ?string $domain, ?string $section, int $limit): array
            {
                return [
                    [
                        'title' => 'API Tokens',
                        'snippet' => 'Manage tokens for integrations.',
                        'domain' => 'api_doc',
                        'section' => 'security',
                        'route_names' => ['profile.tokens.index'],
                        'updated_at' => '2026-03-02T09:00:00Z',
                    ],
                    [
                        'title' => 'Agent Jobs',
                        'snippet' => 'Create and schedule jobs.',
                        'domain' => 'product_doc',
                        'section' => 'general',
                        'route_names' => ['agent.jobs.index'],
                        'updated_at' => '2026-03-02T10:00:00Z',
                    ],
                    [
                        'title' => 'Monitor',
                        'snippet' => 'Monitor run health.',
                        'domain' => 'product_doc',
                        'section' => 'general',
                        'route_names' => ['agent.monitor.index'],
                        'updated_at' => '2026-03-02T11:00:00Z',
                    ],
                ];
            }
        };

        $service = new DocsSearchService($client);

        $results = $service->search('agent', 'product_doc', null, 'agent.jobs.index', 10);

        $this->assertCount(2, $results);
        $this->assertSame('Agent Jobs', $results[0]['title']);
        $this->assertTrue($results[0]['route_affinity']);
        $this->assertSame('Monitor', $results[1]['title']);
        $this->assertFalse($results[1]['route_affinity']);
    }

    public function test_it_filters_by_section_and_shapes_required_payload_fields(): void
    {
        $client = new class implements DocsSearchIndexClient
        {
            public function search(string $query, ?string $domain, ?string $section, int $limit): array
            {
                return [
                    [
                        'title' => 'Discovery',
                        'snippet' => 'Discovery guidance',
                        'domain' => 'product_doc',
                        'section' => 'discovery',
                        'route_names' => [],
                        'updated_at' => '2026-03-02T08:00:00Z',
                    ],
                    [
                        'title' => 'Backups',
                        'snippet' => 'Backup settings',
                        'domain' => 'product_doc',
                        'section' => 'operations',
                        'route_names' => [],
                        'updated_at' => '2026-03-02T08:10:00Z',
                    ],
                ];
            }
        };

        $service = new DocsSearchService($client);

        $results = $service->search('discovery', null, 'discovery', null, 10);

        $this->assertCount(1, $results);
        $this->assertSame([
            'title',
            'snippet',
            'domain',
            'section',
            'slug',
            'url',
            'route_affinity',
            'updated_at',
        ], array_keys($results[0]));
    }

    public function test_it_rejects_empty_query(): void
    {
        $client = new class implements DocsSearchIndexClient
        {
            public function search(string $query, ?string $domain, ?string $section, int $limit): array
            {
                return [];
            }
        };

        $service = new DocsSearchService($client);

        $this->expectException(\InvalidArgumentException::class);

        $service->search('   ', null, null, null, 20);
    }

    public function test_it_throws_outage_exception_when_index_client_fails(): void
    {
        $client = new class implements DocsSearchIndexClient
        {
            public function search(string $query, ?string $domain, ?string $section, int $limit): array
            {
                throw new RuntimeException('Typesense connection failed');
            }
        };

        $service = new DocsSearchService($client);

        $this->expectException(DocsSearchUnavailableException::class);

        $service->search('agent', null, null, null, 20);
    }
}
