<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Runtime;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class RuntimePolicyController extends Controller
{
    public function index(): JsonResponse
    {
        $modes = config('runtime.modes', []);
        $defaultMode = config('runtime.default_mode', 'safe');
        $toolDeny = config('runtime.tool_deny', []);
        $toolAllow = config('runtime.tool_allow', []);

        return response()->json([
            'data' => [
                'default_mode' => $defaultMode,
                'modes' => array_keys($modes),
                'tool_deny' => array_values($toolDeny),
                'tool_allow' => array_values($toolAllow),
                'tool_allowlist_active' => $toolAllow !== [],
            ],
        ]);
    }
}
