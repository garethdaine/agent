<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Skills;

use App\Actions\Skills\GetSkillHealthAction;
use App\Actions\Skills\GetSkillUsageStatsAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SkillDashboardController extends Controller
{
    public function __construct(
        private readonly GetSkillUsageStatsAction $getSkillUsageStats,
        private readonly GetSkillHealthAction $getSkillHealth,
    ) {}

    public function usage(Request $request): JsonResponse
    {
        $team = $request->user()->currentTeam;

        if (! $team) {
            return response()->json(['data' => []]);
        }

        $data = $this->getSkillUsageStats->execute((int) $team->id);

        return response()->json(['data' => $data]);
    }

    public function health(Request $request): JsonResponse
    {
        $team = $request->user()->currentTeam;

        if (! $team) {
            return response()->json(['data' => []]);
        }

        $data = $this->getSkillHealth->execute((int) $team->id);

        return response()->json(['data' => $data]);
    }
}
