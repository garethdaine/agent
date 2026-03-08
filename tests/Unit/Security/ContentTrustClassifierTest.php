<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Enums\Security\ContentTrustLevel;
use App\Services\Security\ContentTrustClassifier;
use App\Services\Security\SecurityConfigProvider;
use Tests\TestCase;

class ContentTrustClassifierTest extends TestCase
{
    private ContentTrustClassifier $classifier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->classifier = new ContentTrustClassifier(new SecurityConfigProvider);
    }

    public function test_web_fetch_is_untrusted(): void
    {
        $this->assertSame(ContentTrustLevel::Untrusted, $this->classifier->classify('web.fetch'));
    }

    public function test_web_search_is_untrusted(): void
    {
        $this->assertSame(ContentTrustLevel::Untrusted, $this->classifier->classify('web.search'));
    }

    public function test_browser_navigate_is_untrusted(): void
    {
        $this->assertSame(ContentTrustLevel::Untrusted, $this->classifier->classify('browser.navigate'));
    }

    public function test_browser_screenshot_is_untrusted(): void
    {
        $this->assertSame(ContentTrustLevel::Untrusted, $this->classifier->classify('browser.screenshot'));
    }

    public function test_browser_extract_is_untrusted(): void
    {
        $this->assertSame(ContentTrustLevel::Untrusted, $this->classifier->classify('browser.extract'));
    }

    public function test_fs_read_is_untrusted(): void
    {
        $this->assertSame(ContentTrustLevel::Untrusted, $this->classifier->classify('fs.read'));
    }

    public function test_fs_list_is_untrusted(): void
    {
        $this->assertSame(ContentTrustLevel::Untrusted, $this->classifier->classify('fs.list'));
    }

    public function test_mcp_call_is_untrusted(): void
    {
        $this->assertSame(ContentTrustLevel::Untrusted, $this->classifier->classify('mcp.call'));
    }

    public function test_attachment_text_is_untrusted(): void
    {
        $this->assertSame(ContentTrustLevel::Untrusted, $this->classifier->classify('attachment.text'));
    }

    public function test_system_prompt_is_trusted(): void
    {
        $this->assertSame(ContentTrustLevel::Trusted, $this->classifier->classify('system.prompt'));
    }

    public function test_user_direct_is_trusted(): void
    {
        $this->assertSame(ContentTrustLevel::Trusted, $this->classifier->classify('user.direct'));
    }

    public function test_user_verified_is_trusted(): void
    {
        $this->assertSame(ContentTrustLevel::Trusted, $this->classifier->classify('user.verified'));
    }

    public function test_session_feature_brief_is_contextual(): void
    {
        $this->assertSame(ContentTrustLevel::Contextual, $this->classifier->classify('session.feature_brief'));
    }

    public function test_session_tech_stack_is_contextual(): void
    {
        $this->assertSame(ContentTrustLevel::Contextual, $this->classifier->classify('session.tech_stack'));
    }

    public function test_session_discovery_is_contextual(): void
    {
        $this->assertSame(ContentTrustLevel::Contextual, $this->classifier->classify('session.discovery'));
    }

    public function test_unknown_source_defaults_to_untrusted(): void
    {
        $this->assertSame(ContentTrustLevel::Untrusted, $this->classifier->classify('foo.bar'));
    }

    public function test_empty_string_source_defaults_to_untrusted(): void
    {
        $this->assertSame(ContentTrustLevel::Untrusted, $this->classifier->classify(''));
    }

    public function test_classify_tool_result_web_fetch(): void
    {
        $this->assertSame(
            ContentTrustLevel::Untrusted,
            $this->classifier->classifyToolResult('web', ['operation' => 'fetch'])
        );
    }

    public function test_classify_tool_result_fs_read(): void
    {
        $this->assertSame(
            ContentTrustLevel::Untrusted,
            $this->classifier->classifyToolResult('fs', ['operation' => 'read'])
        );
    }

    public function test_is_untrusted_returns_true_for_untrusted(): void
    {
        $this->assertTrue($this->classifier->isUntrusted(ContentTrustLevel::Untrusted));
    }

    public function test_is_untrusted_returns_false_for_trusted(): void
    {
        $this->assertFalse($this->classifier->isUntrusted(ContentTrustLevel::Trusted));
    }
}
