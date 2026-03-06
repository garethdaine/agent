<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Tunnel\TunnelHealthMonitor;
use Illuminate\Http\JsonResponse;

class TunnelStatusController extends Controller
{
    public function __invoke(TunnelHealthMonitor $monitor): JsonResponse
    {
        return response()->json($monitor->getHealth());
    }
}
