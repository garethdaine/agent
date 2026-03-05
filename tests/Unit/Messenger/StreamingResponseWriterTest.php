<?php

declare(strict_types=1);

namespace Tests\Unit\Messenger;

use App\Contracts\Messenger\ConnectorAdapterInterface;
use App\DTOs\Messenger\ProviderResponse;
use App\DTOs\Messenger\StreamingConfig;
use App\Models\ChatSession;
use App\Services\Messenger\StreamingResponseWriter;
use Mockery;
use Tests\TestCase;

class StreamingResponseWriterTest extends TestCase
{
    private ConnectorAdapterInterface|Mockery\MockInterface $adapter;

    private ChatSession|Mockery\MockInterface $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adapter = Mockery::mock(ConnectorAdapterInterface::class);
        $this->session = Mockery::mock(ChatSession::class);
    }

    private function immediateConfig(): StreamingConfig
    {
        return new StreamingConfig(throttleMs: 0, minInitialChars: 0);
    }

    private function throttledConfig(): StreamingConfig
    {
        return new StreamingConfig(throttleMs: 999_000, minInitialChars: 0);
    }

    public function test_append_accumulates_content(): void
    {
        $this->adapter->shouldReceive('editMessage')->andReturn(
            ProviderResponse::success('msg-1')
        );

        $writer = new StreamingResponseWriter(
            $this->adapter, $this->session, 'msg-1', $this->immediateConfig()
        );

        $writer->append('Hello ');
        $writer->append('world');

        $this->assertEquals('Hello world', $writer->getContent());
    }

    public function test_finalize_edits_message_without_cursor(): void
    {
        $editedContent = null;
        $this->adapter->shouldReceive('editMessage')
            ->andReturnUsing(function ($session, $msgId, $content) use (&$editedContent) {
                $editedContent = $content;

                return ProviderResponse::success($msgId);
            });

        $writer = new StreamingResponseWriter(
            $this->adapter, $this->session, 'msg-1', $this->throttledConfig()
        );

        $writer->append('Final response');
        $writer->finalize();

        $this->assertEquals('Final response', $editedContent);
        $this->assertStringNotContainsString('▍', $editedContent);
    }

    public function test_intermediate_flush_includes_streaming_cursor(): void
    {
        $editedContent = null;
        $this->adapter->shouldReceive('editMessage')
            ->andReturnUsing(function ($session, $msgId, $content) use (&$editedContent) {
                $editedContent = $content;

                return ProviderResponse::success($msgId);
            });

        $writer = new StreamingResponseWriter(
            $this->adapter, $this->session, 'msg-1', $this->immediateConfig()
        );

        $writer->append('Partial text');

        $this->assertStringContainsString('▍', $editedContent);
        $this->assertStringContainsString('Partial text', $editedContent);
    }

    public function test_throttling_prevents_rapid_edits_after_first(): void
    {
        $this->adapter->shouldReceive('editMessage')
            ->andReturn(ProviderResponse::success('msg-1'));

        $writer = new StreamingResponseWriter(
            $this->adapter, $this->session, 'msg-1', $this->throttledConfig()
        );

        $writer->append('chunk 1');
        $initialEdits = $writer->getEditCount();
        $this->assertEquals(1, $initialEdits);

        $writer->append('chunk 2');
        $writer->append('chunk 3');

        $this->assertEquals(1, $writer->getEditCount());
        $this->assertEquals('chunk 1chunk 2chunk 3', $writer->getContent());

        $writer->finalize();
        $this->assertEquals(2, $writer->getEditCount());
    }

    public function test_force_write_overrides_accumulated_content(): void
    {
        $editedContent = null;
        $this->adapter->shouldReceive('editMessage')
            ->andReturnUsing(function ($session, $msgId, $content) use (&$editedContent) {
                $editedContent = $content;

                return ProviderResponse::success($msgId);
            });

        $writer = new StreamingResponseWriter(
            $this->adapter, $this->session, 'msg-1', $this->throttledConfig()
        );

        $writer->append('partial response');
        $writer->forceWrite('Error: something went wrong');

        $this->assertEquals('Error: something went wrong', $writer->getContent());
        $this->assertEquals('Error: something went wrong', $editedContent);
    }

    public function test_truncates_content_at_max_chars(): void
    {
        $editedContent = null;
        $this->adapter->shouldReceive('editMessage')
            ->andReturnUsing(function ($session, $msgId, $content) use (&$editedContent) {
                $editedContent = $content;

                return ProviderResponse::success($msgId);
            });

        $writer = new StreamingResponseWriter(
            $this->adapter, $this->session, 'msg-1', $this->throttledConfig()
        );

        $writer->append(str_repeat('a', 2500));
        $writer->finalize();

        $this->assertLessThanOrEqual(2000, mb_strlen($editedContent));
        $this->assertStringEndsWith('…', $editedContent);
    }

    public function test_finalize_is_noop_when_content_is_empty(): void
    {
        $this->adapter->shouldNotReceive('editMessage');

        $writer = new StreamingResponseWriter(
            $this->adapter, $this->session, 'msg-1', $this->throttledConfig()
        );

        $writer->finalize();

        $this->assertEquals(0, $writer->getEditCount());
    }

    public function test_edit_failure_is_logged_but_does_not_throw(): void
    {
        $this->adapter->shouldReceive('editMessage')
            ->andThrow(new \RuntimeException('Discord rate limited'));

        $writer = new StreamingResponseWriter(
            $this->adapter, $this->session, 'msg-1', $this->immediateConfig()
        );

        $writer->append('some text');

        $this->assertEquals(0, $writer->getEditCount());
        $this->assertEquals('some text', $writer->getContent());
    }

    public function test_min_initial_chars_delays_first_edit(): void
    {
        $this->adapter->shouldReceive('editMessage')
            ->andReturn(ProviderResponse::success('msg-1'));

        $config = new StreamingConfig(throttleMs: 0, minInitialChars: 50);

        $writer = new StreamingResponseWriter(
            $this->adapter, $this->session, 'msg-1', $config
        );

        $writer->append('short');
        $this->assertEquals(0, $writer->getEditCount());

        $writer->append(str_repeat('x', 50));
        $this->assertEquals(1, $writer->getEditCount());
    }

    public function test_uses_adapter_streaming_config_when_none_provided(): void
    {
        $this->adapter->shouldReceive('getStreamingConfig')
            ->once()
            ->andReturn(StreamingConfig::telegram());

        $this->adapter->shouldReceive('editMessage')
            ->andReturn(ProviderResponse::success('msg-1'));

        $writer = new StreamingResponseWriter(
            $this->adapter, $this->session, 'msg-1'
        );

        $this->assertEquals(4096, $writer->getConfig()->maxMessageChars);
        $this->assertEquals(1000.0, $writer->getConfig()->throttleMs);
    }

    public function test_per_channel_config_discord(): void
    {
        $config = StreamingConfig::discord();

        $this->assertEquals(2000, $config->maxMessageChars);
        $this->assertEquals(1200.0, $config->throttleMs);
        $this->assertFalse($config->supportsNativeStreaming);
    }

    public function test_per_channel_config_telegram(): void
    {
        $config = StreamingConfig::telegram();

        $this->assertEquals(4096, $config->maxMessageChars);
        $this->assertEquals(1000.0, $config->throttleMs);
        $this->assertFalse($config->supportsNativeStreaming);
    }

    public function test_per_channel_config_slack(): void
    {
        $config = StreamingConfig::slack();

        $this->assertEquals(4000, $config->maxMessageChars);
        $this->assertEquals(1000.0, $config->throttleMs);
        $this->assertTrue($config->supportsNativeStreaming);
    }

    public function test_code_fence_preserved_during_truncation(): void
    {
        $editedContent = null;
        $this->adapter->shouldReceive('editMessage')
            ->andReturnUsing(function ($session, $msgId, $content) use (&$editedContent) {
                $editedContent = $content;

                return ProviderResponse::success($msgId);
            });

        $config = new StreamingConfig(maxMessageChars: 100, throttleMs: 0, minInitialChars: 0);

        $writer = new StreamingResponseWriter(
            $this->adapter, $this->session, 'msg-1', $config
        );

        $content = "Some text\n\n```php\n".str_repeat('echo "x";'."\n", 20).'```';
        $writer->append($content);

        $this->assertStringContainsString('```', $editedContent);
    }

    public function test_intermediate_preview_closes_unclosed_fences(): void
    {
        $editedContent = null;
        $this->adapter->shouldReceive('editMessage')
            ->andReturnUsing(function ($session, $msgId, $content) use (&$editedContent) {
                $editedContent = $content;

                return ProviderResponse::success($msgId);
            });

        $config = new StreamingConfig(maxMessageChars: 500, throttleMs: 0, minInitialChars: 0);

        $writer = new StreamingResponseWriter(
            $this->adapter, $this->session, 'msg-1', $config
        );

        $writer->append("Here's some code:\n\n```python\ndef hello():\n    print('world')");

        // Preview should close the unclosed fence
        $this->assertStringContainsString('```', $editedContent);
        $fenceCount = substr_count($editedContent, '```');
        $this->assertEquals(0, $fenceCount % 2, 'Code fences should be balanced in preview');
    }
}
