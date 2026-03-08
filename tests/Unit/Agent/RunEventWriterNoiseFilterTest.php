<?php

declare(strict_types=1);

namespace Tests\Unit\Agent;

use App\Models\AgentJobRun;
use App\Models\AgentRunEvent;
use App\Support\Agent\RunEventWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RunEventWriterNoiseFilterTest extends TestCase
{
    use RefreshDatabase;

    private AgentJobRun $run;

    private RunEventWriter $writer;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('agent.log_filtering.suppress_machine_noise', true);

        $this->run = AgentJobRun::factory()->running()->create();
        $this->writer = new RunEventWriter($this->run);
    }

    // ── MCP tool list dump detection ────────────────────────────────

    public function test_suppresses_mcp_tool_list_dump(): void
    {
        $chunk = implode(',', [
            '"mcp__claude_ai_Linear__get_document"',
            '"mcp__claude_ai_Linear__list_documents"',
            '"mcp__claude_ai_Linear__create_document"',
            '"mcp__claude_ai_Linear__update_document"',
            '"mcp__claude_ai_Linear__extract_images"',
            '"mcp__claude_ai_Linear__get_issue"',
            '"mcp__claude_ai_Linear__list_issues"',
            '"mcp__claude_ai_Linear__save_issue"',
            '"mcp__claude_ai_Linear__list_issue_statuses"',
            '"mcp__claude_ai_Linear__get_issue_status"',
        ]);

        $this->writer->appendOutput('stdout', $chunk);

        $this->assertNoStdoutEvents();
        $this->assertNoiseSuppressed();
    }

    public function test_does_not_suppress_single_mcp_tool_mention(): void
    {
        $chunk = 'I will use mcp__linear_server__get_issue to fetch the issue details.';

        $this->writer->appendOutput('stdout', $chunk);

        $this->assertStdoutEventCount(1);
    }

    public function test_does_not_suppress_agent_discussing_two_mcp_tools(): void
    {
        $chunk = 'Let me call mcp__linear_server__get_issue and mcp__linear_server__list_comments to gather context.';

        $this->writer->appendOutput('stdout', $chunk);

        $this->assertStdoutEventCount(1);
    }

    // ── Config/skill name list dump detection ───────────────────────

    public function test_suppresses_config_name_list_dump(): void
    {
        $chunk = ',"tanstack-query","api-response-optimization","vitest-testing",'
            .'"cloudflare-workers-migration","bun-nextjs","ai-sdk-ui",'
            .'"google-gemini-embeddings","better-chatbot-patterns",'
            .'"cloudflare-workers-ci-cd","scenario-analyzer","strategy-pattern"';

        $this->writer->appendOutput('stdout', $chunk);

        $this->assertNoStdoutEvents();
        $this->assertNoiseSuppressed();
    }

    public function test_does_not_suppress_short_hyphenated_list(): void
    {
        $chunk = 'Available options: "dark-mode", "light-mode", "auto-detect".';

        $this->writer->appendOutput('stdout', $chunk);

        $this->assertStdoutEventCount(1);
    }

    // ── Session metadata fragment detection ─────────────────────────

    public function test_suppresses_session_metadata_fragment(): void
    {
        $chunk = 'Overage":false},"uuid":"97b7a015-d71e-427c-9008-1520e5c6dd30",'
            .'"session_id":"b4a776cf-f2d2-4406-ac1c-552402d3346d"}';

        $this->writer->appendOutput('stdout', $chunk);

        $this->assertNoStdoutEvents();
        $this->assertNoiseSuppressed();
    }

    public function test_suppresses_parent_tool_use_id_metadata(): void
    {
        $chunk = '{"parent_tool_use_id":"toolu_01SLChhkhN8xB5HLDbX6JoPk",'
            .'"session_id":"b4a776cf-f2d2-4406-ac1c-552402d3346d",'
            .'"uuid":"979aecab-5a46-4e12-9361-4b9b9e82edbe"}';

        $this->writer->appendOutput('stdout', $chunk);

        $this->assertNoStdoutEvents();
        $this->assertNoiseSuppressed();
    }

    public function test_does_not_suppress_agent_output_mentioning_session_id(): void
    {
        $chunk = 'The session_id for this conversation is "b4a776cf-f2d2-4406-ac1c-552402d3346d". '
            .'I will use this to resume the conversation later. Let me proceed with the task.';

        $this->writer->appendOutput('stdout', $chunk);

        $this->assertStdoutEventCount(1);
    }

    // ── Tool result metadata echo detection ─────────────────────────

    public function test_suppresses_tool_result_metadata_echo(): void
    {
        $chunk = '? Property (2): property-listing-generator, tenancy-compliance-checker","numLines":500,"startLine":1,"totalLines":1506}}}';

        $this->writer->appendOutput('stdout', $chunk);

        $this->assertNoStdoutEvents();
        $this->assertNoiseSuppressed();
    }

    public function test_suppresses_full_tool_result_echo(): void
    {
        $chunk = 'ne","swiftui-26-reference","cloudflare-zero-trust-access","numLines":42,"startLine":1,"totalLines":42}}}';

        $this->writer->appendOutput('stdout', $chunk);

        $this->assertNoStdoutEvents();
    }

    // ── Normal agent output passes through ──────────────────────────

    public function test_passes_through_normal_agent_reasoning(): void
    {
        $chunk = 'Let me analyze the codebase to understand how the authentication system works. '
            .'I will start by reading the middleware files and then trace the request lifecycle.';

        $this->writer->appendOutput('stdout', $chunk);

        $this->assertStdoutEventCount(1);
    }

    public function test_passes_through_error_messages(): void
    {
        $chunk = 'Error: Connection refused when connecting to the database. '
            .'The PostgreSQL server at localhost:5432 is not responding.';

        $this->writer->appendOutput('stdout', $chunk);

        $this->assertStdoutEventCount(1);
    }

    public function test_passes_through_tool_call_descriptions(): void
    {
        $chunk = 'Tool call: Read (25 output tokens)';

        $this->writer->appendOutput('stdout', $chunk);

        $this->assertStdoutEventCount(1);
    }

    // ── Config flag respected ───────────────────────────────────────

    public function test_noise_passes_through_when_config_disabled(): void
    {
        config()->set('agent.log_filtering.suppress_machine_noise', false);

        $chunk = implode(',', [
            '"mcp__server__tool1"', '"mcp__server__tool2"',
            '"mcp__server__tool3"', '"mcp__server__tool4"',
            '"mcp__server__tool5"', '"mcp__server__tool6"',
        ]);

        $this->writer->appendOutput('stdout', $chunk);

        $this->assertStdoutEventCount(1);
    }

    // ── Byte counting and metadata tracking ─────────────────────────

    public function test_byte_counters_increment_for_suppressed_chunks(): void
    {
        $chunk = implode(',', array_map(
            fn ($i) => '"mcp__server__tool'.$i.'"',
            range(1, 10)
        ));

        $this->writer->appendOutput('stdout', $chunk);

        $this->run->refresh();
        $this->assertGreaterThan(0, $this->run->stdout_bytes_pre);
    }

    public function test_suppression_metadata_tracked(): void
    {
        $chunk = implode(',', array_map(
            fn ($i) => '"mcp__server__tool'.$i.'"',
            range(1, 10)
        ));

        $this->writer->appendOutput('stdout', $chunk);

        $this->run->refresh();
        $metadata = (array) $this->run->metadata_json;
        $this->assertEquals(1, $metadata['noise_suppressed_chunks'] ?? 0);
        $this->assertGreaterThan(0, $metadata['noise_suppressed_bytes'] ?? 0);
    }

    public function test_lifecycle_event_emitted_on_first_suppression_only(): void
    {
        $chunk1 = implode(',', array_map(fn ($i) => '"mcp__a__tool'.$i.'"', range(1, 6)));
        $chunk2 = implode(',', array_map(fn ($i) => '"mcp__b__tool'.$i.'"', range(1, 6)));

        $this->writer->appendOutput('stdout', $chunk1);
        $this->writer->appendOutput('stdout', $chunk2);

        $lifecycleEvents = AgentRunEvent::query()
            ->where('agent_job_run_id', $this->run->id)
            ->where('event_type', 'lifecycle')
            ->get();

        $noiseEvents = $lifecycleEvents->filter(function ($event) {
            $payload = json_decode($event->payload, true);

            return is_array($payload) && ($payload['type'] ?? '') === 'noise_filtering_active';
        });

        $this->assertCount(1, $noiseEvents, 'Should emit noise_filtering_active lifecycle event exactly once');
    }

    public function test_multiple_noise_types_all_suppressed(): void
    {
        // MCP tool list
        $this->writer->appendOutput('stdout', implode(',', array_map(
            fn ($i) => '"mcp__server__tool'.$i.'"',
            range(1, 8)
        )));

        // Config name list
        $this->writer->appendOutput('stdout', ',"tanstack-query","vitest-testing","bun-nextjs","ai-sdk-ui","clerk-auth","zustand","shadcn-vue","pinia-v3","nuxt-core"');

        // Session metadata
        $this->writer->appendOutput('stdout', '{"uuid":"97b7a015-d71e-427c-9008-1520e5c6dd30","session_id":"b4a776cf-f2d2-4406-ac1c-552402d3346d"}');

        $this->assertNoStdoutEvents();

        $this->run->refresh();
        $metadata = (array) $this->run->metadata_json;
        $this->assertEquals(3, $metadata['noise_suppressed_chunks'] ?? 0);
    }

    // ── Helpers ──────────────────────────────────────────────────────

    private function assertNoStdoutEvents(): void
    {
        $count = AgentRunEvent::query()
            ->where('agent_job_run_id', $this->run->id)
            ->where('event_type', 'stdout')
            ->count();

        $this->assertEquals(0, $count, "Expected no stdout events but found {$count}");
    }

    private function assertStdoutEventCount(int $expected): void
    {
        $count = AgentRunEvent::query()
            ->where('agent_job_run_id', $this->run->id)
            ->where('event_type', 'stdout')
            ->count();

        $this->assertEquals($expected, $count, "Expected {$expected} stdout event(s) but found {$count}");
    }

    private function assertNoiseSuppressed(): void
    {
        $this->run->refresh();
        $metadata = (array) $this->run->metadata_json;
        $this->assertGreaterThan(0, $metadata['noise_suppressed_chunks'] ?? 0, 'Expected noise suppression to be tracked');
    }
}
