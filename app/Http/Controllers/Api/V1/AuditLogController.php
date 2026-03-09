<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Agent\ListAuditLogsAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request, ListAuditLogsAction $listLogs): JsonResponse
    {
        $paginator = $listLogs->execute([
            'action_prefix' => $request->input('action_prefix'),
            'target_type' => $request->input('target_type'),
            'actor_type' => $request->input('actor_type'),
            'outcome' => $request->input('outcome'),
            'per_page' => (int) $request->input('per_page', 50),
        ]);

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }
}
