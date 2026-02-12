<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\StoreAgentJobRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentJobController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $jobs = $request->user()
            ->agentJobs()
            ->latest()
            ->get();

        return response()->json([
            'data' => $jobs,
        ]);
    }

    public function store(StoreAgentJobRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $job = $request->user()->agentJobs()->create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'cron_expression' => $validated['cron_expression'],
            'timezone' => $validated['timezone'],
            'is_enabled' => $validated['is_enabled'] ?? true,
            'max_runtime_seconds' => $validated['max_runtime_seconds'],
            'cooldown_seconds' => $validated['cooldown_seconds'],
            'runner_type' => $validated['runner_type'],
            'command_template' => $request->normalizedCommandTemplate(),
            'task_markdown_path' => $validated['task_markdown_path'],
            'working_directory' => $validated['working_directory'],
            'env_json' => $validated['env_json'] ?? null,
            'last_validated_executable_path' => $request->resolvedExecutablePath(),
        ]);

        return response()->json([
            'data' => $job,
        ], 201);
    }
}
