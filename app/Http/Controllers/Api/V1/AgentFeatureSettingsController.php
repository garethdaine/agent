<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\UpdateFeatureSettingsRequest;
use App\Support\Agent\AuditLogger;
use App\Support\Agent\ErrorEnvelope;
use App\Support\Agent\FeatureFlagManager;
use Illuminate\Http\JsonResponse;

class AgentFeatureSettingsController extends Controller
{
    public function index(FeatureFlagManager $featureFlags): JsonResponse
    {
        return response()->json([
            'data' => $featureFlags->all(),
        ]);
    }

    public function update(
        UpdateFeatureSettingsRequest $request,
        FeatureFlagManager $featureFlags,
        AuditLogger $auditLogger,
    ): JsonResponse {
        $flags = $request->validated()['flags'];
        $changedKeys = array_keys($flags);
        $before = $featureFlags->valuesFor($changedKeys);

        try {
            $data = $featureFlags->updateMany($flags, $request->user()?->id);
        } catch (\RuntimeException $exception) {
            return ErrorEnvelope::make(
                'FEATURE_SETTINGS_STORE_UNAVAILABLE',
                $exception->getMessage(),
                500,
            );
        }

        $after = $featureFlags->valuesFor($changedKeys);

        $auditLogger->recordUserAction(
            request: $request,
            action: 'feature.settings.update',
            targetType: 'feature_settings',
            targetId: 'global',
            ownerUserId: null,
            changedFields: $changedKeys,
            before: $before,
            after: $after,
        );

        return response()->json([
            'data' => $data,
        ]);
    }
}
