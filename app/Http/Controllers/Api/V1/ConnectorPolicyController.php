<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ConnectorAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConnectorPolicyController extends Controller
{
    public function show(string $id): JsonResponse
    {
        $connector = ConnectorAccount::findOrFail($id);

        return $this->policyResponse($connector);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $connector = ConnectorAccount::findOrFail($id);

        $validated = $request->validate([
            'dm_policy' => ['sometimes', 'string', 'in:pairing,allowlist,open,disabled'],
            'dm_session_scope' => ['sometimes', 'string', 'in:main,per_peer'],
            'group_require_mention' => ['sometimes', 'boolean'],
            'group_allowed_groups' => ['sometimes', 'array'],
            'group_allowed_groups.*' => ['string', 'max:255'],
            'streaming' => ['sometimes', 'array'],
            'streaming.max_message_chars' => ['sometimes', 'integer', 'min:500', 'max:10000'],
            'streaming.throttle_ms' => ['sometimes', 'numeric', 'min:200', 'max:5000'],
            'streaming.min_initial_chars' => ['sometimes', 'integer', 'min:10', 'max:1000'],
        ]);

        if (isset($validated['dm_policy'])) {
            $connector->setDmPolicy($validated['dm_policy']);
        }

        if (isset($validated['dm_session_scope'])) {
            $connector->setDmSessionScope($validated['dm_session_scope']);
        }

        if (array_key_exists('group_require_mention', $validated) || array_key_exists('group_allowed_groups', $validated)) {
            $current = $connector->getGroupPolicy();
            $connector->setGroupPolicy(
                $validated['group_require_mention'] ?? $current['require_mention'],
                $validated['group_allowed_groups'] ?? $current['allowed_groups'],
            );
        }

        if (isset($validated['streaming'])) {
            $connector->setStreamingOverrides($validated['streaming']);
        }

        $connector->refresh();

        return $this->policyResponse($connector);
    }

    private function policyResponse(ConnectorAccount $connector): JsonResponse
    {
        return response()->json([
            'data' => [
                'id' => $connector->id,
                'name' => $connector->name,
                'provider' => $connector->provider,
                'dm_policy' => $connector->getDmPolicy(),
                'dm_session_scope' => $connector->getDmSessionScope(),
                'group_policy' => $connector->getGroupPolicy(),
                'streaming' => $connector->getStreamingOverrides(),
            ],
        ]);
    }
}
