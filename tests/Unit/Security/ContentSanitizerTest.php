<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\DTOs\Security\SanitizationResult;
use App\Services\Security\ContentSanitizer;
use App\Services\Security\SecurityConfigProvider;
use App\Support\Security\TokenEstimator;
use Mockery;
use Tests\TestCase;

class ContentSanitizerTest extends TestCase
{
    private ContentSanitizer $sanitizer;

    private SecurityConfigProvider $configProvider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configProvider = Mockery::mock(SecurityConfigProvider::class);
        $this->configProvider->shouldReceive('get')
            ->with('strip_html', Mockery::any())
            ->andReturn(true)
            ->byDefault();
        $this->configProvider->shouldReceive('get')
            ->with('tool_result_max_tokens', Mockery::any())
            ->andReturn(8000)
            ->byDefault();
        $this->configProvider->shouldReceive('get')
            ->with('content_wrapping_markers', Mockery::any())
            ->andReturn(false)
            ->byDefault();

        $this->sanitizer = new ContentSanitizer(
            $this->configProvider,
            new TokenEstimator(),
        );
    }

    public function test_html_stripping_removes_tags(): void
    {
        $result = $this->sanitizer->sanitize('<p>Hello <b>world</b></p>', 'test.source');

        $this->assertEquals('Hello world', $result->content);
        $this->assertTrue($result->sanitized);
        $this->assertNotEmpty($result->strippedElements);
    }

    public function test_script_removal(): void
    {
        $result = $this->sanitizer->sanitize("text<script>alert('xss')</script>more", 'test.source');

        $this->assertEquals('textmore', $result->content);
        $this->assertTrue($result->sanitized);
        $this->assertContains('script', $result->strippedElements);
    }

    public function test_style_removal(): void
    {
        $result = $this->sanitizer->sanitize('text<style>.x{color:red}</style>more', 'test.source');

        $this->assertEquals('textmore', $result->content);
        $this->assertTrue($result->sanitized);
        $this->assertContains('style', $result->strippedElements);
    }

    public function test_event_handler_removal(): void
    {
        $result = $this->sanitizer->sanitize('<div onclick="evil()">text</div>', 'test.source');

        $this->assertEquals('text', $result->content);
        $this->assertTrue($result->sanitized);
    }

    public function test_token_truncation(): void
    {
        $this->configProvider->shouldReceive('get')
            ->with('tool_result_max_tokens', Mockery::any())
            ->andReturn(100);

        $content = str_repeat('word ', 4000); // ~20000 chars, ~5000 tokens

        $result = $this->sanitizer->sanitize($content, 'test.source');

        $this->assertTrue($result->truncated);
        $this->assertStringContainsString('[Content truncated:', $result->content);
    }

    public function test_content_within_limits_unchanged(): void
    {
        $this->configProvider->shouldReceive('get')
            ->with('strip_html', Mockery::any())
            ->andReturn(false);

        $content = 'Simple text content';

        $result = $this->sanitizer->sanitize($content, 'test.source');

        $this->assertEquals('Simple text content', $result->content);
        $this->assertFalse($result->truncated);
    }

    public function test_content_wrapping_markers_present_when_enabled(): void
    {
        $this->configProvider->shouldReceive('get')
            ->with('content_wrapping_markers', Mockery::any())
            ->andReturn(true);
        $this->configProvider->shouldReceive('get')
            ->with('strip_html', Mockery::any())
            ->andReturn(false);

        $result = $this->sanitizer->sanitize('some content', 'web.fetch');

        $this->assertStringContainsString('<external_content source="web.fetch">', $result->content);
        $this->assertStringContainsString('</external_content>', $result->content);
    }

    public function test_content_wrapping_markers_absent_when_disabled(): void
    {
        $this->configProvider->shouldReceive('get')
            ->with('content_wrapping_markers', Mockery::any())
            ->andReturn(false);
        $this->configProvider->shouldReceive('get')
            ->with('strip_html', Mockery::any())
            ->andReturn(false);

        $result = $this->sanitizer->sanitize('some content', 'web.fetch');

        $this->assertStringNotContainsString('<external_content', $result->content);
        $this->assertStringNotContainsString('</external_content>', $result->content);
    }

    public function test_json_with_embedded_html(): void
    {
        $result = $this->sanitizer->sanitize('{"html": "<script>bad</script>"}', 'test.source');

        $this->assertStringNotContainsString('<script>', $result->content);
        $this->assertStringNotContainsString('</script>', $result->content);
        $this->assertTrue($result->sanitized);
    }

    public function test_empty_content_returns_empty_result(): void
    {
        $result = $this->sanitizer->sanitize('', 'test.source');

        $this->assertEquals('', $result->content);
        $this->assertFalse($result->sanitized);
        $this->assertFalse($result->truncated);
    }

    public function test_sanitization_result_dto_fields(): void
    {
        $result = $this->sanitizer->sanitize('<b>bold</b> <script>evil()</script>', 'test.source');

        $this->assertInstanceOf(SanitizationResult::class, $result);
        $this->assertIsString($result->content);
        $this->assertIsBool($result->sanitized);
        $this->assertIsBool($result->truncated);
        $this->assertIsInt($result->originalTokenCount);
        $this->assertIsArray($result->strippedElements);
        $this->assertContains('script', $result->strippedElements);
    }
}
