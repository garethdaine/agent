<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Enums\TaskCategory;
use App\Models\InterrogationBuildTask;
use App\Models\InterrogationSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InterrogationBuildTaskComplianceTest extends TestCase
{
    use RefreshDatabase;

    private InterrogationSession $session;

    protected function setUp(): void
    {
        parent::setUp();
        $this->session = InterrogationSession::factory()->create();
    }

    public function test_task_category_defaults_to_custom(): void
    {
        $task = InterrogationBuildTask::create([
            'interrogation_session_id' => $this->session->id,
            'sequence' => 1,
            'title' => 'Test Task',
            'status' => InterrogationBuildTask::STATUS_PENDING,
        ]);

        // Refresh to get the database default value
        $task->refresh();

        $this->assertEquals(TaskCategory::CUSTOM, $task->task_category);
    }

    public function test_task_category_cast_works_correctly(): void
    {
        $task = InterrogationBuildTask::create([
            'interrogation_session_id' => $this->session->id,
            'sequence' => 1,
            'title' => 'Test Task',
            'status' => InterrogationBuildTask::STATUS_PENDING,
            'task_category' => 'feature',
        ]);

        $this->assertInstanceOf(TaskCategory::class, $task->task_category);
        $this->assertEquals(TaskCategory::FEATURE, $task->task_category);
    }

    public function test_get_compliance_metadata_returns_compliance_fields_only(): void
    {
        $task = InterrogationBuildTask::create([
            'interrogation_session_id' => $this->session->id,
            'sequence' => 1,
            'title' => 'Test Task',
            'status' => InterrogationBuildTask::STATUS_PENDING,
            'metadata_json' => [
                'workflow_policy_version' => '1.0',
                'complexity_classification' => 'non_trivial',
                'plan_required' => true,
                'plan_completed' => false,
                'plan_evidence' => ['format' => 'markdown', 'content' => '# Plan'],
                'verification_required' => true,
                'verification_completed' => false,
                'verification_evidence' => null,
                'compliance_block_reason' => null,
                'unrelated_field' => 'should_not_be_returned',
                'another_unrelated' => 123,
            ],
        ]);

        $complianceMetadata = $task->getComplianceMetadata();

        $this->assertArrayHasKey('workflow_policy_version', $complianceMetadata);
        $this->assertArrayHasKey('complexity_classification', $complianceMetadata);
        $this->assertArrayHasKey('plan_required', $complianceMetadata);
        $this->assertArrayHasKey('plan_completed', $complianceMetadata);
        $this->assertArrayHasKey('plan_evidence', $complianceMetadata);
        $this->assertArrayHasKey('verification_required', $complianceMetadata);
        $this->assertArrayHasKey('verification_completed', $complianceMetadata);
        $this->assertArrayHasKey('verification_evidence', $complianceMetadata);
        $this->assertArrayHasKey('compliance_block_reason', $complianceMetadata);

        $this->assertArrayNotHasKey('unrelated_field', $complianceMetadata);
        $this->assertArrayNotHasKey('another_unrelated', $complianceMetadata);

        $this->assertEquals('1.0', $complianceMetadata['workflow_policy_version']);
        $this->assertEquals('non_trivial', $complianceMetadata['complexity_classification']);
    }

    public function test_get_compliance_metadata_returns_empty_array_when_no_metadata(): void
    {
        $task = InterrogationBuildTask::create([
            'interrogation_session_id' => $this->session->id,
            'sequence' => 1,
            'title' => 'Test Task',
            'status' => InterrogationBuildTask::STATUS_PENDING,
            'metadata_json' => null,
        ]);

        $complianceMetadata = $task->getComplianceMetadata();

        $this->assertIsArray($complianceMetadata);
        $this->assertEmpty($complianceMetadata);
    }

    public function test_set_compliance_metadata_merges_fields_into_metadata_json(): void
    {
        $task = InterrogationBuildTask::create([
            'interrogation_session_id' => $this->session->id,
            'sequence' => 1,
            'title' => 'Test Task',
            'status' => InterrogationBuildTask::STATUS_PENDING,
            'metadata_json' => [
                'existing_field' => 'preserved',
                'plan_completed' => false,
            ],
        ]);

        $task->setComplianceMetadata([
            'workflow_policy_version' => '2.0',
            'plan_completed' => true,
            'verification_required' => true,
        ]);

        $this->assertEquals('preserved', $task->metadata_json['existing_field']);
        $this->assertEquals('2.0', $task->metadata_json['workflow_policy_version']);
        $this->assertTrue($task->metadata_json['plan_completed']);
        $this->assertTrue($task->metadata_json['verification_required']);
    }

    public function test_set_compliance_metadata_works_with_null_metadata(): void
    {
        $task = InterrogationBuildTask::create([
            'interrogation_session_id' => $this->session->id,
            'sequence' => 1,
            'title' => 'Test Task',
            'status' => InterrogationBuildTask::STATUS_PENDING,
            'metadata_json' => null,
        ]);

        $task->setComplianceMetadata([
            'workflow_policy_version' => '1.0',
            'plan_required' => false,
        ]);

        $this->assertEquals('1.0', $task->metadata_json['workflow_policy_version']);
        $this->assertFalse($task->metadata_json['plan_required']);
    }

    public function test_is_compliance_blocked_returns_true_when_blocked_with_compliance_reason(): void
    {
        $task = InterrogationBuildTask::create([
            'interrogation_session_id' => $this->session->id,
            'sequence' => 1,
            'title' => 'Test Task',
            'status' => InterrogationBuildTask::STATUS_BLOCKED,
            'metadata_json' => [
                'compliance_block_reason' => 'Missing verification evidence',
            ],
        ]);

        $this->assertTrue($task->isComplianceBlocked());
    }

    public function test_is_compliance_blocked_returns_false_when_blocked_without_compliance_reason(): void
    {
        $task = InterrogationBuildTask::create([
            'interrogation_session_id' => $this->session->id,
            'sequence' => 1,
            'title' => 'Test Task',
            'status' => InterrogationBuildTask::STATUS_BLOCKED,
            'metadata_json' => [
                'some_other_field' => 'value',
            ],
        ]);

        $this->assertFalse($task->isComplianceBlocked());
    }

    public function test_is_compliance_blocked_returns_false_when_not_blocked_status(): void
    {
        $task = InterrogationBuildTask::create([
            'interrogation_session_id' => $this->session->id,
            'sequence' => 1,
            'title' => 'Test Task',
            'status' => InterrogationBuildTask::STATUS_COMPLETED,
            'metadata_json' => [
                'compliance_block_reason' => 'This should not matter',
            ],
        ]);

        $this->assertFalse($task->isComplianceBlocked());
    }

    public function test_is_compliance_blocked_returns_false_when_null_metadata(): void
    {
        $task = InterrogationBuildTask::create([
            'interrogation_session_id' => $this->session->id,
            'sequence' => 1,
            'title' => 'Test Task',
            'status' => InterrogationBuildTask::STATUS_BLOCKED,
            'metadata_json' => null,
        ]);

        $this->assertFalse($task->isComplianceBlocked());
    }

    public function test_all_task_categories_can_be_set(): void
    {
        $categories = [
            TaskCategory::FEATURE,
            TaskCategory::BUGFIX,
            TaskCategory::REFACTOR,
            TaskCategory::DOCS,
            TaskCategory::TEST,
            TaskCategory::CUSTOM,
        ];

        foreach ($categories as $index => $category) {
            $task = InterrogationBuildTask::create([
                'interrogation_session_id' => $this->session->id,
                'sequence' => $index + 100,
                'title' => "Test Task {$category->value}",
                'status' => InterrogationBuildTask::STATUS_PENDING,
                'task_category' => $category->value,
            ]);

            $task->refresh();

            $this->assertEquals($category, $task->task_category);
        }
    }
}
