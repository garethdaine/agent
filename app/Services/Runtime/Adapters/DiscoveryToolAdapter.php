<?php

declare(strict_types=1);

namespace App\Services\Runtime\Adapters;

use App\DTOs\Runtime\RuntimeContext;
use App\DTOs\Runtime\ToolResult;
use App\Models\InterrogationSession;
use Illuminate\Support\Facades\Gate;

class DiscoveryToolAdapter extends AbstractToolAdapter
{
    public function name(): string
    {
        return 'discovery';
    }

    public function schema(): array
    {
        return [
            'operations' => ['status', 'start'],
            'parameters' => [
                'operation' => ['type' => 'string', 'required' => true],
                'session_id' => ['type' => 'integer', 'required_for' => ['status']],
                'project_directory' => ['type' => 'string', 'required_for' => ['start']],
                'feature_brief' => ['type' => 'string', 'required_for' => ['start']],
            ],
        ];
    }

    public function authorize(RuntimeContext $context, array $args): bool
    {
        return parent::authorize($context, $args);
    }

    protected function getRequiredCapability(array $args): string
    {
        return 'query';
    }

    public function execute(RuntimeContext $context, array $args): ToolResult
    {
        $startTime = hrtime(true);
        $operation = $args['operation'] ?? '';

        return match ($operation) {
            'status' => $this->status($context, $args, $startTime),
            'start' => $this->start($context, $args, $startTime),
            default => ToolResult::failure("Unknown operation: {$operation}", $this->duration($startTime)),
        };
    }

    private function status(RuntimeContext $context, array $args, int $startTime): ToolResult
    {
        $sessionId = isset($args['session_id']) ? (int) $args['session_id'] : null;
        if ($sessionId === null) {
            return ToolResult::failure('session_id is required for status', $this->duration($startTime));
        }

        $session = InterrogationSession::query()
            ->where('user_id', $context->user->id)
            ->find($sessionId);

        if ($session === null) {
            return ToolResult::failure("Discovery session {$sessionId} not found", $this->duration($startTime));
        }

        if (Gate::denies('view', $session)) {
            return ToolResult::failure('Not authorized to view this session', $this->duration($startTime));
        }

        return ToolResult::success([
            'session_id' => $session->id,
            'status' => $session->status,
            'phase' => $session->phase,
            'name' => $session->name,
            'interrogation_type' => $session->interrogation_type,
        ], $this->duration($startTime));
    }

    private function start(RuntimeContext $context, array $args, int $startTime): ToolResult
    {
        $projectDirectory = $args['project_directory'] ?? '';
        $featureBrief = $args['feature_brief'] ?? '';

        if ($projectDirectory === '' || $featureBrief === '') {
            return ToolResult::failure('project_directory and feature_brief are required for start', $this->duration($startTime));
        }

        if (Gate::denies('create', InterrogationSession::class)) {
            return ToolResult::failure('Not authorized to create discovery sessions', $this->duration($startTime));
        }

        $session = $context->user->interrogationSessions()->create([
            'name' => 'Runtime: '.substr($featureBrief, 0, 100),
            'runner_type' => 'claude',
            'project_directory' => $projectDirectory,
            'interrogation_type' => InterrogationSession::TYPE_FEATURE,
            'feature_brief' => $featureBrief,
            'status' => InterrogationSession::STATUS_SETUP,
            'phase' => InterrogationSession::PHASE_SETUP,
            'metadata_json' => ['source' => 'runtime_tool'],
        ]);

        return ToolResult::success([
            'session_id' => $session->id,
            'status' => $session->status,
            'phase' => $session->phase,
            'message' => 'Discovery session created. Complete setup in the UI or API to start discovery.',
        ], $this->duration($startTime));
    }

    private function duration(int $startTime): int
    {
        return (int) ((hrtime(true) - $startTime) / 1_000_000);
    }
}
