<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Credentials\StoreCredentialRequest;
use App\Http\Requests\Credentials\UpdateCredentialRequest;
use App\Models\CredentialVault;
use App\Services\Credentials\CredentialsManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CredentialsController extends Controller
{
    // ─── Legacy provider-based endpoints ───

    public function index(Request $request, CredentialsManager $credentialsManager): JsonResponse
    {
        $user = $request->user();
        $providers = config('credentials.providers', []);
        $out = [];

        foreach ($providers as $providerKey => $config) {
            $keyNames = $config['keys'] ?? [];
            $storedKeys = $credentialsManager->getProviderKeys($user, $providerKey);
            $keys = [];
            foreach ($keyNames as $keyName) {
                $keys[] = [
                    'key' => $keyName,
                    'has_value' => in_array($keyName, $storedKeys, true),
                ];
            }
            $out[] = [
                'key' => $providerKey,
                'label' => $config['label'] ?? $providerKey,
                'keys' => $keys,
            ];
        }

        return response()->json(['data' => ['providers' => $out]]);
    }

    public function store(Request $request, CredentialsManager $credentialsManager): JsonResponse
    {
        $validated = $request->validate([
            'provider' => ['required', 'string', Rule::in(array_keys(config('credentials.providers', [])))],
            'key' => ['required', 'string'],
            'value' => ['required', 'string', 'max:8192'],
        ]);

        $providerConfig = config('credentials.providers.'.$validated['provider'], []);
        $allowedKeys = $providerConfig['keys'] ?? [];
        if (! in_array($validated['key'], $allowedKeys, true)) {
            return response()->json([
                'error' => ['message' => 'Invalid key for this provider.', 'details' => ['key' => ['Key not allowed.']]],
            ], 422);
        }

        $credentialsManager->store($request->user(), $validated['provider'], $validated['key'], $validated['value']);

        return response()->json(['data' => ['stored' => true]]);
    }

    public function destroy(Request $request, CredentialsManager $credentialsManager): JsonResponse
    {
        $validated = $request->validate([
            'provider' => ['required', 'string', Rule::in(array_keys(config('credentials.providers', [])))],
            'key' => ['required', 'string'],
        ]);

        $deleted = $credentialsManager->delete($request->user(), $validated['provider'], $validated['key']);

        return response()->json(['data' => ['deleted' => $deleted]]);
    }

    // ─── Vault endpoints (password manager) ───

    public function vaultIndex(Request $request, CredentialsManager $credentialsManager): JsonResponse
    {
        $type = $request->query('type');
        $search = $request->query('search');

        $credentials = $credentialsManager->listCredentials(
            $request->user(),
            is_string($type) ? $type : null,
            is_string($search) ? $search : null,
        );

        $grouped = $credentials->groupBy('credential_type')->map(function ($items, $type) {
            return [
                'type' => $type,
                'label' => config("credentials.types.{$type}.label", $type),
                'credentials' => $items->map(fn (CredentialVault $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'provider' => $c->provider,
                    'url' => $c->url,
                    'approval_mode' => $c->approval_mode,
                    'has_secrets' => $c->encrypted_value !== null && $c->encrypted_value !== '',
                    'metadata' => $c->metadata,
                    'last_used_at' => $c->last_used_at?->toIso8601String(),
                    'last_rotated_at' => $c->last_rotated_at?->toIso8601String(),
                    'expires_at' => $c->expires_at?->toIso8601String(),
                    'is_expired' => $c->isExpired(),
                    'created_at' => $c->created_at?->toIso8601String(),
                ])->values(),
            ];
        })->values();

        return response()->json(['data' => ['vault' => $grouped]]);
    }

    public function vaultStore(StoreCredentialRequest $request, CredentialsManager $credentialsManager): JsonResponse
    {
        $credential = $credentialsManager->storeCredential(
            $request->user(),
            $request->validated('credential_type'),
            $request->validated(),
        );

        return response()->json(['data' => [
            'id' => $credential->id,
            'stored' => true,
        ]], 201);
    }

    public function vaultShow(Request $request, string $id): JsonResponse
    {
        $credential = CredentialVault::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        return response()->json(['data' => [
            'id' => $credential->id,
            'name' => $credential->name,
            'credential_type' => $credential->credential_type,
            'provider' => $credential->provider,
            'key' => $credential->key,
            'url' => $credential->url,
            'approval_mode' => $credential->approval_mode,
            'metadata' => $credential->metadata,
            'has_secrets' => $credential->encrypted_value !== null && $credential->encrypted_value !== '',
            'has_notes' => $credential->notes !== null && $credential->notes !== '',
            'last_used_at' => $credential->last_used_at?->toIso8601String(),
            'last_rotated_at' => $credential->last_rotated_at?->toIso8601String(),
            'expires_at' => $credential->expires_at?->toIso8601String(),
            'is_expired' => $credential->isExpired(),
            'created_at' => $credential->created_at?->toIso8601String(),
            'updated_at' => $credential->updated_at?->toIso8601String(),
        ]]);
    }

    public function vaultUpdate(UpdateCredentialRequest $request, string $id, CredentialsManager $credentialsManager): JsonResponse
    {
        $credential = CredentialVault::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        $credentialsManager->updateCredential($credential, $request->validated());

        return response()->json(['data' => ['updated' => true]]);
    }

    public function vaultDestroy(Request $request, string $id, CredentialsManager $credentialsManager): JsonResponse
    {
        $credential = CredentialVault::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        $deleted = $credentialsManager->deleteCredential($credential);

        return response()->json(['data' => ['deleted' => $deleted]]);
    }

    public function vaultRotate(Request $request, string $id, CredentialsManager $credentialsManager): JsonResponse
    {
        $validated = $request->validate([
            'secrets' => ['required', 'array'],
            'secrets.*' => ['nullable', 'string', 'max:65535'],
        ]);

        $credential = CredentialVault::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        $credentialsManager->rotateCredential($credential, $validated['secrets']);

        return response()->json(['data' => ['rotated' => true]]);
    }
}
