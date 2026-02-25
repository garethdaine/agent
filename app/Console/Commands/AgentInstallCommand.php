<?php

namespace App\Console\Commands;

use App\Models\ConnectorAccount;
use App\Support\Messenger\ConnectorManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class AgentInstallCommand extends Command
{
    protected $signature = 'agent:install
        {--connector=* : Providers to configure (slack, telegram)}
        {--mode=local : Ingress mode (local, webhook)}
        {--non-interactive : Fail on missing required values}
        {--skip-migrations : Skip running migrations}
        {--skip-health-check : Skip final health check}';

    protected $description = 'Install and configure the Agent messenger control plane';

    private const REQUIRED_PHP_VERSION = '8.2.0';

    private const REQUIRED_EXTENSIONS = [
        'redis',
        'pcntl',
        'pdo',
        'openssl',
        'mbstring',
        'json',
    ];

    private const SUPPORTED_CONNECTORS = [
        'slack',
        'telegram',
    ];

    private const CONNECTOR_CREDENTIALS = [
        'slack' => [
            'bot_token' => 'Slack Bot Token (xoxb-...)',
            'signing_secret' => 'Slack Signing Secret',
            'app_token' => 'Slack App Token (xapp-... for Socket Mode, optional)',
        ],
        'telegram' => [
            'bot_token' => 'Telegram Bot Token',
        ],
    ];

    public function handle(ConnectorManager $connectorManager): int
    {
        $this->info('=== Agent Messenger Control Plane Installation ===');
        $this->newLine();

        // Step 1: Preflight Checks
        $this->info('[1/6] Running preflight checks...');

        if (! $this->runPreflightChecks()) {
            $this->error('Preflight checks failed. Please fix the issues above and try again.');

            return self::FAILURE;
        }

        $this->info('All preflight checks passed.');
        $this->newLine();

        // Step 2: Configure Connectors
        $this->info('[2/6] Configuring connectors...');
        $connectors = $this->option('connector');

        if (empty($connectors)) {
            if ($this->option('non-interactive')) {
                $this->error('No connectors specified. Use --connector=slack,telegram');

                return self::FAILURE;
            }

            $connectors = $this->selectConnectors();
        }

        $connectors = $this->normalizeConnectors($connectors);

        if (empty($connectors)) {
            $this->warn('No connectors selected. Skipping connector configuration.');
        } else {
            foreach ($connectors as $connector) {
                if (! $this->configureConnector($connector, $connectorManager)) {
                    $this->error(sprintf('Failed to configure %s connector.', ucfirst($connector)));

                    return self::FAILURE;
                }
            }
        }

        $this->newLine();

        // Step 3: Configure Ingress Mode
        $this->info('[3/6] Configuring ingress mode...');
        $mode = $this->option('mode');

        if ($this->configureIngressMode($mode)) {
            $this->info(sprintf('Ingress mode set to: %s', $mode));
        }

        $this->newLine();

        // Step 4: Create Runtime Config
        $this->info('[4/6] Creating runtime configuration...');
        $this->createRuntimeConfig($mode);
        $this->info('Runtime configuration updated.');
        $this->newLine();

        // Step 5: Run Migrations
        $this->info('[5/6] Running migrations...');

        if ($this->option('skip-migrations')) {
            $this->warn('Skipping migrations (--skip-migrations).');
        } else {
            $exitCode = Artisan::call('migrate', ['--force' => true], $this->output);

            if ($exitCode !== 0) {
                $this->error('Migrations failed.');

                return self::FAILURE;
            }

            $this->info('Migrations completed successfully.');
        }

        $this->newLine();

        // Step 6: Health Check
        $this->info('[6/6] Running health check...');

        if ($this->option('skip-health-check')) {
            $this->warn('Skipping health check (--skip-health-check).');
        } else {
            $healthResults = $this->runHealthCheck($connectors);
            $this->displayHealthCheckResults($healthResults);
        }

        $this->newLine();
        $this->info('=== Installation Complete ===');
        $this->newLine();

        $this->printNextSteps();

        return self::SUCCESS;
    }

    private function runPreflightChecks(): bool
    {
        $allPassed = true;

        // PHP Version Check
        $phpVersion = PHP_VERSION;
        $phpPassed = version_compare($phpVersion, self::REQUIRED_PHP_VERSION, '>=');
        $this->printCheckResult(
            sprintf('PHP Version (>= %s)', self::REQUIRED_PHP_VERSION),
            $phpPassed,
            $phpPassed ? $phpVersion : sprintf('Found %s', $phpVersion)
        );
        $allPassed = $allPassed && $phpPassed;

        // Required Extensions Check
        foreach (self::REQUIRED_EXTENSIONS as $extension) {
            $loaded = extension_loaded($extension);
            $this->printCheckResult(
                sprintf('PHP Extension: %s', $extension),
                $loaded,
                $loaded ? 'Loaded' : 'Not loaded'
            );
            $allPassed = $allPassed && $loaded;
        }

        // Redis Connectivity
        $redisPassed = $this->checkRedisConnectivity();
        $this->printCheckResult(
            'Redis Connectivity',
            $redisPassed,
            $redisPassed ? 'Connected' : 'Connection failed'
        );
        $allPassed = $allPassed && $redisPassed;

        // Database Connectivity
        $dbPassed = $this->checkDatabaseConnectivity();
        $this->printCheckResult(
            'Database Connectivity',
            $dbPassed,
            $dbPassed ? 'Connected' : 'Connection failed'
        );
        $allPassed = $allPassed && $dbPassed;

        // Writable Storage Directories
        $storageDirectories = [
            storage_path('app'),
            storage_path('logs'),
            storage_path('framework/cache'),
            storage_path('framework/sessions'),
            storage_path('framework/views'),
        ];

        foreach ($storageDirectories as $directory) {
            $writable = is_dir($directory) && is_writable($directory);
            $shortPath = str_replace(base_path().'/', '', $directory);
            $this->printCheckResult(
                sprintf('Writable: %s', $shortPath),
                $writable,
                $writable ? 'Writable' : 'Not writable or missing'
            );
            $allPassed = $allPassed && $writable;
        }

        // Node.js availability (optional)
        $nodeResult = Process::run('node --version 2>/dev/null');
        $nodeAvailable = $nodeResult->successful();
        $this->printCheckResult(
            'Node.js (optional)',
            $nodeAvailable,
            $nodeAvailable ? trim($nodeResult->output()) : 'Not found (Vite dev server unavailable)',
            warn: ! $nodeAvailable
        );

        return $allPassed;
    }

    private function printCheckResult(string $label, bool $passed, string $details, bool $warn = false): void
    {
        $icon = $passed ? '<fg=green>[OK]</>' : ($warn ? '<fg=yellow>[WARN]</>' : '<fg=red>[FAIL]</>');
        $this->line(sprintf('  %s %s - %s', $icon, $label, $details));
    }

    private function checkRedisConnectivity(): bool
    {
        try {
            Redis::ping();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function checkDatabaseConnectivity(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array<string>
     */
    private function selectConnectors(): array
    {
        $selected = [];

        foreach (self::SUPPORTED_CONNECTORS as $connector) {
            if (confirm(
                label: sprintf('Configure %s connector?', ucfirst($connector)),
                default: false
            )) {
                $selected[] = $connector;
            }
        }

        return $selected;
    }

    /**
     * @param  array<string>|string  $connectors
     * @return array<string>
     */
    private function normalizeConnectors(array|string $connectors): array
    {
        if (is_string($connectors)) {
            $connectors = explode(',', $connectors);
        }

        // Flatten nested arrays (from multiple --connector flags)
        $flattened = [];
        foreach ($connectors as $connector) {
            if (str_contains($connector, ',')) {
                $flattened = array_merge($flattened, explode(',', $connector));
            } else {
                $flattened[] = $connector;
            }
        }

        return array_values(array_filter(
            array_map('trim', array_map('strtolower', $flattened)),
            fn (string $c): bool => in_array($c, self::SUPPORTED_CONNECTORS, true)
        ));
    }

    private function configureConnector(string $connector, ConnectorManager $connectorManager): bool
    {
        $this->newLine();
        $this->info(sprintf('Configuring %s connector...', ucfirst($connector)));

        $credentialSchema = self::CONNECTOR_CREDENTIALS[$connector] ?? [];
        $credentials = [];

        foreach ($credentialSchema as $key => $label) {
            $isOptional = str_contains(strtolower($label), 'optional');

            if ($this->option('non-interactive')) {
                $envKey = sprintf('MESSENGER_%s_%s', strtoupper($connector), strtoupper($key));
                $value = env($envKey, '');

                if ($value === '' && ! $isOptional) {
                    $this->error(sprintf('Required credential %s not set in environment (%s)', $label, $envKey));

                    return false;
                }

                $credentials[$key] = $value;
            } else {
                $value = password(
                    label: $label.($isOptional ? ' (press Enter to skip)' : ''),
                    required: ! $isOptional,
                );

                if ($value !== '' || ! $isOptional) {
                    $credentials[$key] = $value;
                }
            }
        }

        // Validate credentials with provider API
        $this->line('  Validating credentials...');

        $validationResult = $this->validateProviderCredentials($connector, $credentials);

        if (! $validationResult['valid']) {
            $this->error(sprintf('  Credential validation failed: %s', $validationResult['error']));

            if (! $this->option('non-interactive')) {
                if (! confirm('Continue anyway?', default: false)) {
                    return false;
                }
            } else {
                return false;
            }
        } else {
            $this->info(sprintf('  Credentials validated: %s', $validationResult['details']));
        }

        // Prompt for account name
        $name = $this->option('non-interactive')
            ? sprintf('%s Bot', ucfirst($connector))
            : text(
                label: sprintf('Enter a name for this %s connection', ucfirst($connector)),
                default: $validationResult['suggested_name'] ?? sprintf('%s Bot', ucfirst($connector)),
                required: true,
            );

        // Store connector account
        $mode = $this->option('mode');

        $accountKey = $this->generateAccountKey($connector, $credentials);

        $existingAccount = ConnectorAccount::where('provider', $connector)
            ->where('account_key', $accountKey)
            ->first();

        if ($existingAccount) {
            $this->warn(sprintf('  Updating existing %s connector account...', ucfirst($connector)));
            $existingAccount->update([
                'name' => $name,
                'credentials' => $credentials,
                'connection_mode' => $mode,
                'status' => ConnectorAccount::STATUS_DISCONNECTED,
                'config' => array_merge(
                    config(sprintf('messenger.providers.%s', $connector), []),
                    ['configured_at' => now()->toIso8601String()]
                ),
            ]);
            $this->info(sprintf('  %s connector updated successfully.', ucfirst($connector)));
        } else {
            ConnectorAccount::create([
                'provider' => $connector,
                'name' => $name,
                'credentials' => $credentials,
                'connection_mode' => $mode,
                'status' => ConnectorAccount::STATUS_DISCONNECTED,
                'account_key' => $accountKey,
                'config' => array_merge(
                    config(sprintf('messenger.providers.%s', $connector), []),
                    ['configured_at' => now()->toIso8601String()]
                ),
            ]);
            $this->info(sprintf('  %s connector configured successfully.', ucfirst($connector)));
        }

        return true;
    }

    /**
     * @param  array<string, string>  $credentials
     * @return array{valid: bool, error: ?string, details: ?string, suggested_name: ?string}
     */
    private function validateProviderCredentials(string $connector, array $credentials): array
    {
        try {
            return match ($connector) {
                'slack' => $this->validateSlackCredentials($credentials),
                'telegram' => $this->validateTelegramCredentials($credentials),
                default => ['valid' => true, 'error' => null, 'details' => 'Validation skipped', 'suggested_name' => null],
            };
        } catch (\Throwable $e) {
            return [
                'valid' => false,
                'error' => sprintf('Validation error: %s', $e->getMessage()),
                'details' => null,
                'suggested_name' => null,
            ];
        }
    }

    /**
     * @param  array<string, string>  $credentials
     * @return array{valid: bool, error: ?string, details: ?string, suggested_name: ?string}
     */
    private function validateSlackCredentials(array $credentials): array
    {
        $botToken = $credentials['bot_token'] ?? '';

        if ($botToken === '') {
            return [
                'valid' => false,
                'error' => 'Bot token is required',
                'details' => null,
                'suggested_name' => null,
            ];
        }

        $response = Http::withToken($botToken)
            ->timeout(10)
            ->get('https://slack.com/api/auth.test');

        if (! $response->successful()) {
            return [
                'valid' => false,
                'error' => 'Failed to reach Slack API',
                'details' => null,
                'suggested_name' => null,
            ];
        }

        $data = $response->json();

        if (! ($data['ok'] ?? false)) {
            return [
                'valid' => false,
                'error' => $data['error'] ?? 'Unknown Slack API error',
                'details' => null,
                'suggested_name' => null,
            ];
        }

        return [
            'valid' => true,
            'error' => null,
            'details' => sprintf('Bot: %s, Team: %s', $data['user'] ?? 'unknown', $data['team'] ?? 'unknown'),
            'suggested_name' => $data['team'] ?? null,
        ];
    }

    /**
     * @param  array<string, string>  $credentials
     * @return array{valid: bool, error: ?string, details: ?string, suggested_name: ?string}
     */
    private function validateTelegramCredentials(array $credentials): array
    {
        $botToken = $credentials['bot_token'] ?? '';

        if ($botToken === '') {
            return [
                'valid' => false,
                'error' => 'Bot token is required',
                'details' => null,
                'suggested_name' => null,
            ];
        }

        $response = Http::timeout(10)
            ->get(sprintf('https://api.telegram.org/bot%s/getMe', $botToken));

        if (! $response->successful()) {
            return [
                'valid' => false,
                'error' => 'Failed to reach Telegram API',
                'details' => null,
                'suggested_name' => null,
            ];
        }

        $data = $response->json();

        if (! ($data['ok'] ?? false)) {
            return [
                'valid' => false,
                'error' => $data['description'] ?? 'Unknown Telegram API error',
                'details' => null,
                'suggested_name' => null,
            ];
        }

        $result = $data['result'] ?? [];

        return [
            'valid' => true,
            'error' => null,
            'details' => sprintf('Bot: @%s (%s)', $result['username'] ?? 'unknown', $result['first_name'] ?? 'unknown'),
            'suggested_name' => $result['first_name'] ?? null,
        ];
    }

    /**
     * @param  array<string, string>  $credentials
     */
    private function generateAccountKey(string $connector, array $credentials): string
    {
        // Generate a deterministic key based on the primary identifier
        $identifier = match ($connector) {
            'slack' => $credentials['bot_token'] ?? '',
            'telegram' => $credentials['bot_token'] ?? '',
            default => Str::random(32),
        };

        // Hash to avoid exposing token in database
        return hash('sha256', sprintf('%s:%s', $connector, $identifier));
    }

    private function configureIngressMode(string $mode): bool
    {
        if ($mode === 'webhook') {
            $this->warn('Webhook mode requires publicly accessible endpoints.');

            if (! $this->option('non-interactive')) {
                $domain = text(
                    label: 'Enter your public domain (e.g., agent.example.com)',
                    required: true,
                    validate: function (string $value): ?string {
                        if (! filter_var('https://'.$value, FILTER_VALIDATE_URL)) {
                            return 'Please enter a valid domain name.';
                        }

                        return null;
                    }
                );

                $this->info(sprintf('Webhook URLs will be available at:'));
                $this->line(sprintf('  - Slack:    https://%s/agent/api/v1/connectors/slack/webhook', $domain));
                $this->line(sprintf('  - Telegram: https://%s/agent/api/v1/connectors/telegram/webhook', $domain));

                // Verify TLS if possible
                $this->line('  Checking TLS...');
                $tlsCheck = Http::timeout(5)->get(sprintf('https://%s', $domain));

                if ($tlsCheck->successful() || $tlsCheck->status() === 404) {
                    $this->info('  TLS verification passed.');
                } else {
                    $this->warn('  Could not verify TLS. Ensure HTTPS is properly configured.');
                }
            }
        } else {
            $this->info('Local connector mode - no public endpoints required.');
            $this->line('  Slack will use Socket Mode (requires App Token).');
            $this->line('  Telegram will use Long Polling.');
        }

        return true;
    }

    private function createRuntimeConfig(string $mode): void
    {
        // Update config values that might need to be persisted
        // Note: In a real scenario, this might write to .env or a config cache

        $configUpdates = [
            'MESSENGER_DEFAULT_MODE' => $mode,
        ];

        $this->line('  Runtime configuration:');

        foreach ($configUpdates as $key => $value) {
            $this->line(sprintf('    %s=%s', $key, $value));
        }

        $this->line('');
        $this->line('  Add these to your .env file if not already present.');
    }

    /**
     * @param  array<string>  $connectors
     * @return array<string, array{status: string, details: string}>
     */
    private function runHealthCheck(array $connectors): array
    {
        $results = [];

        // Check core services
        $results['Database'] = $this->checkDatabaseConnectivity()
            ? ['status' => 'ok', 'details' => 'Connected']
            : ['status' => 'error', 'details' => 'Connection failed'];

        $results['Redis'] = $this->checkRedisConnectivity()
            ? ['status' => 'ok', 'details' => 'Connected']
            : ['status' => 'error', 'details' => 'Connection failed'];

        // Check Horizon
        $horizonResult = Process::run('php artisan horizon:status 2>/dev/null');
        $results['Horizon'] = $horizonResult->successful()
            ? ['status' => 'ok', 'details' => trim($horizonResult->output())]
            : ['status' => 'warn', 'details' => 'Not running (run php artisan horizon)'];

        // Check configured connectors
        foreach ($connectors as $connector) {
            $account = ConnectorAccount::where('provider', $connector)
                ->latest()
                ->first();

            if ($account) {
                $results[ucfirst($connector).' Connector'] = [
                    'status' => 'ok',
                    'details' => sprintf('Configured: %s (%s mode)', $account->name, $account->connection_mode),
                ];
            } else {
                $results[ucfirst($connector).' Connector'] = [
                    'status' => 'error',
                    'details' => 'Not configured',
                ];
            }
        }

        return $results;
    }

    /**
     * @param  array<string, array{status: string, details: string}>  $results
     */
    private function displayHealthCheckResults(array $results): void
    {
        foreach ($results as $service => $result) {
            $icon = match ($result['status']) {
                'ok' => '<fg=green>[OK]</>',
                'warn' => '<fg=yellow>[WARN]</>',
                default => '<fg=red>[ERROR]</>',
            };

            $this->line(sprintf('  %s %s - %s', $icon, $service, $result['details']));
        }
    }

    private function printNextSteps(): void
    {
        $this->info('Next steps:');
        $this->line('');
        $this->line('  1. Start the runtime services:');
        $this->line('     php artisan agent:restart');
        $this->line('');
        $this->line('  2. Monitor the services:');
        $this->line('     php artisan horizon');
        $this->line('');
        $this->line('  3. Test your connectors:');
        $this->line('     - Send a message to your bot');
        $this->line('     - Check the logs: tail -f storage/logs/laravel.log');
        $this->line('');
    }
}
