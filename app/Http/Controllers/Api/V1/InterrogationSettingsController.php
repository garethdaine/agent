<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Interrogation\UpdateSettingsRequest;
use App\Models\InterrogationSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InterrogationSettingsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $settings = InterrogationSetting::query()
            ->where('user_id', $request->user()->id)
            ->orderBy('key')
            ->get(['key', 'value', 'updated_at']);

        return response()->json([
            'data' => $settings,
        ]);
    }

    public function show(Request $request, string $key): JsonResponse
    {
        $setting = InterrogationSetting::query()
            ->where('user_id', $request->user()->id)
            ->where('key', $key)
            ->first();

        return response()->json([
            'data' => [
                'key' => $key,
                'value' => $setting?->value,
                'updated_at' => $setting?->updated_at?->toIso8601String(),
            ],
        ]);
    }

    public function update(UpdateSettingsRequest $request, string $key): JsonResponse
    {
        $setting = InterrogationSetting::setForUser((int) $request->user()->id, $key, $request->validated()['value']);

        return response()->json([
            'data' => [
                'key' => $setting->key,
                'value' => $setting->value,
                'updated_at' => $setting->updated_at?->toIso8601String(),
            ],
        ]);
    }
}
