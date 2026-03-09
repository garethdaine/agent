<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Connector\FindExistingConnectedProviderAction;
use App\Actions\Connector\SaveConnectedProviderAction;
use App\Actions\Interrogation\UpdateSessionPhaseAction;
use App\Models\InterrogationSession;
use App\Support\TaskProviders\ProviderOAuthStateStore;
use App\Support\TaskProviders\TaskManagementProviderManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class TaskProviderOAuthController extends Controller
{
    public function store(
        Request $request,
        string $provider,
        TaskManagementProviderManager $providerManager,
        ProviderOAuthStateStore $oauthStateStore,
        SaveConnectedProviderAction $saveProvider,
        FindExistingConnectedProviderAction $findExistingProvider,
        UpdateSessionPhaseAction $updateSessionPhase,
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

        /** @var \App\Models\InterrogationSession|null $session */
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

            $existingProvider = $findExistingProvider->execute((int) $session->id, $providerDriver->key());
            $existingMetadata = is_array($existingProvider?->metadata_json) ? $existingProvider->metadata_json : [];

            $saveProvider->execute(
                sessionId: (int) $session->id,
                userId: (int) $user->id,
                driverKey: $providerDriver->key(),
                token: $token,
                identity: $identity,
                existingMetadata: $existingMetadata,
            );

            if ((int) $session->phase < InterrogationSession::PHASE_PROVIDER_SETUP) {
                $updateSessionPhase->execute(
                    $session,
                    InterrogationSession::PHASE_PROVIDER_SETUP,
                    InterrogationSession::STATUS_SETUP,
                );
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
