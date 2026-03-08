<?php

declare(strict_types=1);

namespace App\Support\Agent;

use Illuminate\Http\JsonResponse;

class ErrorEnvelope
{
    /**
     * @param  array<string, mixed>  $details
     */
    public static function make(string $code, string $message, int $status, array $details = []): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => (object) $details,
            ],
        ], $status);
    }
}
