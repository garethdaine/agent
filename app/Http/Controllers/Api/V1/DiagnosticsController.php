<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\Agent\DiagnosticsService;
use Illuminate\Http\JsonResponse;

class DiagnosticsController extends Controller
{
    public function index(DiagnosticsService $diagnostics): JsonResponse
    {
        $checks = $diagnostics->run();

        $ok = count(array_filter($checks, fn (array $c) => $c['status'] === DiagnosticsService::STATUS_OK));
        $warn = count(array_filter($checks, fn (array $c) => $c['status'] === DiagnosticsService::STATUS_WARN));
        $error = count(array_filter($checks, fn (array $c) => $c['status'] === DiagnosticsService::STATUS_ERROR));

        return response()->json([
            'data' => [
                'checks' => $checks,
                'summary' => [
                    'total' => count($checks),
                    'ok' => $ok,
                    'warn' => $warn,
                    'error' => $error,
                    'healthy' => $error === 0,
                ],
            ],
        ]);
    }
}
