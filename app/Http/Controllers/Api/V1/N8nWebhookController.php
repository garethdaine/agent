<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class N8nWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        if (! config('n8n.enabled', false)) {
            return response()->json([
                'ok' => false,
                'message' => 'n8n integration is not enabled.',
            ], 501);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Webhook received. n8n automation handling not yet implemented.',
        ]);
    }
}
