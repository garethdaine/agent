<?php

declare(strict_types=1);

namespace Tests\Unit\Messenger;

use App\Support\Messenger\MarkdownSafeContent;
use Tests\TestCase;

class MarkdownSafeContentTest extends TestCase
{
    public function test_short_content_returned_unchanged(): void
    {
        $content = 'Hello world';
        $this->assertEquals($content, MarkdownSafeContent::truncate($content, 2000));
    }

    public function test_truncates_at_paragraph_break(): void
    {
        $paragraph1 = str_repeat('word ', 40); // ~200 chars
        $paragraph2 = str_repeat('more ', 40);
        $content = $paragraph1."\n\n".$paragraph2;

        $result = MarkdownSafeContent::truncate($content, 250);

        $this->assertStringNotContainsString($paragraph2, $result);
        $this->assertStringEndsWith('…', $result);
    }

    public function test_truncates_at_newline_when_no_paragraph_break(): void
    {
        $lines = implode("\n", array_fill(0, 30, str_repeat('x', 10)));
        $result = MarkdownSafeContent::truncate($lines, 200);

        $this->assertLessThanOrEqual(200, mb_strlen($result));
        $this->assertStringEndsWith('…', $result);
    }

    public function test_truncates_at_whitespace_when_no_newlines(): void
    {
        $content = implode(' ', array_fill(0, 50, 'longword'));
        $result = MarkdownSafeContent::truncate($content, 100);

        $this->assertLessThanOrEqual(100, mb_strlen($result));
        $this->assertStringEndsWith('…', $result);
    }

    public function test_hard_break_when_no_separators(): void
    {
        $content = str_repeat('a', 300);
        $result = MarkdownSafeContent::truncate($content, 100);

        $this->assertLessThanOrEqual(100, mb_strlen($result));
        $this->assertStringEndsWith('…', $result);
    }

    public function test_detects_unclosed_code_fence(): void
    {
        $this->assertTrue(MarkdownSafeContent::hasUnclosedFence("```php\ncode"));
        $this->assertFalse(MarkdownSafeContent::hasUnclosedFence("```php\ncode\n```"));
        $this->assertTrue(MarkdownSafeContent::hasUnclosedFence("~~~\ncode"));
        $this->assertFalse(MarkdownSafeContent::hasUnclosedFence("~~~\ncode\n~~~"));
    }

    public function test_truncate_closes_unclosed_fence(): void
    {
        $content = "Text\n\n```python\n".str_repeat("print('hello')\n", 50);
        $result = MarkdownSafeContent::truncate($content, 200);

        $this->assertFalse(MarkdownSafeContent::hasUnclosedFence($result));
    }

    public function test_does_not_split_inside_code_fence(): void
    {
        $beforeFence = str_repeat('a', 80);
        $fence = "```\nshort code\n```";
        $afterFence = str_repeat('b', 80);
        $content = $beforeFence."\n\n".$fence."\n\n".$afterFence;

        // Limit is just past the paragraph break before the fence
        $result = MarkdownSafeContent::truncate($content, 100);

        // Should break before the fence, not inside it
        $this->assertLessThanOrEqual(100, mb_strlen($result));
    }

    public function test_prepare_for_preview_includes_cursor(): void
    {
        $result = MarkdownSafeContent::prepareForPreview('Hello world', 2000);
        $this->assertStringEndsWith(' ▍', $result);
    }

    public function test_prepare_for_preview_closes_open_fence(): void
    {
        $content = "```python\ndef foo():";
        $result = MarkdownSafeContent::prepareForPreview($content, 2000);

        $this->assertStringContainsString('```', $result);
        $this->assertStringEndsWith('▍', $result);

        $withoutCursor = str_replace(' ▍', '', $result);
        $this->assertFalse(MarkdownSafeContent::hasUnclosedFence($withoutCursor));
    }

    public function test_closed_fence_not_double_closed(): void
    {
        $content = "```python\nprint('hi')\n```\nDone";
        $result = MarkdownSafeContent::prepareForPreview($content, 2000);

        $fenceCount = substr_count($result, '```');
        $this->assertEquals(2, $fenceCount);
    }

    public function test_multiple_fences_handled(): void
    {
        $content = "```js\nalert(1)\n```\n\nText\n\n```py\nprint(2)\n```";

        $this->assertFalse(MarkdownSafeContent::hasUnclosedFence($content));
        $this->assertEquals($content, MarkdownSafeContent::truncate($content, 5000));
    }

    public function test_tilde_fences_handled(): void
    {
        $this->assertTrue(MarkdownSafeContent::hasUnclosedFence("~~~ruby\nputs 'hi'"));
        $this->assertFalse(MarkdownSafeContent::hasUnclosedFence("~~~ruby\nputs 'hi'\n~~~"));
    }
}
