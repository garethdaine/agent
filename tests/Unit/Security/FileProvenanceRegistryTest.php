<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Enums\Security\ContentTrustLevel;
use App\Models\Runtime\RuntimeSession;
use App\Services\Security\FileProvenanceRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class FileProvenanceRegistryTest extends TestCase
{
    use RefreshDatabase;

    private FileProvenanceRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new FileProvenanceRegistry();
    }

    public function test_record_then_lookup_returns_correct_trust_level(): void
    {
        $session = RuntimeSession::factory()->create();

        $this->registry->record(
            sessionId: $session->id,
            filePath: '/tmp/download.html',
            trustLevel: ContentTrustLevel::Untrusted,
            origin: 'web.fetch',
        );

        $result = $this->registry->lookup($session->id, '/tmp/download.html');

        $this->assertEquals(ContentTrustLevel::Untrusted, $result);
    }

    public function test_redis_primary_lookup_hits_redis_before_database(): void
    {
        $session = RuntimeSession::factory()->create();

        $this->registry->record(
            sessionId: $session->id,
            filePath: '/tmp/file.txt',
            trustLevel: ContentTrustLevel::Contextual,
            origin: 'session.discovery',
        );

        // Verify Redis has the data
        $redisKey = "security:provenance:{$session->id}";
        $redisValue = Redis::connection('cache')->hget($redisKey, '/tmp/file.txt');
        $this->assertNotNull($redisValue);

        $decoded = json_decode($redisValue, true);
        $this->assertEquals('contextual', $decoded['trustLevel']);
    }

    public function test_redis_miss_falls_back_to_database_jsonb(): void
    {
        $session = RuntimeSession::factory()->create();

        $this->registry->record(
            sessionId: $session->id,
            filePath: '/tmp/db-file.txt',
            trustLevel: ContentTrustLevel::Untrusted,
            origin: 'mcp.call',
            toolCallId: 'tc-123',
        );

        // Delete the Redis key to simulate a miss
        $redisKey = "security:provenance:{$session->id}";
        Redis::connection('cache')->del($redisKey);

        // Lookup should fall back to DB
        $result = $this->registry->lookup($session->id, '/tmp/db-file.txt');
        $this->assertEquals(ContentTrustLevel::Untrusted, $result);
    }

    public function test_lookup_for_unknown_file_returns_null(): void
    {
        $session = RuntimeSession::factory()->create();

        $result = $this->registry->lookup($session->id, '/nonexistent/file.txt');
        $this->assertNull($result);
    }

    public function test_redis_hash_key_has_24h_ttl(): void
    {
        $session = RuntimeSession::factory()->create();

        $this->registry->record(
            sessionId: $session->id,
            filePath: '/tmp/ttl-test.txt',
            trustLevel: ContentTrustLevel::Untrusted,
            origin: 'web.fetch',
        );

        $redisKey = "security:provenance:{$session->id}";
        $ttl = Redis::connection('cache')->ttl($redisKey);

        // TTL should be close to 86400 (24 hours)
        $this->assertGreaterThan(86300, $ttl);
        $this->assertLessThanOrEqual(86400, $ttl);
    }

    public function test_get_all_returns_all_records_for_session(): void
    {
        $session = RuntimeSession::factory()->create();

        $this->registry->record($session->id, '/tmp/a.txt', ContentTrustLevel::Untrusted, 'web.fetch');
        $this->registry->record($session->id, '/tmp/b.txt', ContentTrustLevel::Contextual, 'session.discovery');
        $this->registry->record($session->id, '/tmp/c.txt', ContentTrustLevel::Trusted, 'user.direct');

        $all = $this->registry->getAll($session->id);

        $this->assertCount(3, $all);
        $this->assertArrayHasKey('/tmp/a.txt', $all);
        $this->assertArrayHasKey('/tmp/b.txt', $all);
        $this->assertArrayHasKey('/tmp/c.txt', $all);
    }

    public function test_purge_session_removes_all_records(): void
    {
        $session = RuntimeSession::factory()->create();

        $this->registry->record($session->id, '/tmp/purge.txt', ContentTrustLevel::Untrusted, 'web.fetch');

        $this->registry->purgeSession($session->id);

        // Redis should be gone
        $redisKey = "security:provenance:{$session->id}";
        $this->assertEquals(0, Redis::connection('cache')->exists($redisKey));

        // Lookup should return null
        $this->assertNull($this->registry->lookup($session->id, '/tmp/purge.txt'));

        // DB column should be null
        $session->refresh();
        $this->assertNull($session->file_provenance);
    }

    public function test_multiple_files_tracked_independently(): void
    {
        $session = RuntimeSession::factory()->create();

        $this->registry->record($session->id, '/tmp/trusted.txt', ContentTrustLevel::Trusted, 'user.direct');
        $this->registry->record($session->id, '/tmp/untrusted.txt', ContentTrustLevel::Untrusted, 'web.fetch');

        $this->assertEquals(ContentTrustLevel::Trusted, $this->registry->lookup($session->id, '/tmp/trusted.txt'));
        $this->assertEquals(ContentTrustLevel::Untrusted, $this->registry->lookup($session->id, '/tmp/untrusted.txt'));
    }

    public function test_record_updates_database_jsonb_column(): void
    {
        $session = RuntimeSession::factory()->create();

        $this->registry->record(
            sessionId: $session->id,
            filePath: '/tmp/db-check.txt',
            trustLevel: ContentTrustLevel::Untrusted,
            origin: 'web.fetch',
            toolCallId: 'tc-456',
        );

        $session->refresh();
        $provenance = $session->file_provenance;

        $this->assertNotNull($provenance);
        $this->assertCount(1, $provenance);
        $this->assertEquals('/tmp/db-check.txt', $provenance[0]['filePath']);
        $this->assertEquals('untrusted', $provenance[0]['trustLevel']);
        $this->assertEquals('web.fetch', $provenance[0]['origin']);
        $this->assertEquals('tc-456', $provenance[0]['toolCallId']);
        $this->assertArrayHasKey('createdAt', $provenance[0]);
    }
}
