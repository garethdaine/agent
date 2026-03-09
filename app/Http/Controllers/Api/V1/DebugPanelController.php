<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Monitoring\GetDebugStatsAction;
use App\Http\Controllers\Controller;
use App\Support\Agent\DiagnosticsService;
use Illuminate\Http\JsonResponse;

class DebugPanelController extends Controller
{
    public function index(DiagnosticsService $diagnostics, GetDebugStatsAction $debugStats): JsonResponse
    {
        return response()->json([
            'data' => $debugStats->execute($diagnostics),
        ]);
    }
}
