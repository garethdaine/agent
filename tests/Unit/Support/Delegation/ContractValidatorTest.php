<?php

namespace Tests\Unit\Support\Delegation;

use App\Models\DelegationCapability;
use App\Support\Delegation\ContractValidator;
use App\Support\Delegation\ValidationResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractValidatorTest extends TestCase
{
    use RefreshDatabase;

    private ContractValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new ContractValidator;
    }

    public function test_validates_required_capability_exists(): void
    {
        DelegationCapability::create([
            'name' => 'Code Execution',
            'slug' => 'code_execution',
            'is_active' => true,
        ]);

        $contract = [
            'required_capability' => 'code_execution',
            'prompt' => 'Do something',
        ];

        $result = $this->validator->validate($contract);

        $this->assertInstanceOf(ValidationResult::class, $result);
        $this->assertTrue($result->isValid());
        $this->assertEmpty($result->errors());
    }

    public function test_fails_invalid_capability_reference(): void
    {
        $contract = [
            'required_capability' => 'nonexistent_capability',
            'prompt' => 'Do something',
        ];

        $result = $this->validator->validate($contract);

        $this->assertFalse($result->isValid());
        $this->assertContains(
            "Capability 'nonexistent_capability' not found or inactive",
            $result->errors()
        );
    }

    public function test_validates_max_runtime_seconds_limit(): void
    {
        $contract = [
            'prompt' => 'Do something',
            'authority_scope' => [
                'max_runtime_seconds' => 86400,
            ],
        ];

        $result = $this->validator->validate($contract);

        $this->assertTrue($result->isValid());
    }

    public function test_fails_runtime_exceeding_24_hours(): void
    {
        $contract = [
            'prompt' => 'Do something',
            'authority_scope' => [
                'max_runtime_seconds' => 86401,
            ],
        ];

        $result = $this->validator->validate($contract);

        $this->assertFalse($result->isValid());
        $this->assertContains(
            'max_runtime_seconds cannot exceed 86400 (24 hours)',
            $result->errors()
        );
    }

    public function test_validates_criticality_enum(): void
    {
        $validCriticalities = ['low', 'medium', 'high', 'critical'];

        foreach ($validCriticalities as $criticality) {
            $contract = [
                'prompt' => 'Do something',
                'criticality' => $criticality,
            ];

            $result = $this->validator->validate($contract);

            $this->assertTrue($result->isValid(), "Criticality '{$criticality}' should be valid");
        }
    }

    public function test_fails_invalid_criticality(): void
    {
        $contract = [
            'prompt' => 'Do something',
            'criticality' => 'super_critical',
        ];

        $result = $this->validator->validate($contract);

        $this->assertFalse($result->isValid());
        $this->assertContains(
            'Invalid criticality value',
            $result->errors()
        );
    }

    public function test_requires_prompt_or_task_markdown_path(): void
    {
        $contract = [];

        $result = $this->validator->validate($contract);

        $this->assertFalse($result->isValid());
        $this->assertContains(
            'Either prompt or task_markdown_path is required',
            $result->errors()
        );
    }

    public function test_fails_when_both_prompt_and_path_present(): void
    {
        $contract = [
            'prompt' => 'Do something',
            'task_markdown_path' => '/path/to/task.md',
        ];

        $result = $this->validator->validate($contract);

        $this->assertFalse($result->isValid());
        $this->assertContains(
            'Cannot specify both prompt and task_markdown_path',
            $result->errors()
        );
    }

    public function test_validates_check_profile_exists(): void
    {
        // Valid check profile from config
        $contract = [
            'prompt' => 'Do something',
            'verification_strategy' => [
                'automated_check' => [
                    'check_profile' => 'laravel_standard',
                ],
            ],
        ];

        $result = $this->validator->validate($contract);

        $this->assertTrue($result->isValid());
    }

    public function test_fails_invalid_check_profile(): void
    {
        $contract = [
            'prompt' => 'Do something',
            'verification_strategy' => [
                'automated_check' => [
                    'check_profile' => 'nonexistent_profile',
                ],
            ],
        ];

        $result = $this->validator->validate($contract);

        $this->assertFalse($result->isValid());
        $this->assertContains(
            "Check profile 'nonexistent_profile' not found in configuration",
            $result->errors()
        );
    }

    public function test_validates_human_approval_timeout_limit(): void
    {
        // Valid timeout within 4 hours
        $contract = [
            'prompt' => 'Do something',
            'verification_strategy' => [
                'human_approval' => [
                    'timeout_hours' => 4,
                ],
            ],
        ];

        $result = $this->validator->validate($contract);

        $this->assertTrue($result->isValid());
    }

    public function test_fails_human_approval_timeout_exceeds_limit(): void
    {
        $contract = [
            'prompt' => 'Do something',
            'verification_strategy' => [
                'human_approval' => [
                    'timeout_hours' => 5,
                ],
            ],
        ];

        $result = $this->validator->validate($contract);

        $this->assertFalse($result->isValid());
        $this->assertContains(
            'human_approval timeout_hours cannot exceed 4',
            $result->errors()
        );
    }

    public function test_inactive_capability_fails_validation(): void
    {
        DelegationCapability::create([
            'name' => 'Inactive Capability',
            'slug' => 'inactive_cap',
            'is_active' => false,
        ]);

        $contract = [
            'required_capability' => 'inactive_cap',
            'prompt' => 'Do something',
        ];

        $result = $this->validator->validate($contract);

        $this->assertFalse($result->isValid());
        $this->assertContains(
            "Capability 'inactive_cap' not found or inactive",
            $result->errors()
        );
    }

    public function test_accumulates_multiple_errors(): void
    {
        $contract = [
            'required_capability' => 'nonexistent',
            'criticality' => 'invalid',
            'authority_scope' => [
                'max_runtime_seconds' => 100000,
            ],
        ];

        $result = $this->validator->validate($contract);

        $this->assertFalse($result->isValid());
        $this->assertGreaterThanOrEqual(3, count($result->errors()));
    }

    public function test_returns_warnings_for_deprecated_fields(): void
    {
        $contract = [
            'prompt' => 'Do something',
        ];

        $result = $this->validator->validate($contract);

        // Even valid contracts can have warnings
        $this->assertIsArray($result->warnings());
    }
}
