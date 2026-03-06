# Requirements Discovery Summary

Session: 2

## Cloudflare Tunnel Integration — Feature Requirements Summary

### Overview
Add a fully UI-managed Cloudflare Tunnel (cloudflared) integration to Agent Scheduler, enabling solo operators and small-team admins to securely expose their local instance to the internet at a custom hostname without CLI knowledge beyond initial cloudflared installation.

### Resolved Architecture Decisions

**Process Supervision: Horizon Supervisor**
The tunnel process runs as a dedicated Horizon supervisor entry (`supervisor-tunnel`) in `config/horizon.php`, consistent with existing `supervisor-memory-working` and `supervisor-memory-formation` patterns. A new `TunnelRunJob` (or equivalent long-lived job) wraps the `cloudflared tunnel run` command. The Artisan command `php artisan tunnel:run` serves as the entry point, dispatchable by Horizon.

**Crash Restart Policy: Max 5 Attempts in 10 Minutes**
When the cloudflared process crashes, Horizon retries up to 5 times within a 10-minute window. If all 5 attempts fail, the tunnel status is set to `error` in the database and retrying stops. The user must manually re-start from the UI or CLI after investigating the failure. The Horizon supervisor config uses `tries => 5` with `backoff => [10, 30, 60, 120, 300]`.

**Config Persistence: Database-Primary with JSON Column**
A new `config/tunnel.php` provides defaults only. Runtime tunnel settings are stored in a single-row `tunnel_settings` database table using a JSON `settings` column for easy future extensibility. The JSON column stores: `tunnel_name` (string), `tunnel_uuid` (string/uuid), `hostname` (string, e.g. `agent.mydomain.com`), `origin_url` (string, default `http://localhost:8000`), `protocol` (enum: `http2`, `quic`), `ip_allowlist` (array, nullable), `cloudflare_access_enabled` (boolean, default false), `access_client_id` (string, nullable), `access_client_secret` (string, nullable). A separate `status` column (enum: `active`, `stopped`, `error`, `unconfigured`) is stored outside JSON for efficient querying. This single-row design simplifies all read/write paths while the JSON structure allows schema extension without migrations.

**Feature Gating: FeatureFlagManager**
A new `tunnel` feature flag is registered in `FeatureFlagManager`. The tunnel UI (sidebar nav item, dashboard card, settings page) is only rendered when the flag is enabled. The flag is an explicit opt-in toggle in Settings, not auto-detected. When disabled, zero tunnel-related code executes and no errors surface.

**Credential Validation: File Check + CLI Verification**
Validate cloudflared auth by: (1) checking `cloudflared` binary is on PATH, (2) checking `~/.cloudflared/cert.pem` exists, (3) executing `cloudflared tunnel list` to confirm credentials are functional. All three checks surface in the setup wizard and settings page with clear status indicators.

**Version Compatibility: Warn Only**
The app parses output from `cloudflared version` and compares against a known minimum version constant stored in `CloudflaredService::MIN_VERSION`. If the installed version is below the minimum, a non-blocking warning is displayed in the setup wizard, settings page, and dashboard status card. All operations remain allowed regardless of version.

**OAuth Login Flow: Background Process with Parsed Auth URL**
When the user clicks "Authenticate with Cloudflare" in the setup wizard, the app runs `cloudflared tunnel login` as a background process, parses the authorization URL from stdout, and displays it as a clickable link in the wizard UI. The wizard polls for `~/.cloudflared/cert.pem` to detect successful authentication completion and advances to the next step automatically.

**Platform Support: macOS + Linux**
v1 install helper supports macOS (`brew install cloudflared`) and Linux (`apt-get`, `yum`, or direct binary download). Platform detection logic in a `CloudflaredInstaller` service determines the host OS and provides the appropriate install command. An Artisan command `php artisan tunnel:install` and an in-app button both trigger installation guidance and verification.

**DNS Management: Automatic with Manual Fallback**
The app wraps `cloudflared tunnel route dns <tunnel-uuid> <hostname>` to automatically create/update the CNAME record. If the command fails (permissions, existing conflicting record), the UI shows manual fallback instructions with the exact CNAME value to add in Cloudflare DNS.

**Health Monitoring: Process Status + Metrics Endpoint**
Tunnel health is checked via: (1) process status (is the cloudflared process alive, exit code), (2) parsing the cloudflared metrics endpoint (default `http://localhost:20241/metrics` — Prometheus format) for connection count, tunnel status, and error rates. Health data is exposed via an API endpoint for the dashboard widget. The dashboard TunnelStatusCard polls at a **5-second interval** for near-realtime updates.

**Security Safeguards: Warning Banner + Dual-Layer IP Allowlist**
Before tunnel activation, a prominent warning banner informs users that public exposure requires authentication to be properly configured. An optional IP allowlist field in tunnel settings restricts access to specified CIDR ranges. IP allowlist enforcement uses **both layers**: cloudflared ingress rules as the primary enforcement (blocked IPs never reach Laravel) and a Laravel middleware (`TunnelIpAllowlistMiddleware`) as a defense-in-depth fallback reading from the `CF-Connecting-IP` header. HTTPS is enforced by default through Cloudflare's edge.

**Cloudflare Access Integration: Service Token Policy**
v1 includes an optional Cloudflare Access toggle in tunnel settings that generates a **service token** (client ID + client secret pair) via `cloudflared access` commands. When enabled, the app creates a service token Access policy for the tunnel hostname. The generated `CF-Access-Client-Id` and `CF-Access-Client-Secret` values are stored in the JSON settings column and displayed once in the UI for the user to copy. Clients accessing the tunnel must include these headers. This approach requires no external identity provider and supports both programmatic and browser-based access.

**Setup UX: Hybrid Wizard + Settings Page**
First-time setup uses a multi-step wizard: (1) Install cloudflared, (2) Authenticate — run `cloudflared tunnel login` in background, parse and display auth URL as clickable link, poll for cert.pem completion, (3) Create tunnel + set hostname, (4) Configure protocol/origin, (5) Review + activate. After initial setup, ongoing management uses the Settings > Tunnel page with status indicators, start/stop controls, and configuration editing.

**Dashboard Widget: Status Card**
A new `TunnelStatusCard.vue` component on the main Dashboard, visible when the `tunnel` feature flag is enabled. Displays: tunnel status (active/stopped/error), hostname with clickable link, uptime, connection count, version warning (if applicable), and a quick start/stop toggle. Polls `/api/tunnel/status` every 5 seconds.

**Testing: Mocked Process Layer**
All tunnel service tests mock the shell/process execution layer. Unit tests validate `CloudflaredService` logic (command construction, output parsing, state transitions, config validation, version comparison). Feature tests validate the controller/API layer, settings persistence, and wizard flow. No real cloudflared binary required in CI.

### Key Services and Classes

| Entity | Type | Responsibility |
|---|---|---|
| `CloudflaredService` | Service | Wraps all cloudflared CLI commands (version, install check, login, tunnel create/run/stop/delete, route dns, access); holds `MIN_VERSION` constant |
| `CloudflaredInstaller` | Service | Platform detection (macOS/Linux), install command generation, installation verification |
| `TunnelHealthMonitor` | Service | Process status checks, Prometheus metrics endpoint parsing (localhost:20241/metrics), health state aggregation |
| `TunnelSettingsRepository` | Repository | Single-row CRUD for tunnel_settings table, JSON settings read/write, config merging with config/tunnel.php defaults |
| `TunnelController` | Controller | Settings page, wizard steps, start/stop/delete actions |
| `TunnelStatusController` | API Controller | Health/status JSON endpoint for 5s dashboard polling |
| `TunnelRunJob` | Job | Long-lived Horizon job that executes `cloudflared tunnel run`, crash tracking for 5-attempt/10-minute limit |
| `TunnelIpAllowlistMiddleware` | Middleware | Defense-in-depth IP allowlist enforcement via CF-Connecting-IP header |
| `php artisan tunnel:run` | Command | Artisan entry point for Horizon supervisor |
| `php artisan tunnel:install` | Command | Interactive cloudflared installation helper with platform detection |
| `php artisan tunnel:status` | Command | CLI tunnel health check |
| `config/tunnel.php` | Config | Default values for all tunnel settings |
| `tunnel_settings` migration | Migration | Single-row table with JSON settings column and status enum column |
| `TunnelStatusCard.vue` | Component | Dashboard health/status card (5s polling) |
| `TunnelSettings.vue` | Page | Settings > Tunnel management page |
| `TunnelSetupWizard.vue` | Component | Multi-step first-time setup wizard (5 steps) |

### Database Schema: `tunnel_settings` Migration
```
id: bigIncrements (primary key)
settings: json (tunnel_name, tunnel_uuid, hostname, origin_url, protocol, ip_allowlist, cloudflare_access_enabled, access_client_id, access_client_secret)
status: string/enum (unconfigured, active, stopped, error) — default 'unconfigured'
created_at: timestamp
updated_at: timestamp
```

### Config File: `config/tunnel.php` Defaults
```php
return [
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

### Horizon Supervisor Entry
```php
'supervisor-tunnel' => [
    'connection' => 'redis',
    'queue' => ['tunnel'],
    'balance' => 'false',
    'maxProcesses' => 1,
    'tries' => 5,
    'backoff' => [10, 30, 60, 120, 300],
    'timeout' => 0, // long-lived process
],
```

### API Routes
- `GET /settings/tunnel` — Tunnel settings page (Inertia)
- `POST /settings/tunnel` — Update tunnel settings
- `POST /settings/tunnel/start` — Start tunnel
- `POST /settings/tunnel/stop` — Stop tunnel
- `POST /settings/tunnel/delete` — Delete tunnel
- `POST /settings/tunnel/install-check` — Verify cloudflared installation and version
- `POST /settings/tunnel/auth-check` — Verify cloudflared authentication
- `POST /settings/tunnel/auth-start` — Start cloudflared tunnel login, return parsed auth URL
- `POST /settings/tunnel/auth-poll` — Poll for cert.pem existence after login
- `POST /settings/tunnel/create` — Create new tunnel
- `POST /settings/tunnel/route-dns` — Configure DNS routing
- `POST /settings/tunnel/access-token` — Generate Cloudflare Access service token
- `GET /api/tunnel/status` — JSON health/status for dashboard polling (5s interval)
- `GET /settings/tunnel/wizard` — Setup wizard page (Inertia)

## Goals

- Implement a fully UI-managed Cloudflare Tunnel integration that allows users to expose their local Agent Scheduler instance to the internet at a custom hostname
- Create a CloudflaredService that wraps all cloudflared CLI commands (version check, install check, login with background process and auth URL parsing, tunnel create/run/stop/delete, route dns, access service-token) behind a testable service layer with a MIN_VERSION constant
- Build a hybrid setup UX: multi-step wizard for first-time configuration (5 steps: install, auth with clickable URL, create tunnel, configure, activate), Settings > Tunnel page for ongoing management
- Add a dedicated Horizon supervisor (supervisor-tunnel) to manage the long-lived cloudflared tunnel process with a 5-attempt/10-minute crash restart policy
- Store tunnel configuration in a single-row tunnel_settings table with a JSON settings column (including access_client_id and access_client_secret for Cloudflare Access service tokens) and a separate status enum column, with config/tunnel.php providing defaults only
- Register a 'tunnel' feature flag in FeatureFlagManager to gate all tunnel UI and functionality behind an explicit opt-in toggle
- Display tunnel health/status as a new TunnelStatusCard on the main Dashboard with 5-second polling of the cloudflared Prometheus metrics endpoint, including version warnings
- Support macOS (brew) and Linux (apt/yum/binary) platforms for cloudflared installation detection and guided install commands
- Automatically manage DNS CNAME records via cloudflared tunnel route dns with manual fallback instructions displayed on failure
- Implement dual-layer security: warning banner before activation, IP allowlist enforced at both cloudflared ingress rules (primary) and Laravel TunnelIpAllowlistMiddleware via CF-Connecting-IP (defense-in-depth)
- Implement Cloudflare Access integration using service token policy type — generate client ID/secret pair, store in JSON settings, display once for user to copy
- Validate cloudflared readiness via three checks: binary on PATH, ~/.cloudflared/cert.pem exists, and cloudflared tunnel list succeeds — each with distinct status in the UI
- Implement non-blocking version compatibility warning when cloudflared version is below MIN_VERSION constant
- Provide Artisan commands: tunnel:run (Horizon entry point), tunnel:install (platform-aware installation helper), tunnel:status (CLI health check with process and metrics data)
- Implement TunnelIpAllowlistMiddleware that reads CIDR allowlist from tunnel_settings JSON and validates CF-Connecting-IP header as defense-in-depth


## Constraints

- cloudflared must be installed on the host machine — the app does not bundle or auto-install the binary, only provides guided install commands for macOS and Linux
- No Cloudflare API tokens are stored by the app — authentication relies entirely on cloudflared's own credential file (~/.cloudflared/cert.pem)
- The tunnel feature must be entirely opt-in via FeatureFlagManager — when disabled, zero tunnel code executes and no errors surface in logs or UI
- Graceful degradation: if cloudflared is not installed or tunnel not configured, the app functions as a local-only install with no errors
- HTTPS is enforced by default through Cloudflare's edge — no option to disable
- Tunnel process runs within Horizon as a dedicated supervisor with maxProcesses=1, timeout=0, tries=5, and backoff=[10,30,60,120,300]
- Config persistence is database-primary using a single-row table with JSON settings column — must survive config:cache clears
- v1 platform support limited to macOS and Linux only (no native Windows support)
- All tunnel service tests must mock the shell/process layer — no real cloudflared binary required in CI
- The user must have a Cloudflare account and a domain/subdomain managed by Cloudflare DNS
- Tunnel settings page and wizard are gated behind existing authentication middleware — no public access to tunnel management
- The app must not require users to touch the terminal after initial cloudflared installation
- After 5 failed restart attempts within 10 minutes, the tunnel status must be set to 'error' and retrying must stop until manually re-initiated
- IP allowlist must be enforced at both cloudflared ingress (primary) and Laravel middleware (defense-in-depth) layers
- The OAuth login flow must run cloudflared tunnel login in background and parse the auth URL from stdout — never attempt to auto-open a browser
- Dashboard status card polling interval is fixed at 5 seconds
- Version compatibility checks are warn-only — never block operations based on cloudflared version
- Cloudflare Access service token credentials (client ID/secret) are stored in the JSON settings column and displayed once to the user — the app does not re-display the secret after initial generation


## Acceptance Criteria

- A 'tunnel' feature flag exists in FeatureFlagManager; toggling it off hides all tunnel UI (sidebar item, dashboard card, settings page) and prevents tunnel operations
- config/tunnel.php exists with default values for tunnel_name, tunnel_uuid, hostname, origin_url, protocol, cloudflared_binary, metrics_port, ip_allowlist, cloudflare_access_enabled, status_poll_interval, and min_version
- A tunnel_settings migration creates a single-row table with a JSON 'settings' column (storing tunnel_name, tunnel_uuid, hostname, origin_url, protocol, ip_allowlist, cloudflare_access_enabled, access_client_id, access_client_secret) and a separate 'status' enum column (unconfigured, active, stopped, error) defaulting to 'unconfigured'
- TunnelSettingsRepository implements single-row CRUD with JSON settings read/write, merging database values with config/tunnel.php defaults for any missing keys
- CloudflaredService correctly wraps: cloudflared version (with semver parsing and MIN_VERSION comparison), cloudflared tunnel login (background execution with stdout auth URL parsing), cloudflared tunnel create, cloudflared tunnel run, cloudflared tunnel delete, cloudflared tunnel list, cloudflared tunnel route dns, and cloudflared access service-token commands
- CloudflaredInstaller detects macOS vs Linux and returns the appropriate install command (brew install cloudflared, apt-get install cloudflared, yum install cloudflared, or direct binary download URL)
- Credential validation performs three sequential checks: binary on PATH, ~/.cloudflared/cert.pem exists, and cloudflared tunnel list succeeds — each with distinct pass/fail status in the UI
- Version check parses cloudflared version output, compares against CloudflaredService::MIN_VERSION, and returns a non-blocking warning if below minimum — all operations remain allowed
- The setup wizard step 2 (Authenticate) runs cloudflared tunnel login in background, parses the auth URL from stdout, displays it as a clickable link, and polls for cert.pem to detect completion and auto-advance
- The setup wizard guides users through 5 steps: (1) install cloudflared with version check, (2) authenticate with clickable auth URL, (3) create tunnel + set hostname, (4) configure protocol/origin, (5) review and activate
- Settings > Tunnel page shows current configuration, start/stop controls, status indicators, IP allowlist CIDR editor, Cloudflare Access toggle with service token display, and version warning if applicable
- Horizon config includes supervisor-tunnel with queue=['tunnel'], balance='false', maxProcesses=1, tries=5, backoff=[10,30,60,120,300], timeout=0
- TunnelRunJob tracks restart attempts; after 5 failed attempts within 10 minutes, tunnel status is set to 'error' in the database and retrying stops
- php artisan tunnel:run starts the cloudflared tunnel run process as a Horizon-managed long-lived job
- php artisan tunnel:install outputs platform-appropriate installation instructions, verifies installation status via the three-check validation, and warns if version is below minimum
- php artisan tunnel:status outputs tunnel health including process alive status, parsed cloudflared Prometheus metrics data, and version info
- TunnelStatusCard.vue renders on the Dashboard when the tunnel feature flag is enabled, showing status badge, hostname clickable link, uptime, connection count, version warning if applicable, and start/stop toggle
- TunnelStatusCard polls GET /api/tunnel/status every 5 seconds for live health data parsed from the cloudflared Prometheus metrics endpoint (localhost:20241/metrics)
- DNS routing is attempted automatically via cloudflared tunnel route dns; on failure, the UI displays manual CNAME instructions with the exact record value
- A warning banner is displayed before tunnel activation informing users that public exposure requires app authentication to be configured
- IP allowlist CIDR ranges are stored in the JSON settings column, enforced at the cloudflared ingress rules level (tunnel config YAML), and additionally enforced by TunnelIpAllowlistMiddleware reading CF-Connecting-IP header
- TunnelIpAllowlistMiddleware reads the allowlist from tunnel_settings, validates incoming CF-Connecting-IP against stored CIDR ranges, and returns 403 for non-matching IPs
- Cloudflare Access toggle generates a service token via cloudflared access commands, stores the CF-Access-Client-Id and CF-Access-Client-Secret in the JSON settings column, and displays credentials once for the user to copy
- POST /settings/tunnel/access-token generates and returns the service token credentials; the secret is shown only on this initial response
- POST /settings/tunnel/auth-start runs cloudflared tunnel login in background and returns the parsed auth URL; POST /settings/tunnel/auth-poll checks for cert.pem existence
- POST /settings/tunnel/install-check returns binary presence, version info, and version compatibility warning
- All CloudflaredService, TunnelHealthMonitor, and TunnelIpAllowlistMiddleware methods are unit-tested with mocked process/shell execution — no cloudflared binary required in CI
- Feature tests cover the TunnelController wizard flow (all 5 steps), settings CRUD, start/stop/delete actions, auth-start/auth-poll endpoints, access-token generation, and the /api/tunnel/status endpoint
- When the tunnel feature flag is disabled and cloudflared is not installed, no tunnel-related errors appear in logs, dashboard, or any UI surface

