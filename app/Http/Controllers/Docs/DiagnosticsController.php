<?php

declare(strict_types=1);

namespace App\Http\Controllers\Docs;

use App\Http\Controllers\Controller;
use App\Support\Documentation\DocsTelemetryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DiagnosticsController extends Controller
{
    public function __construct(
        private readonly DocsTelemetryService $telemetry,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $limit = max(1, min((int) $request->query('limit', 20), 100));

        return response()->json([
            'data' => $this->telemetry->snapshot($limit),
        ]);
    }
}
