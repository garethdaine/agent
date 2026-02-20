<?php

namespace App\Http\Controllers;

use App\Models\ConnectedProvider;
use App\Models\InterrogationSession;
use App\Support\TaskProviders\ProviderOAuthStateStore;
use App\Support\TaskProviders\TaskManagementProviderManager;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class TaskProviderOAuthController extends Controller
{
    public function callback(
        Request $request,
        string $provider,
        TaskManagementProviderManager $providerManager,
        ProviderOAuthStateStore $oauthStateStore,
    ): RedirectResponse {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('tools.discovery.index');
        }

        $state = trim((string) $request->query('state', ''));
        $code = trim((string) $request->query('code', ''));

        if ($state === '' || $code === '') {
            return $this->redirectWithError($request, null, 'Task provider OAuth callback was missing required parameters.');
        }

        $payload = $oauthStateStore->pull($state);
        if (! is_array($payload)) {
            return $this->redirectWithError($request, null, 'Task provider OAuth state expired. Retry provider authentication.');
        }

        $sessionId = (int) ($payload['session_id'] ?? 0);
        $stateUserId = (int) ($payload['user_id'] ?? 0);
        $stateDriver = strtolower(trim((string) ($payload['driver'] ?? '')));
        $routeProvider = strtolower(trim((string) $provider));
        $returnTo = strtolower(trim((string) ($payload['return_to'] ?? 'wizard')));
        if (! in_array($returnTo, ['wizard', 'settings'], true)) {
            $returnTo = 'wizard';
        }

        if ($stateUserId !== (int) $user->id || $stateDriver !== $routeProvider) {
            return $this->redirectWithError(
                $request,
                $sessionId > 0 ? $sessionId : null,
                'Task provider OAuth callback did not match this user/session context.',
                $returnTo,
            );
        }

        $session = $user->interrogationSessions()->find($sessionId);
        if ($session === null) {
            return $this->redirectWithError($request, null, 'Session not found for task provider callback.');
        }

        try {
            $providerDriver = $providerManager->driver($stateDriver);
        } catch (InvalidArgumentException $exception) {
            return $this->redirectWithError($request, (int) $session->id, $exception->getMessage(), $returnTo);
        }

        try {
            $redirectUri = $this->oauthRedirectUri($providerDriver->key());
            $token = $providerDriver->exchangeAuthorizationCode($code, $redirectUri);
            $identity = $providerDriver->fetchIdentity((string) ($token['access_token'] ?? ''));

            $existingProvider = ConnectedProvider::query()
                ->where('providerable_type', InterrogationSession::class)
                ->where('providerable_id', (int) $session->id)
                ->where('category', 'task_management')
                ->where('driver', $providerDriver->key())
                ->first();
            $existingMetadata = is_array($existingProvider?->metadata_json) ? $existingProvider->metadata_json : [];
            $projectSync = is_array($existingMetadata['project_sync'] ?? null) ? $existingMetadata['project_sync'] : [];
            $projectMode = in_array(($projectSync['mode'] ?? null), ['create_new', 'existing'], true)
                ? (string) $projectSync['mode']
                : 'create_new';

            ConnectedProvider::query()->updateOrCreate(
                [
                    'providerable_type' => InterrogationSession::class,
                    'providerable_id' => (int) $session->id,
                    'category' => 'task_management',
                    'driver' => $providerDriver->key(),
                ],
                [
                    'user_id' => (int) $user->id,
                    'provider_user_id' => $identity['provider_user_id'] ?? null,
                    'provider_workspace_id' => $identity['workspace_id'] ?? null,
                    'provider_workspace_name' => $identity['workspace_name'] ?? null,
                    'access_token' => $token['access_token'] ?? null,
                    'refresh_token' => $token['refresh_token'] ?? null,
                    'token_type' => $token['token_type'] ?? null,
                    'expires_at' => $token['expires_at'] ?? null,
                    'scopes_json' => $token['scopes'] ?? [],
                    'metadata_json' => [
                        'provider' => $providerDriver->key(),
                        'connected_at' => CarbonImmutable::now('UTC')->toIso8601String(),
                        'team_id' => $identity['team_id'] ?? null,
                        'team_name' => $identity['team_name'] ?? null,
                        'team_key' => $identity['team_key'] ?? null,
                        'identity' => $identity,
                        'project_sync' => [
                            'mode' => $projectMode,
                            'selected_project_id' => $projectMode === 'existing'
                                ? (is_string($projectSync['selected_project_id'] ?? null) ? trim((string) $projectSync['selected_project_id']) : null)
                                : null,
                            'selected_project_name' => $projectMode === 'existing'
                                ? ($projectSync['selected_project_name'] ?? null)
                                : null,
                            'selected_project_url' => $projectMode === 'existing'
                                ? ($projectSync['selected_project_url'] ?? null)
                                : null,
                            'updated_at' => CarbonImmutable::now('UTC')->toIso8601String(),
                        ],
                    ],
                ],
            );

            if ((int) $session->phase <= InterrogationSession::PHASE_PROVIDER_SETUP) {
                $session->phase = InterrogationSession::PHASE_TECH_STACK_SETUP;
                $session->status = InterrogationSession::STATUS_SETUP;
                $session->save();
            }
        } catch (\Throwable $throwable) {
            report($throwable);

            return $this->redirectWithError($request, (int) $session->id, $throwable->getMessage(), $returnTo);
        }

        $routeName = $returnTo === 'settings'
            ? 'tools.discovery.session.settings'
            : 'tools.discovery.wizard';

        return redirect()->route($routeName, ['id' => $session->id, 'provider_connected' => $providerDriver->key()]);
    }

    private function oauthRedirectUri(string $driver): string
    {
        $configured = trim((string) config('services.'.strtolower(trim($driver)).'.redirect_uri', ''));
        if ($configured !== '') {
            return $configured;
        }

        return route('integrations.oauth.callback', ['provider' => $driver]);
    }

    private function redirectWithError(Request $request, ?int $sessionId, string $message, string $returnTo = 'wizard'): RedirectResponse
    {
        if (! in_array($returnTo, ['wizard', 'settings'], true)) {
            $returnTo = 'wizard';
        }

        if ($sessionId !== null && $sessionId > 0) {
            $routeName = $returnTo === 'settings'
                ? 'tools.discovery.session.settings'
                : 'tools.discovery.wizard';

            return redirect()->route($routeName, [
                'id' => $sessionId,
                'provider_error' => mb_substr(trim($message), 0, 240),
            ]);
        }

        return redirect()->route('tools.discovery.index', [
            'provider_error' => mb_substr(trim($message), 0, 240),
        ]);
    }
}
