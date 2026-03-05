<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MessengerIdentityLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PairingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = MessengerIdentityLink::query()
            ->with('connectorAccount:id,name,provider')
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->statusFilter($request->input('status'));
        }

        if ($request->filled('connector_account_id')) {
            $query->forConnector($request->input('connector_account_id'));
        }

        $perPage = min((int) $request->input('per_page', 50), 200);

        return response()->json([
            'data' => $query->paginate($perPage)->through(fn (MessengerIdentityLink $link) => [
                'id' => $link->id,
                'user_id' => $link->user_id,
                'connector_account_id' => $link->connector_account_id,
                'connector_name' => $link->connectorAccount?->name,
                'connector_provider' => $link->connectorAccount?->provider,
                'provider_user_id' => $link->provider_user_id,
                'provider_username' => $link->provider_username,
                'status' => $link->status,
                'expires_at' => $link->expires_at?->toIso8601String(),
                'created_at' => $link->created_at?->toIso8601String(),
            ])->items(),
            'meta' => [
                'total' => $query->count(),
            ],
        ]);
    }

    public function approve(string $id): JsonResponse
    {
        $link = MessengerIdentityLink::findOrFail($id);

        if (! $link->isPending()) {
            return response()->json([
                'error' => "Cannot approve: link is {$link->status}.",
            ], 422);
        }

        $link->approve();

        return response()->json(['data' => ['id' => $link->id, 'status' => $link->status]]);
    }

    public function revoke(string $id): JsonResponse
    {
        $link = MessengerIdentityLink::findOrFail($id);

        if ($link->isRevoked()) {
            return response()->json([
                'error' => 'Already revoked.',
            ], 422);
        }

        $link->revoke();

        return response()->json(['data' => ['id' => $link->id, 'status' => $link->status]]);
    }
}
