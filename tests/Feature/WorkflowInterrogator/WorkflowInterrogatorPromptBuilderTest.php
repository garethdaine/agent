<?php

declare(strict_types=1);

namespace Tests\Feature\WorkflowInterrogator;

use App\Models\User;
use App\Models\WorkflowInterrogationAttachment;
use App\Models\WorkflowInterrogationSession;
use App\Support\WorkflowInterrogator\WorkflowInterrogatorPromptBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowInterrogatorPromptBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_round_prompt_is_brief_first_and_includes_uploaded_context(): void
    {
        $user = User::factory()->create();

        $session = WorkflowInterrogationSession::query()->create([
            'user_id' => (int) $user->id,
            'runner_type' => 'claude',
            'model' => 'claude-opus-4-6',
            'project_directory' => base_path(),
            'interrogation_mode' => WorkflowInterrogationSession::MODE_WORKFLOW,
            'company_name' => 'Acme Ops',
            'company_description' => 'Operations-heavy business',
            'workflow_title' => 'Returns workflow',
            'workflow_brief' => 'Clarify how returns are processed.',
        ]);

        WorkflowInterrogationAttachment::query()->create([
            'workflow_interrogation_session_id' => (int) $session->id,
            'filename' => 'notes.md',
            'mime_type' => 'text/markdown',
            'size_bytes' => 128,
            'storage_disk' => 'local',
            'storage_path' => 'workflow-interrogator/1/1/notes.md',
            'extracted_text' => "Returns require manager approval.\nCustomer photos are collected first.",
        ]);

        $prompt = app(WorkflowInterrogatorPromptBuilder::class)
            ->buildRoundPrompt($session->fresh('attachments'), [], []);

        $this->assertStringContainsString('Start from the user brief and uploaded context first.', $prompt);
        $this->assertStringContainsString('Do not default to codebase or repository inspection.', $prompt);
        $this->assertStringContainsString('Selected working folder:', $prompt);
        $this->assertStringContainsString('notes.md (text/markdown, 128 bytes)', $prompt);
        $this->assertStringContainsString('Returns require manager approval.', $prompt);
        $this->assertStringNotContainsString('Before questioning, silently inspect repository context', $prompt);
    }
}
