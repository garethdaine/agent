<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Interrogation\GetInterrogationSettingAction;
use App\Actions\Interrogation\ListInterrogationSettingsAction;
use App\Actions\Interrogation\SaveInterrogationSettingAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Interrogation\UpdateSettingsRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InterrogationSettingsController extends Controller
{
    public function __construct(
        private readonly ListInterrogationSettingsAction $listSettings,
        private readonly GetInterrogationSettingAction $getSetting,
        private readonly SaveInterrogationSettingAction $saveSetting,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $settings = $this->listSettings->execute($request->user()->id);

        return response()->json([
            'data' => $settings,
        ]);
    }

    public function show(Request $request, string $key): JsonResponse
    {
        $setting = $this->getSetting->execute($request->user()->id, $key);

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
        $setting = $this->saveSetting->execute((int) $request->user()->id, $key, $request->validated()['value']);

        return response()->json([
            'data' => [
                'key' => $setting->key,
                'value' => $setting->value,
                'updated_at' => $setting->updated_at?->toIso8601String(),
            ],
        ]);
    }
}
