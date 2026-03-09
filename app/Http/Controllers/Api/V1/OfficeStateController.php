<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Monitoring\GetAgentStatesAction;
use App\Actions\Monitoring\GetDelegationStateAction;
use App\Actions\Monitoring\GetEscalationsStateAction;
use App\Actions\Monitoring\GetJobsSummaryAction;
use App\Actions\Monitoring\GetMemoryStateAction;
use App\Actions\Monitoring\GetMessengerStateAction;
use App\Actions\Monitoring\GetRecentActivityAction;
use App\Actions\Monitoring\GetSystemStateAction;
use App\Actions\Monitoring\GetToolsStateAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OfficeStateController extends Controller
{
    public function __invoke(
        Request $request,
        GetAgentStatesAction $agentStates,
        GetSystemStateAction $systemState,
        GetDelegationStateAction $delegationState,
        GetMessengerStateAction $messengerState,
        GetMemoryStateAction $memoryState,
        GetJobsSummaryAction $jobsSummary,
        GetToolsStateAction $toolsState,
        GetEscalationsStateAction $escalationsState,
        GetRecentActivityAction $recentActivity,
    ): JsonResponse {
        $user = $request->user();

        return response()->json([
            'data' => [
                'agents' => $agentStates->execute($user),
                'system' => $systemState->execute(),
                'delegation' => $delegationState->execute($user),
                'messenger' => $messengerState->execute(),
                'memory' => $memoryState->execute($user),
                'jobs' => $jobsSummary->execute($user),
                'tools' => $toolsState->execute($user),
                'escalations' => $escalationsState->execute($user),
                'recent_activity' => $recentActivity->execute($user),
            ],
        ]);
    }
}
