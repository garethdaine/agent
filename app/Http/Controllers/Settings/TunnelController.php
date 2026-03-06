<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Jobs\Tunnel\TunnelRunJob;
use App\Repositories\TunnelSettingsRepository;
use App\Services\Tunnel\CloudflaredInstaller;
use App\Services\Tunnel\CloudflaredService;
use App\Services\Tunnel\TunnelSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Process;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class TunnelController extends Controller
{
    public function __construct(
        private readonly CloudflaredService $cloudflaredService,
        private readonly CloudflaredInstaller $installer,
        private readonly TunnelSettingsRepository $repository,
        private readonly TunnelSyncService $syncService,
    ) {}

    public function index(): InertiaResponse
    {
        $settings = $this->repository->getSettings();
        $status = $this->repository->getStatus();
        if (config('tunnel.use_sync_start', false) && $this->syncService->hasSyncProcess()) {
            $status = 'active';
        }
        $validation = $this->cloudflaredService->validateCredentials();
        $version = $this->cloudflaredService->isVersionCompatible();

        return Inertia::render('Settings/Tunnel/Index', [
            'tunnelSettings' => $settings,
            'status' => $status,
            'validationChecks' => $validation,
            'versionInfo' => $version,
            'appUrl' => config('app.url'),
            'useSyncStart' => config('tunnel.use_sync_start', false),
        ]);
    }

    public function wizard(): InertiaResponse
    {
        $settings = $this->repository->getSettings();
        $status = $this->repository->getStatus();

        return Inertia::render('Settings/Tunnel/Wizard', [
            'tunnelSettings' => $settings,
            'status' => $status,
            'appUrl' => config('app.url'),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'hostname' => ['nullable', 'string', 'regex:/^[a-zA-Z0-9]([a-zA-Z0-9\-\.]*[a-zA-Z0-9])?$/'],
            'protocol' => ['required', 'in:http2,quic'],
            'ip_allowlist' => ['nullable', 'array'],
            'ip_allowlist.*' => ['string', 'regex:/^(\d{1,3}\.){3}\d{1,3}\/\d{1,2}$/'],
            'cloudflare_access_enabled' => ['sometimes', 'boolean'],
        ]);

        $validated['origin_url'] = config('app.url');

        $settings = $this->repository->getSettings();
        $merged = array_merge($settings, $validated);
        $hasMinConfig = ! empty($merged['tunnel_uuid']) && ! empty($merged['hostname']);
        $status = $hasMinConfig && $this->repository->getStatus() === 'unconfigured'
            ? 'stopped'
            : null;

        $this->repository->update($validated, $status);

        return response()->json(['message' => 'Tunnel settings updated.']);
    }

    public function start(Request $request): JsonResponse|RedirectResponse
    {
        $settings = $this->repository->getSettings();
        $hasMinConfig = ! empty($settings['tunnel_uuid'])
            && ! empty($settings['hostname'])
            && ! empty($settings['origin_url'])
            && ! empty($settings['protocol']);

        if (! $hasMinConfig) {
            throw ValidationException::withMessages([
                'tunnel' => ['Tunnel is not configured. Complete setup first.'],
            ]);
        }

        if ($this->repository->getStatus() === 'unconfigured') {
            $this->repository->updateStatus('stopped');
        }

        $this->repository->update(['last_error' => null]);

        if (config('tunnel.use_sync_start', false)) {
            $result = $this->syncService->spawnSyncProcess();
            if (! $result['success']) {
                throw ValidationException::withMessages([
                    'tunnel' => [$result['error'] ?? 'Failed to start tunnel.'],
                ]);
            }
            $this->repository->updateStatus('active');
        } else {
            TunnelRunJob::dispatch();
        }

        if ($request->header('X-Inertia')) {
            return redirect()->route('settings.tunnel.index');
        }

        return response()->json(['message' => 'Tunnel start initiated.']);
    }

    public function stop(Request $request): JsonResponse|RedirectResponse
    {
        if (config('tunnel.use_sync_start', false)) {
            $this->syncService->killSyncProcess();
        }

        $this->repository->updateStatus('stopped');

        if ($request->header('X-Inertia')) {
            return redirect()->back();
        }

        return response()->json(['message' => 'Tunnel stopped.']);
    }

    public function delete(Request $request): JsonResponse|RedirectResponse
    {
        $settings = $this->repository->getSettings();
        $uuid = $settings['tunnel_uuid'] ?? null;

        if ($uuid) {
            $this->cloudflaredService->deleteTunnel($uuid);
        }

        $this->repository->update([
            'tunnel_name' => '',
            'tunnel_uuid' => '',
            'hostname' => '',
            'origin_url' => 'http://localhost:8000',
            'protocol' => 'http2',
            'ip_allowlist' => [],
            'cloudflare_access_enabled' => false,
            'access_client_id' => null,
            'access_client_secret' => null,
        ], 'unconfigured');

        if ($request->header('X-Inertia')) {
            return redirect()->route('settings.tunnel.index');
        }

        return response()->json(['message' => 'Tunnel deleted.']);
    }

    public function installCheck(): JsonResponse
    {
        $validation = $this->cloudflaredService->validateCredentials();
        $platform = $this->installer->detectPlatform();
        $installCommand = $this->installer->getInstallCommand($platform);

        return response()->json(array_merge($validation, [
            'platform' => $platform,
            'install_command' => $installCommand,
        ]));
    }

    public function install(): JsonResponse
    {
        if (! config('tunnel.allow_install', false)) {
            return response()->json([
                'success' => false,
                'message' => 'Server-side installation is disabled. Set TUNNEL_ALLOW_INSTALL=true and run the install command manually in your terminal.',
            ], 403);
        }

        $validation = $this->cloudflaredService->validateCredentials();
        if ($validation['binary']) {
            return response()->json(['success' => true, 'message' => 'cloudflared is already installed.']);
        }

        $platform = $this->installer->detectPlatform();
        $command = $this->installer->getInstallCommand($platform);

        $result = Process::timeout(120)->run($command);

        if (! $result->successful()) {
            return response()->json([
                'success' => false,
                'message' => 'Installation failed. Run the command manually in your terminal.',
                'output' => trim($result->output().$result->errorOutput()),
                'install_command' => $command,
            ], 422);
        }

        $validation = $this->cloudflaredService->validateCredentials();

        return response()->json([
            'success' => true,
            'message' => 'cloudflared installed successfully.',
            'binary' => $validation['binary'],
            'credentials' => $validation['credentials'],
            'functional' => $validation['functional'],
        ]);
    }

    public function authStart(): JsonResponse
    {
        $result = $this->cloudflaredService->startLogin();

        return response()->json($result);
    }

    public function authPoll(): JsonResponse
    {
        $authenticated = $this->cloudflaredService->pollLoginComplete();

        return response()->json(['authenticated' => $authenticated]);
    }

    public function createTunnel(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tunnel_name' => ['required', 'string', 'max:255'],
            'hostname' => ['required', 'string', 'max:255', 'regex:/^[a-zA-Z0-9]([a-zA-Z0-9\-\.]*[a-zA-Z0-9])?$/'],
        ]);

        $result = $this->cloudflaredService->createTunnel($validated['tunnel_name']);

        if (! $result['success']) {
            return response()->json(['message' => $result['error'] ?? 'Failed to create tunnel.'], 422);
        }

        $this->repository->update([
            'tunnel_name' => $validated['tunnel_name'],
            'tunnel_uuid' => $result['id'],
            'hostname' => $validated['hostname'],
        ]);

        return response()->json([
            'tunnel_name' => $validated['tunnel_name'],
            'tunnel_uuid' => $result['id'],
        ]);
    }

    public function useExistingTunnel(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tunnel_name' => ['required', 'string', 'max:255'],
            'hostname' => ['required', 'string', 'max:255', 'regex:/^[a-zA-Z0-9]([a-zA-Z0-9\-\.]*[a-zA-Z0-9])?$/'],
        ]);

        $tunnels = $this->cloudflaredService->listTunnels();
        $list = is_array($tunnels) && isset($tunnels[0]) ? $tunnels : ($tunnels['tunnels'] ?? []);
        $match = collect($list)->first(fn ($t) => ($t['name'] ?? '') === $validated['tunnel_name']);
        $uuid = $match['id'] ?? $match['tunnel_id'] ?? null;

        if (! $match || ! $uuid) {
            return response()->json([
                'message' => 'Tunnel with that name not found. It may have been deleted.',
            ], 422);
        }

        $this->repository->update([
            'tunnel_name' => $validated['tunnel_name'],
            'tunnel_uuid' => $uuid,
            'hostname' => $validated['hostname'],
        ]);

        return response()->json([
            'tunnel_name' => $validated['tunnel_name'],
            'tunnel_uuid' => $uuid,
        ]);
    }

    public function routeDns(): JsonResponse
    {
        $settings = $this->repository->getSettings();
        $uuid = $settings['tunnel_uuid'] ?? '';
        $hostname = $settings['hostname'] ?? '';

        $result = $this->cloudflaredService->routeDns($uuid, $hostname);

        return response()->json($result);
    }

    public function generateAccessToken(): JsonResponse
    {
        $settings = $this->repository->getSettings();

        if (! empty($settings['access_client_id'])) {
            return response()->json([
                'client_id' => $settings['access_client_id'],
                'client_secret' => null,
                'message' => 'Access token already generated. Secret is not re-displayed.',
            ]);
        }

        $hostname = $settings['hostname'] ?? '';
        $result = $this->cloudflaredService->generateAccessServiceToken($hostname);

        if (! $result['success']) {
            return response()->json(['message' => $result['error'] ?? 'Failed to generate access token.'], 422);
        }

        $this->repository->update([
            'access_client_id' => $result['client_id'],
            'access_client_secret' => $result['client_secret'],
        ]);

        return response()->json([
            'client_id' => $result['client_id'],
            'client_secret' => $result['client_secret'],
        ]);
    }
}
