<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Interrogation\UpdateInterrogationTaskProviderSettingsRequest;
use App\Models\ConnectedProvider;
use App\Models\InterrogationSession;
use App\Support\TaskProviders\ProviderOAuthStateStore;
use App\Support\TaskProviders\TaskManagementProviderManager;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class InterrogationTaskProviderController extends Controller
{
    public function startOAuth(
        Request $request,
        int $id,
        string $driver,
        TaskManagementProviderManager $providerManager,
        ProviderOAuthStateStore $oauthStateStore,
    ): JsonResponse {
        $session = $request->user()->interrogationSessions()->findOrFail($id);

        try {
            $providerDriver = $providerManager->driver($driver);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'error' => [
                    'code' => 'TASK_PROVIDER_UNSUPPORTED',
                    'message' => $exception->getMessage(),
                ],
            ], 422);
        }

        $returnTo = strtolower(trim((string) $request->input('return_to', 'wizard')));
        if (! in_array($returnTo, ['wizard', 'settings'], true)) {
            $returnTo = 'wizard';
        }

        $state = $oauthStateStore->put(
            (int) $request->user()->id,
            (int) $session->id,
            $providerDriver->key(),
            $returnTo,
        );

        $redirectUri = $this->oauthRedirectUri($providerDriver->key());

        return response()->json([
            'data' => [
                'driver' => $providerDriver->key(),
                'authorization_url' => $providerDriver->authorizationUrl($state, $redirectUri),
            ],
        ]);
    }

    public function disconnect(Request $request, int $id, string $driver): JsonResponse
    {
        $session = $request->user()->interrogationSessions()->findOrFail($id);

        $deleted = $session->providerIntegrations()
            ->where('category', 'task_management')
            ->where('driver', strtolower(trim($driver)))
            ->delete();

        return response()->json([
            'data' => [
                'disconnected' => $deleted > 0,
                'driver' => strtolower(trim($driver)),
            ],
        ]);
    }

    public function projects(
        Request $request,
        int $id,
        string $driver,
        TaskManagementProviderManager $providerManager,
    ): JsonResponse {
        $session = $request->user()->interrogationSessions()->findOrFail($id);
        $normalizedDriver = strtolower(trim($driver));
        $provider = $this->connectedProviderForSession($session, $normalizedDriver);

        if (! $provider instanceof ConnectedProvider) {
            return response()->json([
                'error' => [
                    'code' => 'TASK_PROVIDER_NOT_CONNECTED',
                    'message' => sprintf('No %s provider is connected for this session.', $normalizedDriver),
                ],
            ], 409);
        }

        try {
            $providerDriver = $providerManager->driver($normalizedDriver);
            $projects = $providerDriver->listProjects($provider);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'error' => [
                    'code' => 'TASK_PROVIDER_UNSUPPORTED',
                    'message' => $exception->getMessage(),
                ],
            ], 422);
        } catch (\Throwable $throwable) {
            report($throwable);

            return response()->json([
                'error' => [
                    'code' => 'TASK_PROVIDER_PROJECTS_FETCH_FAILED',
                    'message' => $throwable->getMessage(),
                ],
            ], 422);
        }

        $projectSync = is_array(data_get($provider->metadata_json, 'project_sync', []))
            ? data_get($provider->metadata_json, 'project_sync', [])
            : [];
        $mode = in_array(($projectSync['mode'] ?? null), ['create_new', 'existing'], true)
            ? (string) $projectSync['mode']
            : 'create_new';

        return response()->json([
            'data' => [
                'driver' => $normalizedDriver,
                'project_mode' => $mode,
                'selected_project_id' => $mode === 'existing'
                    ? (string) ($projectSync['selected_project_id'] ?? '')
                    : null,
                'selected_project_name' => $mode === 'existing'
                    ? ($projectSync['selected_project_name'] ?? null)
                    : null,
                'selected_project_url' => $mode === 'existing'
                    ? ($projectSync['selected_project_url'] ?? null)
                    : null,
                'projects' => $projects,
            ],
        ]);
    }

    public function updateSettings(
        UpdateInterrogationTaskProviderSettingsRequest $request,
        int $id,
        string $driver,
        TaskManagementProviderManager $providerManager,
    ): JsonResponse {
        $session = $request->user()->interrogationSessions()->findOrFail($id);
        $normalizedDriver = strtolower(trim($driver));
        $provider = $this->connectedProviderForSession($session, $normalizedDriver);

        if (! $provider instanceof ConnectedProvider) {
            return response()->json([
                'error' => [
                    'code' => 'TASK_PROVIDER_NOT_CONNECTED',
                    'message' => sprintf('No %s provider is connected for this session.', $normalizedDriver),
                ],
            ], 409);
        }

        $validated = $request->validated();
        $projectMode = (string) $validated['project_mode'];
        $selectedProjectId = null;
        $selectedProjectName = null;
        $selectedProjectUrl = null;

        if ($projectMode === 'existing') {
            try {
                $providerDriver = $providerManager->driver($normalizedDriver);
                $projects = $providerDriver->listProjects($provider);
            } catch (InvalidArgumentException $exception) {
                return response()->json([
                    'error' => [
                        'code' => 'TASK_PROVIDER_UNSUPPORTED',
                        'message' => $exception->getMessage(),
                    ],
                ], 422);
            } catch (\Throwable $throwable) {
                report($throwable);

                return response()->json([
                    'error' => [
                        'code' => 'TASK_PROVIDER_PROJECTS_FETCH_FAILED',
                        'message' => $throwable->getMessage(),
                    ],
                ], 422);
            }

            $targetProjectId = trim((string) ($validated['existing_project_id'] ?? ''));
            $selected = collect($projects)->first(
                static fn (array $project): bool => trim((string) ($project['id'] ?? '')) === $targetProjectId
            );

            if (! is_array($selected)) {
                return response()->json([
                    'error' => [
                        'code' => 'TASK_PROVIDER_PROJECT_INVALID',
                        'message' => 'Selected Linear project is not available to this connection.',
                    ],
                    'errors' => [
                        'existing_project_id' => ['Selected Linear project is not available to this connection.'],
                    ],
                ], 422);
            }

            $selectedProjectId = trim((string) ($selected['id'] ?? ''));
            $selectedProjectName = $selected['name'] ?? null;
            $selectedProjectUrl = $selected['url'] ?? null;
        }

        $metadata = is_array($provider->metadata_json) ? $provider->metadata_json : [];
        $metadata['project_sync'] = [
            'mode' => $projectMode,
            'selected_project_id' => $selectedProjectId,
            'selected_project_name' => $selectedProjectName,
            'selected_project_url' => $selectedProjectUrl,
            'updated_at' => CarbonImmutable::now('UTC')->toIso8601String(),
        ];

        $provider->metadata_json = $metadata;
        $provider->save();

        return response()->json([
            'data' => [
                'driver' => $normalizedDriver,
                'project_mode' => $projectMode,
                'selected_project_id' => $selectedProjectId,
                'selected_project_name' => $selectedProjectName,
                'selected_project_url' => $selectedProjectUrl,
            ],
        ]);
    }

    private function oauthRedirectUri(string $driver): string
    {
        $configured = trim((string) config('services.'.strtolower(trim($driver)).'.redirect_uri', ''));
        if ($configured !== '') {
            return $configured;
        }

        return route('integrations.oauth.callback', ['provider' => $driver]);
    }

    private function connectedProviderForSession(InterrogationSession $session, string $driver): ?ConnectedProvider
    {
        return $session->providerIntegrations()
            ->where('category', 'task_management')
            ->where('driver', $driver)
            ->orderByDesc('id')
            ->first();
    }
}
