<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Agent\UpdateBackupSettingAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\UpdateBackupSettingsRequest;
use App\Models\AgentBackupSetting;
use App\Support\Agent\AuditLogger;
use App\Support\Agent\ErrorEnvelope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class AgentBackupSettingsController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json([
            'data' => $this->transform(AgentBackupSetting::current()),
        ]);
    }

    public function update(
        UpdateBackupSettingsRequest $request,
        AuditLogger $auditLogger,
        UpdateBackupSettingAction $updateSetting
    ): JsonResponse {
        $setting = AgentBackupSetting::current();

        $before = $setting->only([
            'is_enabled',
            'timezone',
            'run_hour',
            'run_minute',
            'retention_days',
        ]);

        $updateSetting->execute($setting, $request->validated(), $request->user()?->id);

        $auditLogger->recordUserAction(
            request: $request,
            action: 'backup.settings.update',
            targetType: 'backup_settings',
            targetId: (int) $setting->id,
            ownerUserId: null,
            changedFields: ['is_enabled', 'timezone', 'run_hour', 'run_minute', 'retention_days'],
            before: $before,
            after: $setting->only(['is_enabled', 'timezone', 'run_hour', 'run_minute', 'retention_days']),
        );

        return response()->json([
            'data' => $this->transform($setting),
        ]);
    }

    public function runNow(Request $request, AuditLogger $auditLogger): JsonResponse
    {
        $exitCode = Artisan::call('agent:backup-database', ['--force-run' => true]);
        $output = trim(Artisan::output());
        $setting = AgentBackupSetting::current()->fresh();

        $auditLogger->recordUserAction(
            request: $request,
            action: 'backup.run_now',
            targetType: 'backup_settings',
            targetId: (int) $setting->id,
            ownerUserId: null,
            changedFields: ['last_run_at', 'last_status', 'last_error'],
            before: null,
            after: [
                'exit_code' => $exitCode,
                'last_run_at' => $setting->last_run_at?->toIso8601String(),
                'last_status' => $setting->last_status,
                'last_error' => $setting->last_error,
            ],
            outcome: $exitCode === 0 ? 'success' : 'failure',
            errorCode: $exitCode === 0 ? null : 'BACKUP_RUN_FAILED',
            errorMessage: $exitCode === 0 ? null : ($setting->last_error ?: ($output !== '' ? $output : 'Backup command failed.')),
        );

        if ($exitCode !== 0) {
            return ErrorEnvelope::make('BACKUP_RUN_FAILED', $setting->last_error ?: ($output !== '' ? $output : 'Backup command failed.'), 500);
        }

        return response()->json([
            'data' => [
                'exit_code' => $exitCode,
                'output' => $output,
                'settings' => $this->transform($setting),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(AgentBackupSetting $setting): array
    {
        return [
            'id' => $setting->id,
            'is_enabled' => (bool) $setting->is_enabled,
            'timezone' => (string) $setting->timezone,
            'run_hour' => (int) $setting->run_hour,
            'run_minute' => (int) $setting->run_minute,
            'retention_days' => (int) $setting->retention_days,
            'last_run_at' => $setting->last_run_at?->toIso8601String(),
            'last_status' => $setting->last_status,
            'last_error' => $setting->last_error,
            'updated_at' => $setting->updated_at?->toIso8601String(),
        ];
    }
}
