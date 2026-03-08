<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Credentials\CredentialsManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CredentialsController extends Controller
{
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
}
