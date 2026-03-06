# Implementation Plan

Derived from discovery session 2.

# Cloudflare Tunnel Integration — Implementation Plan

## Section 1: Foundation — Config, Migration, Repository, and Feature Flag

### 1.1 Register `tunnel.enabled` Feature Flag in FeatureFlagManager

**File:** `app/Support/Agent/FeatureFlagManager.php`

- Add constant `TUNNEL_ENABLED = 'tunnel.enabled'` in the "Platform flag constants" block (after `COMPACTION_ENABLED`).
- Add DEFINITIONS entry:
  ```php
  self::TUNNEL_ENABLED => [
      'label' => 'Cloudflare Tunnel',
      'description' => 'Enable Cloudflare Tunnel integration for secure remote access via a custom hostname.',
  ],
  ```
- The flag defaults to `false` (opt-in) via `config('tunnel.enabled', false)`.

**Acceptance check:** Toggle the flag via the existing Features settings page (`/tools/features/settings`). Confirm it appears in the list with label and description. Confirm `FeatureFlagManager::TUNNEL_ENABLED` resolves correctly when enabled/disabled.

### 1.2 Create `config/tunnel.php`

**File:** `config/tunnel.php` (new)

```php
return [
    'enabled' => env('TUNNEL_ENABLED', false),
    'tunnel_name' => env('TUNNEL_NAME', ''),
    'tunnel_uuid' => env('TUNNEL_UUID', ''),
    'hostname' => env('TUNNEL_HOSTNAME', ''),
    'origin_url' => env('TUNNEL_ORIGIN_URL', 'http://localhost:8000'),
    'protocol' => env('TUNNEL_PROTOCOL', 'http2'),
    'cloudflared_binary' => env('CLOUDFLARED_BINARY', 'cloudflared'),
    'metrics_port' => env('TUNNEL_METRICS_PORT', 20241),
    'ip_allowlist' => [],
    'cloudflare_access_enabled' => false,
    'status_poll_interval' => 5,
    'min_version' => '2024.1.0',
];
```

### 1.3 Create `tunnel_settings` Migration

**File:** `database/migrations/xxxx_xx_xx_create_tunnel_settings_table.php` (new)

- Schema: `id` (bigIncrements), `settings` (json, default `{}`), `status` (string, default `'unconfigured'`, indexed), `created_at`, `updated_at`.
- JSON `settings` column stores: `tunnel_name`, `tunnel_uuid`, `hostname`, `origin_url`, `protocol`, `ip_allowlist`, `cloudflare_access_enabled`, `access_client_id`, `access_client_secret`.
- Add a database seeder or migration hook that inserts the single default row with status `unconfigured` and empty JSON settings `{}`.

### 1.4 Create `TunnelSetting` Eloquent Model

**File:** `app/Models/TunnelSetting.php` (new)

- Table: `tunnel_settings`.
- Casts: `settings` as `array`, `status` as string.
- No mass-assignment guard needed (single-row, repository-managed).
- Helper accessors for common settings keys (`hostname`, `tunnel_uuid`, `origin_url`, etc.) that read from the `settings` JSON.
- `isActive()`, `isStopped()`, `isError()`, `isUnconfigured()` status helpers.

### 1.5 Create `TunnelSettingsRepository`

**File:** `app/Repositories/TunnelSettingsRepository.php` (new)

- `get(): TunnelSetting` — returns the single row, creating it if absent. Merges any missing keys from `config/tunnel.php` defaults into the settings JSON on read.
- `update(array $settings, ?string $status = null): TunnelSetting` — updates the JSON settings column (merging with existing), optionally updates status.
- `updateStatus(string $status): TunnelSetting` — updates only the status column.
- `getStatus(): string` — returns the current status string.
- `getSettings(): array` — returns the merged settings array (DB values + config defaults for missing keys).
- `clearAccessCredentials(): void` — nullifies `access_client_id` and `access_client_secret` in JSON.

**Acceptance check:** Unit test confirms single-row creation, config default merging, status transitions, and JSON key updates.

### 1.6 Add Horizon `supervisor-tunnel` Entry

**File:** `config/horizon.php`

- Add to `defaults` array after `supervisor-subagent`:
  ```php
  'supervisor-tunnel' => [
      'connection' => 'redis',
      'queue' => ['tunnel'],
      'balance' => 'false',
      'maxProcesses' => 1,
      'maxTime' => 0,
      'maxJobs' => 0,
      'memory' => 128,
      'tries' => 5,
      'backoff' => [10, 30, 60, 120, 300],
      'timeout' => 0,
      'nice' => 0,
  ],
  ```
- Add corresponding entries to `environments.production` (`maxProcesses => 1`) and `environments.local` (empty override `[]`).
- Add `'redis:tunnel' => 30` to the `waits` array.

### 1.7 Share `tunnelEnabled` Flag via Inertia

**File:** `app/Http/Middleware/HandleInertiaRequests.php`

- Add to the `share()` return array:
  ```php
  'tunnelEnabled' => app(FeatureFlagManager::class)->isEnabled(FeatureFlagManager::TUNNEL_ENABLED),
  ```

---

## Section 2: CloudflaredService — CLI Wrapper

### 2.1 Create `CloudflaredService`

**File:** `app/Services/Tunnel/CloudflaredService.php` (new)

**Constants:**
- `MIN_VERSION = '2024.1.0'`

**Methods (all wrap shell commands via Laravel `Process` facade):**

- `isBinaryInstalled(): bool` — checks if cloudflared binary is on PATH using `which`/`command -v`. Returns boolean.
- `getBinaryPath(): ?string` — returns the full path to the cloudflared binary, or null.
- `getVersion(): ?string` — runs `cloudflared version`, parses semver from output (regex: `/(\d+\.\d+\.\d+)/`). Returns version string or null.
- `isVersionCompatible(): array` — returns `['compatible' => bool, 'installed_version' => ?string, 'min_version' => string, 'warning' => ?string]`.
- `isAuthenticated(): bool` — checks `~/.cloudflared/cert.pem` file existence.
- `getCertPath(): string` — returns the expected cert.pem path (`$HOME/.cloudflared/cert.pem`).
- `listTunnels(): array` — runs `cloudflared tunnel list --output json`, parses JSON output. Returns array of tunnel objects.
- `validateCredentials(): array` — performs three-check validation: binary installed, cert.pem exists, tunnel list succeeds. Returns `['binary' => bool, 'credentials' => bool, 'functional' => bool, 'version' => ?string, 'version_warning' => ?string]`.
- `startLogin(): array` — runs `cloudflared tunnel login` as a background process, captures stdout, parses the authorization URL (regex for `https://dash.cloudflare.com/...`). Returns `['pid' => int, 'auth_url' => string]`.
- `pollLoginComplete(): bool` — checks if `cert.pem` now exists.
- `createTunnel(string $name): array` — runs `cloudflared tunnel create <name> --output json`. Returns parsed JSON with tunnel UUID.
- `deleteTunnel(string $uuidOrName): bool` — runs `cloudflared tunnel delete <uuid>`. Returns success boolean.
- `buildRunCommand(string $uuid, string $originUrl, string $protocol, ?array $ipAllowlist, int $metricsPort): array` — constructs the full `cloudflared tunnel run` command arguments array including `--url`, `--protocol`, `--metrics`, and generates a temporary ingress config YAML with IP allowlist rules if present. Returns `['command' => array, 'config_path' => ?string]`.
- `routeDns(string $tunnelUuid, string $hostname): array` — runs `cloudflared tunnel route dns <uuid> <hostname>`. Returns `['success' => bool, 'cname_target' => string, 'error' => ?string, 'manual_instructions' => ?string]`.
- `generateAccessServiceToken(string $hostname): array` — runs `cloudflared access service-token --hostname <hostname>`. Parses output for client ID and client secret. Returns `['client_id' => string, 'client_secret' => string]`.

**Internal helpers:**
- `runProcess(array $command, int $timeout = 30): ProcessResult` — wrapper around Laravel Process with error handling.
- `parseVersion(string $output): ?string` — regex-based semver extraction.
- `compareVersions(string $installed, string $minimum): bool` — semver comparison using `version_compare`.
- `generateIngressConfig(string $uuid, string $originUrl, ?array $ipAllowlist): string` — writes a YAML config to a temp file for `cloudflared tunnel run --config`.

**Acceptance check:** All methods are unit-tested with mocked `Process` facade calls. No real cloudflared binary required. Tests validate command construction, output parsing, version comparison, and error handling.

### 2.2 Create `CloudflaredInstaller`

**File:** `app/Services/Tunnel/CloudflaredInstaller.php` (new)

- `detectPlatform(): string` — returns `'macos'`, `'linux-apt'`, `'linux-yum'`, or `'linux-binary'` based on `PHP_OS_FAMILY` and package manager availability.
- `getInstallCommand(): string` — returns platform-appropriate command: `brew install cloudflared`, `sudo apt-get install -y cloudflared`, `sudo yum install -y cloudflared`, or the direct binary download URL/commands.
- `getInstallInstructions(): array` — returns `['platform' => string, 'command' => string, 'description' => string, 'manual_url' => string]`.
- `verifyInstallation(): array` — delegates to `CloudflaredService::validateCredentials()` and adds platform info.

### 2.3 Create `TunnelHealthMonitor`

**File:** `app/Services/Tunnel/TunnelHealthMonitor.php` (new)

- `checkProcessStatus(): array` — checks if the cloudflared process is running (via `pgrep cloudflared` or checking the PID stored in settings). Returns `['alive' => bool, 'pid' => ?int, 'exit_code' => ?int]`.
- `fetchMetrics(int $metricsPort = 20241): array` — HTTP GET to `http://localhost:{$metricsPort}/metrics`, parses Prometheus text format. Extracts: `cloudflared_tunnel_connections` (gauge for active connections), `cloudflared_tunnel_request_errors` (counter), `process_start_time_seconds` (for uptime calculation). Returns `['connections' => int, 'error_rate' => float, 'uptime_seconds' => int, 'raw_available' => bool]`.
- `getHealth(): array` — aggregates process status + metrics + version info + tunnel settings status. Returns full health payload for the dashboard card and API endpoint.

---

## Section 3: Artisan Commands

### 3.1 `php artisan tunnel:run`

**File:** `app/Console/Commands/Tunnel/TunnelRunCommand.php` (new)

- Signature: `tunnel:run`
- Description: "Run the cloudflared tunnel process (Horizon entry point)"
- Dispatches `TunnelRunJob` to the `tunnel` queue OR directly executes the cloudflared process in the foreground (when called directly). When dispatched via Horizon, the job wraps the long-lived `cloudflared tunnel run` process.
- Validates tunnel is configured (status not `unconfigured`) before starting.
- Sets tunnel status to `active` in the database on successful start.
- On process exit, sets status to `stopped` or `error` based on exit code.

### 3.2 `TunnelRunJob`

**File:** `app/Jobs/Tunnel/TunnelRunJob.php` (new)

- Queue: `tunnel`
- Properties: `$tries = 5`, `$backoff = [10, 30, 60, 120, 300]`, `$timeout = 0`
- `handle()`: Uses `CloudflaredService::buildRunCommand()` to get the command, then executes it as a long-lived process via `Process::forever()->run()`. Sets status to `active` on start.
- `failed()`: Checks if all 5 attempts exhausted. If so, updates tunnel status to `error` in the database. Logs the failure reason.
- Crash tracking: Stores attempt timestamps in Redis (key: `tunnel:restart_attempts`) to enforce the 5-in-10-minutes window. If a 6th crash occurs outside the 10-minute window, the counter resets.

### 3.3 `php artisan tunnel:install`

**File:** `app/Console/Commands/Tunnel/TunnelInstallCommand.php` (new)

- Signature: `tunnel:install`
- Description: "Check cloudflared installation and provide platform-appropriate install instructions"
- Uses `CloudflaredInstaller` to detect platform and output install command.
- Runs the three-check validation (`CloudflaredService::validateCredentials()`).
- Displays version warning if below `MIN_VERSION`.
- Outputs clear pass/fail status for each check with colored terminal output.

### 3.4 `php artisan tunnel:status`

**File:** `app/Console/Commands/Tunnel/TunnelStatusCommand.php` (new)

- Signature: `tunnel:status`
- Description: "Display tunnel health and status"
- Uses `TunnelHealthMonitor::getHealth()` to fetch and display: process alive/dead, connection count, uptime, error rate, version info, tunnel hostname, current status.
- Tabular CLI output format.

---

## Section 4: Middleware and Security

### 4.1 Create `TunnelFeatureGate` Middleware

**File:** `app/Http/Middleware/TunnelFeatureGate.php` (new)

- Follows the exact pattern of `OrgFeatureGate.php`.
- Injects `FeatureFlagManager`, checks `TUNNEL_ENABLED`.
- Returns `ErrorEnvelope::make('FEATURE_DISABLED', 'Cloudflare Tunnel is not enabled.', 404)` when disabled.
- For Inertia routes, returns a 404 redirect instead.

### 4.2 Create `TunnelIpAllowlistMiddleware`

**File:** `app/Http/Middleware/TunnelIpAllowlistMiddleware.php` (new)

- Reads IP allowlist from `TunnelSettingsRepository::getSettings()['ip_allowlist']`.
- If allowlist is empty or null, passes through (no restriction).
- Reads client IP from `CF-Connecting-IP` header (falls back to `request->ip()`).
- Validates IP against each CIDR range using a `cidrMatch(string $ip, string $cidr): bool` helper (inet_pton / bitwise comparison).
- Returns 403 JSON response with `ErrorEnvelope::make('IP_BLOCKED', ...)` for non-matching IPs.
- Only active when tunnel status is `active` (skip enforcement when tunnel is stopped/unconfigured).

**Acceptance check:** Unit tests with mocked repository, various IP/CIDR combinations (IPv4, IPv6), empty allowlist passthrough, header fallback.

### 4.3 Register Middleware

**File:** `app/Http/Kernel.php` or route-level registration (depending on project pattern)

- Register `TunnelFeatureGate` as route middleware alias `tunnel.feature`.
- Register `TunnelIpAllowlistMiddleware` as route middleware alias `tunnel.ip`.
- Apply `tunnel.ip` middleware globally or to the web middleware group (defense-in-depth — only enforces when tunnel is active and allowlist is non-empty).

---

## Section 5: Controllers and API Routes

### 5.1 Create `TunnelController`

**File:** `app/Http/Controllers/Settings/TunnelController.php` (new)

**Inertia page methods:**
- `index(Request $request)` — renders `Settings/Tunnel/Index` page with current tunnel settings, status, version info, and validation state. Passes `tunnelSettings`, `status`, `validationChecks`, `versionInfo` as Inertia props.
- `wizard(Request $request)` — renders `Settings/Tunnel/Wizard` page for first-time setup.

**Action methods (all return JSON or Inertia redirect):**
- `update(Request $request)` — validates and updates tunnel settings (hostname, origin_url, protocol, ip_allowlist, cloudflare_access_enabled). Uses `TunnelSettingsRepository::update()`.
- `start(Request $request)` — dispatches `TunnelRunJob` to the `tunnel` queue, sets status to `active`. Returns confirmation.
- `stop(Request $request)` — kills the running cloudflared process (via `CloudflaredService`), sets status to `stopped`.
- `delete(Request $request)` — runs `CloudflaredService::deleteTunnel()`, clears settings, sets status to `unconfigured`.
- `installCheck(Request $request)` — runs `CloudflaredService::validateCredentials()`. Returns JSON with binary/credentials/functional checks + version warning.
- `authStart(Request $request)` — runs `CloudflaredService::startLogin()`. Returns JSON with `auth_url`.
- `authPoll(Request $request)` — runs `CloudflaredService::pollLoginComplete()`. Returns JSON with `authenticated: bool`.
- `createTunnel(Request $request)` — validates tunnel name, runs `CloudflaredService::createTunnel()`, stores UUID in settings. Returns JSON.
- `routeDns(Request $request)` — runs `CloudflaredService::routeDns()`. Returns JSON with success/failure and manual instructions.
- `generateAccessToken(Request $request)` — runs `CloudflaredService::generateAccessServiceToken()`, stores client_id and client_secret in settings JSON. Returns the credentials once. Subsequent calls return only the client_id (secret is not re-displayed).

### 5.2 Create `TunnelStatusController`

**File:** `app/Http/Controllers/Api/TunnelStatusController.php` (new)

- `__invoke(Request $request)` — uses `TunnelHealthMonitor::getHealth()` to return JSON health payload for dashboard polling. Includes: status, hostname, connections, uptime, error_rate, version, version_warning.

### 5.3 Register Routes

**File:** `routes/web.php`

Add within the authenticated middleware group, wrapped in `tunnel.feature` middleware:

```php
Route::middleware(['tunnel.feature'])->group(function () {
    Route::get('/settings/tunnel', [TunnelController::class, 'index'])->name('settings.tunnel.index');
    Route::get('/settings/tunnel/wizard', [TunnelController::class, 'wizard'])->name('settings.tunnel.wizard');
    Route::post('/settings/tunnel', [TunnelController::class, 'update'])->name('settings.tunnel.update');
    Route::post('/settings/tunnel/start', [TunnelController::class, 'start'])->name('settings.tunnel.start');
    Route::post('/settings/tunnel/stop', [TunnelController::class, 'stop'])->name('settings.tunnel.stop');
    Route::post('/settings/tunnel/delete', [TunnelController::class, 'delete'])->name('settings.tunnel.delete');
    Route::post('/settings/tunnel/install-check', [TunnelController::class, 'installCheck'])->name('settings.tunnel.install-check');
    Route::post('/settings/tunnel/auth-start', [TunnelController::class, 'authStart'])->name('settings.tunnel.auth-start');
    Route::post('/settings/tunnel/auth-poll', [TunnelController::class, 'authPoll'])->name('settings.tunnel.auth-poll');
    Route::post('/settings/tunnel/create', [TunnelController::class, 'createTunnel'])->name('settings.tunnel.create');
    Route::post('/settings/tunnel/route-dns', [TunnelController::class, 'routeDns'])->name('settings.tunnel.route-dns');
    Route::post('/settings/tunnel/access-token', [TunnelController::class, 'generateAccessToken'])->name('settings.tunnel.access-token');
});
```

**File:** `routes/api.php` (or `routes/web.php` API section)

```php
Route::middleware(['auth', 'tunnel.feature'])->get('/api/tunnel/status', TunnelStatusController::class)->name('api.tunnel.status');
```

---

## Section 6: Frontend — Vue Components and Pages

### 6.1 Add Tunnel Nav Item to AppSidebar

**File:** `resources/js/Components/AppSidebar.vue`

- Import `Globe` icon from `lucide-vue-next` (represents networking/tunnel).
- Add a new `SidebarNavLink` inside the "Settings" `SidebarNavGroup`, after the "Memory" link and before "Configuration", gated by `v-if="page.props.tunnelEnabled"`:
  ```html
  <SidebarNavLink
      v-if="page.props.tunnelEnabled"
      :href="route('settings.tunnel.index')"
      :active="route().current('settings.tunnel.*')"
      :collapsed="collapsed"
  >
      <template #icon>
          <Globe class="h-4 w-4 shrink-0" />
      </template>
      Tunnel
  </SidebarNavLink>
  ```

**Acceptance check:** When `tunnel.enabled` flag is on, "Tunnel" appears in the Settings sidebar group. When off, it is absent. Clicking navigates to `/settings/tunnel`.

### 6.2 Create `TunnelSettings.vue` Page

**File:** `resources/js/Pages/Settings/Tunnel/Index.vue` (new)

- Uses `AppLayout`.
- Displays current tunnel configuration in editable form fields: hostname, origin URL, protocol (select: http2/quic), IP allowlist (tag/chip input for CIDR ranges).
- Status indicator badge (active=green, stopped=yellow, error=red, unconfigured=gray).
- Start/Stop toggle button (POST to start/stop endpoints).
- Delete tunnel button with confirmation modal.
- Version warning banner (non-blocking, yellow) when applicable.
- Security warning banner (prominent, orange) reminding users to configure authentication before activation.
- Cloudflare Access section: toggle switch, displays client_id when enabled, "Generate Service Token" button that calls the access-token endpoint and displays credentials once with copy buttons.
- IP Allowlist section: CIDR input field with add/remove, displays current list.
- "Run Setup Wizard" button that navigates to the wizard page (visible when status is `unconfigured`).
- Form submission via Inertia `router.post()` to `settings.tunnel.update`.

### 6.3 Create `TunnelSetupWizard.vue` Component

**File:** `resources/js/Pages/Settings/Tunnel/Wizard.vue` (new)

- Uses `AppLayout`.
- Multi-step wizard with 5 steps, managed by a `currentStep` ref.
- Step indicator/breadcrumb at top showing progress.

**Step 1 — Install cloudflared:**
- "Check Installation" button calls POST `/settings/tunnel/install-check`.
- Displays three-check results: binary on PATH (pass/fail), version (with warning if below minimum), cert.pem exists.
- Shows platform-detected install command with copy button.
- "Next" enabled only when binary check passes.

**Step 2 — Authenticate:**
- "Authenticate with Cloudflare" button calls POST `/settings/tunnel/auth-start`.
- Displays the returned auth URL as a clickable link (opens in new tab).
- Polls POST `/settings/tunnel/auth-poll` every 2 seconds.
- On success (cert.pem detected), shows green checkmark and auto-advances to step 3.
- Skip button if already authenticated (cert.pem check from step 1).

**Step 3 — Create Tunnel + Set Hostname:**
- Text input for tunnel name.
- Text input for hostname (e.g., `agent.mydomain.com`).
- "Create Tunnel" button calls POST `/settings/tunnel/create`.
- On success, automatically calls POST `/settings/tunnel/route-dns`.
- If DNS routing fails, displays manual CNAME instructions with the exact `<tunnel-uuid>.cfargotunnel.com` value and a "I've added the CNAME record" confirmation button.
- "Next" enabled after tunnel creation succeeds.

**Step 4 — Configure Protocol/Origin:**
- Select for protocol (http2/quic, default http2).
- Text input for origin URL (default `http://localhost:8000`).
- Optional IP allowlist CIDR input.
- "Next" button saves configuration.

**Step 5 — Review + Activate:**
- Summary of all configured values.
- Prominent security warning: "You are about to expose this application to the internet. Ensure authentication is properly configured."
- "Activate Tunnel" button calls POST `/settings/tunnel/start`.
- On success, redirects to the Settings > Tunnel page.

### 6.4 Create `TunnelStatusCard.vue` Dashboard Component

**File:** `resources/js/Components/Dashboard/TunnelStatusCard.vue` (new)

- Card component using the existing `Card`, `CardHeader`, `CardTitle`, `CardContent` UI components.
- Polls `GET /api/tunnel/status` every 5 seconds using `setInterval` + `axios.get()`. Cleans up interval on unmount.
- Displays:
  - Status badge (active/stopped/error/unconfigured) with color coding.
  - Hostname as clickable `https://` link (when active).
  - Uptime (formatted as `Xh Ym`).
  - Active connections count.
  - Version warning (yellow badge, if applicable).
  - Quick start/stop toggle button (POST to start/stop endpoints).
- Card is only rendered when tunnel feature flag is enabled AND tunnel is configured (status != unconfigured). When unconfigured but flag enabled, shows a "Set up Tunnel" CTA linking to the wizard.

### 6.5 Mount `TunnelStatusCard` on Dashboard

**File:** `resources/js/Pages/Dashboard.vue`

- Import `TunnelStatusCard` component.
- Conditionally render it in the dashboard grid when `$page.props.tunnelEnabled` is true:
  ```html
  <TunnelStatusCard v-if="$page.props.tunnelEnabled" />
  ```
- Place after existing dashboard cards in the layout grid.

**Acceptance check:** When tunnel flag is enabled, the status card appears on the Dashboard. When disabled, no card. Card correctly polls and updates every 5 seconds. Start/stop toggle works from the dashboard.

---

## Section 7: Testing

### 7.1 Unit Tests

**Directory:** `tests/Unit/Services/Tunnel/`

- `CloudflaredServiceTest.php`:
  - Test `isBinaryInstalled()` with mocked Process (binary found, binary not found).
  - Test `getVersion()` parses various cloudflared version output formats.
  - Test `isVersionCompatible()` with versions above, below, and equal to MIN_VERSION.
  - Test `validateCredentials()` three-check matrix (all pass, binary missing, cert missing, tunnel list fails).
  - Test `startLogin()` parses auth URL from mocked stdout.
  - Test `createTunnel()` parses UUID from mocked JSON output.
  - Test `deleteTunnel()` success and failure paths.
  - Test `buildRunCommand()` constructs correct arguments, generates ingress YAML with IP allowlist.
  - Test `routeDns()` success path and failure with manual instructions.
  - Test `generateAccessServiceToken()` parses client ID and secret.

- `CloudflaredInstallerTest.php`:
  - Test platform detection for macOS and Linux variants.
  - Test install command generation per platform.

- `TunnelHealthMonitorTest.php`:
  - Test `fetchMetrics()` parses Prometheus text format correctly.
  - Test `checkProcessStatus()` with running and dead process.
  - Test `getHealth()` aggregation.

- `TunnelSettingsRepositoryTest.php`:
  - Test single-row creation and retrieval.
  - Test config default merging for missing keys.
  - Test status updates.
  - Test JSON settings partial updates (merge, not replace).

- `TunnelIpAllowlistMiddlewareTest.php`:
  - Test CIDR matching: exact IP, subnet match, no match, IPv4.
  - Test empty allowlist passthrough.
  - Test CF-Connecting-IP header reading.
  - Test request->ip() fallback.
  - Test middleware skips when tunnel status is not active.
  - Test 403 response for blocked IPs.

### 7.2 Feature Tests

**Directory:** `tests/Feature/Tunnel/`

- `TunnelSettingsPageTest.php`:
  - Test settings page loads when feature flag enabled (200 response, correct Inertia component).
  - Test settings page returns 404 when feature flag disabled.
  - Test settings update POST validates and persists.
  - Test IP allowlist CIDR validation (valid/invalid ranges).

- `TunnelWizardFlowTest.php`:
  - Test wizard page loads when feature flag enabled.
  - Test install-check endpoint returns validation results.
  - Test auth-start endpoint returns auth URL.
  - Test auth-poll endpoint returns authentication status.
  - Test create endpoint creates tunnel and stores UUID.
  - Test route-dns endpoint success and failure responses.
  - Test start endpoint dispatches TunnelRunJob.
  - Test stop endpoint updates status.
  - Test delete endpoint clears settings.

- `TunnelAccessTokenTest.php`:
  - Test access-token generation stores credentials.
  - Test subsequent calls do not re-expose secret.

- `TunnelStatusApiTest.php`:
  - Test `/api/tunnel/status` returns health JSON when flag enabled.
  - Test returns 404 when flag disabled.
  - Test response structure matches expected schema.

- `TunnelFeatureGateTest.php`:
  - Test all tunnel routes return 404 when flag disabled.
  - Test all tunnel routes accessible when flag enabled and authenticated.

---

## Section 8: Graceful Degradation Verification

### 8.1 Zero-Error Validation

- Verify that when `tunnel.enabled` flag is `false` (default):
  - No tunnel-related entries appear in sidebar navigation.
  - No TunnelStatusCard renders on the Dashboard.
  - No tunnel routes are accessible (all return 404 via feature gate).
  - No tunnel-related errors appear in `storage/logs/laravel.log`.
  - Horizon supervisor-tunnel exists in config but processes no jobs (empty queue).
  - The `tunnel_settings` table migration runs cleanly but the row remains `unconfigured`.

- Verify that when cloudflared is not installed:
  - The settings page (when flag is enabled) shows clear "Not Installed" status.
  - The wizard step 1 shows install instructions.
  - No PHP errors or exceptions from missing binary.
  - `TunnelHealthMonitor` returns graceful "unavailable" state.

### 8.2 `TunnelIpAllowlistMiddleware` Global Safety

- When registered globally but tunnel is not active, the middleware is a no-op (zero performance impact).
- When tunnel is active but allowlist is empty, the middleware is a no-op.
- Only enforces when tunnel status is `active` AND `ip_allowlist` is non-empty.

---

## Dependency Order

1. **Section 1** (Foundation) must complete first — all subsequent sections depend on the config, migration, model, repository, feature flag, Horizon entry, and Inertia shared prop.
2. **Section 2** (CloudflaredService, Installer, HealthMonitor) depends on Section 1 (uses TunnelSettingsRepository and config).
3. **Section 3** (Artisan Commands, TunnelRunJob) depends on Sections 1 and 2 (uses services and repository).
4. **Section 4** (Middleware) depends on Section 1 (uses FeatureFlagManager and TunnelSettingsRepository).
5. **Section 5** (Controllers and Routes) depends on Sections 1, 2, 3, and 4 (uses all services, dispatches jobs, applies middleware).
6. **Section 6** (Frontend) depends on Section 5 (routes must exist for navigation and API calls).
7. **Section 7** (Testing) depends on all prior sections. Unit tests for Sections 1-4 can be written alongside those sections. Feature tests for Section 5 require routes to be registered. Frontend behavior tests require Section 6.
8. **Section 8** (Graceful Degradation) is a verification pass after all other sections are complete.

## Sections

- Section 1: Foundation — Config, Migration, Repository, and Feature Flag
- Section 2: CloudflaredService — CLI Wrapper
- Section 3: Artisan Commands
- Section 4: Middleware and Security
- Section 5: Controllers and API Routes
- Section 6: Frontend — Vue Components and Pages
- Section 7: Testing
- Section 8: Graceful Degradation Verification


## Risks

- Long-lived Horizon job (timeout=0) for cloudflared tunnel run is atypical for Laravel queue workers — Horizon may not handle perpetual processes gracefully, requiring careful testing of the process supervision lifecycle and potential fallback to a dedicated systemd/launchd service
- Parsing cloudflared CLI stdout for the auth URL during tunnel login is fragile — output format may change between cloudflared versions, requiring regex maintenance and fallback handling
- The single-row tunnel_settings table pattern with JSON column could hit race conditions if the UI sends concurrent update requests (e.g., start + update settings simultaneously) — needs optimistic locking or mutex
- Prometheus metrics endpoint parsing (localhost:20241/metrics) depends on cloudflared exposing this endpoint by default — some cloudflared configurations or versions may not enable metrics, causing health monitor failures
- Cloudflare Access service token generation via cloudflared access CLI commands may require additional authentication or API permissions beyond what cert.pem provides — needs validation against current cloudflared versions
- IP allowlist dual-layer enforcement relies on CF-Connecting-IP header being set by Cloudflare — direct access bypassing the tunnel (e.g., local network) would not have this header, potentially allowing the request->ip() fallback to match incorrectly
- DNS route creation via cloudflared tunnel route dns requires the domain to be on the same Cloudflare account as the tunnel credentials — mismatched accounts will fail silently or with unclear errors
- The 5-second polling interval for the dashboard status card creates sustained HTTP request load — if multiple browser tabs are open or multiple users are viewing the dashboard, this compounds


## Assumptions

- The existing Laravel Process facade (Illuminate\Process) is available and is the standard pattern for shell command execution in this codebase
- The AppSidebar.vue Settings group is the correct placement for the Tunnel nav item, matching the existing pattern for feature-gated sidebar entries (delegationEnabled, orgLayerEnabled)
- HandleInertiaRequests.php is the single shared props source for all Inertia pages — adding tunnelEnabled here makes it available to all Vue components via page.props
- The existing ErrorEnvelope pattern used by OrgFeatureGate is the correct pattern for feature-gated 404 responses in this codebase
- cloudflared tunnel list --output json and cloudflared tunnel create --output json produce parseable JSON on all supported platforms and versions >= MIN_VERSION
- The Dashboard.vue page supports adding additional card components in its grid layout without structural changes
- Routes in routes/web.php follow the pattern of inline closures or controller references within the authenticated middleware group — no separate route file needed for tunnel routes
- The TunnelRunJob long-lived process pattern can be achieved by using Process::forever() within a Horizon-managed job, similar to how other long-running processes are handled in the codebase
- Redis is available for storing tunnel restart attempt timestamps for the 5-in-10-minute crash tracking window
- The project uses lucide-vue-next for all sidebar and UI icons — Globe icon is available in the installed version

