<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\Agent\ServiceManagerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceManagerController extends Controller
{
    public function index(ServiceManagerService $manager): JsonResponse
    {
        return response()->json([
            'data' => array_values($manager->status()),
        ]);
    }

    public function restart(Request $request, ServiceManagerService $manager): JsonResponse
    {
        $request->validate([
            'service' => ['required', 'string', 'max:50'],
        ]);

        if (! $request->user()?->hasRole('admin')) {
            abort(403, 'Only administrators can restart services.');
        }

        $result = $manager->restart($request->input('service'));

        return response()->json([
            'data' => $result,
        ], $result['success'] ? 200 : 422);
    }
}
