<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Pairing\FindMessengerIdentityLinkAction;
use App\Actions\Pairing\ListMessengerIdentityLinksAction;
use App\Http\Controllers\Controller;
use App\Models\MessengerIdentityLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PairingController extends Controller
{
    public function __construct(
        private readonly ListMessengerIdentityLinksAction $listLinks,
        private readonly FindMessengerIdentityLinkAction $findLink,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->listLinks->execute([
            'status' => $request->input('status'),
            'connector_account_id' => $request->input('connector_account_id'),
            'per_page' => (int) $request->input('per_page', 50),
        ]);

        return response()->json([
            'data' => $paginator->through(fn (MessengerIdentityLink $link) => [
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
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function approve(string $id): JsonResponse
    {
        $link = $this->findLink->execute($id);

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
        $link = $this->findLink->execute($id);

        if ($link->isRevoked()) {
            return response()->json([
                'error' => 'Already revoked.',
            ], 422);
        }

        $link->revoke();

        return response()->json(['data' => ['id' => $link->id, 'status' => $link->status]]);
    }
}
