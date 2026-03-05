<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\Agent\SecurityAuditService;
use Illuminate\Http\JsonResponse;

class SecurityAuditController extends Controller
{
    public function index(SecurityAuditService $audit): JsonResponse
    {
        $findings = $audit->run();

        $critical = count(array_filter($findings, fn (array $f) => $f['severity'] === SecurityAuditService::SEVERITY_CRITICAL));
        $warn = count(array_filter($findings, fn (array $f) => $f['severity'] === SecurityAuditService::SEVERITY_WARN));
        $info = count(array_filter($findings, fn (array $f) => $f['severity'] === SecurityAuditService::SEVERITY_INFO));

        return response()->json([
            'data' => [
                'findings' => $findings,
                'summary' => [
                    'total' => count($findings),
                    'critical' => $critical,
                    'warn' => $warn,
                    'info' => $info,
                    'ok' => $critical === 0,
                ],
            ],
        ]);
    }
}
