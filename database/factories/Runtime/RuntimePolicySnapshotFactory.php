<?php

namespace Database\Factories\Runtime;

use App\Enums\Runtime\PolicySnapshotReason;
use App\Models\Runtime\RuntimePolicySnapshot;
use App\Models\Runtime\RuntimeSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RuntimePolicySnapshot>
 */
class RuntimePolicySnapshotFactory extends Factory
{
    protected $model = RuntimePolicySnapshot::class;

    public function definition(): array
    {
        return [
            'runtime_session_id' => RuntimeSession::factory(),
            'snapshot_reason' => PolicySnapshotReason::SessionStart,
            'policy_json' => [
                'mode' => 'safe',
                'capabilities' => ['read', 'query', 'browser_snapshot'],
                'approvals_required' => [],
                'workspace_root' => '/tmp/runtime-workspace',
            ],
            'captured_at' => now(),
        ];
    }

    public function modeChange(): static
    {
        return $this->state(fn (array $attributes) => [
            'snapshot_reason' => PolicySnapshotReason::ModeChange,
        ]);
    }

    public function standardModePolicy(): static
    {
        return $this->state(fn (array $attributes) => [
            'policy_json' => [
                'mode' => 'standard',
                'capabilities' => ['read', 'write', 'query', 'browser_action', 'runtime_command'],
                'approvals_required' => ['mutations'],
                'workspace_root' => '/tmp/runtime-workspace',
            ],
        ]);
    }

    public function fullModePolicy(): static
    {
        return $this->state(fn (array $attributes) => [
            'policy_json' => [
                'mode' => 'full',
                'capabilities' => ['*'],
                'approvals_required' => ['mutations', 'external', 'elevated'],
                'workspace_root' => '/tmp/runtime-workspace',
            ],
        ]);
    }
}
