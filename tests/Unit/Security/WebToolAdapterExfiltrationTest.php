<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\DTOs\Runtime\RuntimeContext;
use App\DTOs\Security\ExfiltrationInspectionResult;
use App\Enums\Runtime\RuntimeMode;
use App\Enums\Security\ExfiltrationPattern;
use App\Models\Runtime\RuntimeSession;
use App\Models\Runtime\RuntimeTurn;
use App\Models\User;
use App\Services\Runtime\Adapters\WebToolAdapter;
use App\Services\Security\ExfiltrationDetector;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class WebToolAdapterExfiltrationTest extends TestCase
{
    private ExfiltrationDetector $detector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->detector = Mockery::mock(ExfiltrationDetector::class);
    }

    private function makeAdapter(?ExfiltrationDetector $detector = null): WebToolAdapter
    {
        $adapter = new WebToolAdapter;
        if ($detector !== null) {
            $adapter->setExfiltrationDetector($detector);
        }

        return $adapter;
    }

    private function makeContext(): RuntimeContext
    {
        $session = Mockery::mock(RuntimeSession::class)->shouldIgnoreMissing();
        $session->shouldReceive('getAttribute')->with('id')->andReturn('session-uuid');
        $session->shouldReceive('getAttribute')->with('mode')->andReturn(RuntimeMode::Standard);

        $turn = Mockery::mock(RuntimeTurn::class);
        $user = Mockery::mock(User::class);

        return new RuntimeContext(
            session: $session,
            turn: $turn,
            user: $user,
            mode: RuntimeMode::Standard,
            policy: [],
            workspaceRoot: '/tmp/workspace',
        );
    }

    public function test_post_with_session_token_to_external_host_is_blocked(): void
    {
        $adapter = $this->makeAdapter($this->detector);
        $context = $this->makeContext();

        config(['runtime.web.allowed_hosts' => ['api.example.com']]);

        $this->detector->shouldReceive('inspect')
            ->with('POST', 'https://api.example.com/webhook', 'session_token=abc123secrettoken1234567890', Mockery::any())
            ->once()
            ->andReturn(new ExfiltrationInspectionResult(
                blocked: true,
                matchedPatterns: [ExfiltrationPattern::SessionTokenEcho],
                reason: 'Exfiltration patterns detected: session_token_echo',
            ));

        $result = $adapter->execute($context, [
            'operation' => 'fetch',
            'url' => 'https://api.example.com/webhook',
            'method' => 'POST',
            'body' => 'session_token=abc123secrettoken1234567890',
        ]);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('potential data exfiltration', $result->error);
        $this->assertStringContainsString('session_token_echo', $result->error);
    }

    public function test_post_with_clean_body_to_external_host_is_allowed(): void
    {
        $adapter = $this->makeAdapter($this->detector);
        $context = $this->makeContext();

        config(['runtime.web.allowed_hosts' => ['api.example.com']]);

        $this->detector->shouldReceive('inspect')
            ->with('POST', 'https://api.example.com/data', '{"name": "test"}', Mockery::any())
            ->once()
            ->andReturn(new ExfiltrationInspectionResult(
                blocked: false,
                matchedPatterns: [],
                reason: null,
            ));

        Http::fake(['api.example.com/*' => Http::response(['ok' => true], 200)]);

        $result = $adapter->execute($context, [
            'operation' => 'fetch',
            'url' => 'https://api.example.com/data',
            'method' => 'POST',
            'body' => '{"name": "test"}',
        ]);

        $this->assertTrue($result->success);
    }

    public function test_get_request_not_inspected(): void
    {
        $adapter = $this->makeAdapter($this->detector);
        $context = $this->makeContext();

        config(['runtime.web.allowed_hosts' => ['example.com']]);

        $this->detector->shouldNotReceive('inspect');

        Http::fake(['example.com/*' => Http::response('ok', 200)]);

        $result = $adapter->execute($context, [
            'operation' => 'fetch',
            'url' => 'https://example.com/page',
            'method' => 'GET',
        ]);

        $this->assertTrue($result->success);
    }

    public function test_null_detector_no_inspection(): void
    {
        $adapter = $this->makeAdapter(null);
        $context = $this->makeContext();

        config(['runtime.web.allowed_hosts' => ['example.com']]);

        Http::fake(['example.com/*' => Http::response('ok', 200)]);

        $result = $adapter->execute($context, [
            'operation' => 'fetch',
            'url' => 'https://example.com/api',
            'method' => 'POST',
            'body' => 'session_token=abc123secrettoken1234567890',
        ]);

        $this->assertTrue($result->success);
    }
}
