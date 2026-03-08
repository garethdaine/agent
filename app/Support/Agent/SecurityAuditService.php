<?php

namespace App\Support\Agent;

use App\Models\Security\SecurityDetectionRule;
use App\Services\Security\SecurityConfigProvider;
use Illuminate\Support\Facades\Config;

class SecurityAuditService
{
    public const SEVERITY_CRITICAL = 'critical';

    public const SEVERITY_WARN = 'warn';

    public const SEVERITY_INFO = 'info';

    public function __construct(
        private ?SecurityConfigProvider $configProvider = null,
    ) {
        $this->configProvider ??= app(SecurityConfigProvider::class);
    }

    /**
     * Run security audit checks. Returns list of findings.
     *
     * @return array<int, array{check_id: string, severity: string, message: string, fix: string|null}>
     */
    public function run(): array
    {
        $findings = [];

        $this->checkAppDebug($findings);
        $this->checkRuntimeDefaultMode($findings);
        $this->checkToolPolicy($findings);
        $this->checkLoggingRedaction($findings);
        $this->checkSessionTimeout($findings);

        $this->checkContentTrustActive($findings);
        $this->checkInjectionDetectionActive($findings);
        $this->checkExfiltrationDetectionActive($findings);
        $this->checkDefaultDenyDisabled($findings);
        $this->checkStripHtmlDisabled($findings);
        $this->checkInjectionThresholdHigh($findings);
        $this->checkDetectionRulesCount($findings);
        $this->checkMessengerRateLimitHigh($findings);
        $this->checkPromptIsolationDisabled($findings);
        $this->checkImmutableConfigIntact($findings);

        return $findings;
    }

    /**
     * @param  array<int, array{check_id: string, severity: string, message: string, fix: string|null}>  $findings
     */
    private function checkAppDebug(array &$findings): void
    {
        if (Config::get('app.debug')) {
            $findings[] = [
                'check_id' => 'app.debug_enabled',
                'severity' => self::SEVERITY_WARN,
                'message' => 'Application debug mode is enabled. Disable in production to avoid information disclosure.',
                'fix' => 'Set APP_DEBUG=false in .env',
            ];
        }
    }

    /**
     * @param  array<int, array{check_id: string, severity: string, message: string, fix: string|null}>  $findings
     */
    private function checkRuntimeDefaultMode(array &$findings): void
    {
        $default = Config::get('runtime.default_mode', 'safe');
        if ($default !== 'safe') {
            $findings[] = [
                'check_id' => 'runtime.default_mode_not_safe',
                'severity' => self::SEVERITY_INFO,
                'message' => "Default runtime mode is \"{$default}\". Safe mode is recommended for least privilege.",
                'fix' => 'Set runtime.default_mode=safe in config/runtime.php or RUNTIME_DEFAULT_MODE env',
            ];
        }
    }

    /**
     * @param  array<int, array{check_id: string, severity: string, message: string, fix: string|null}>  $findings
     */
    private function checkToolPolicy(array &$findings): void
    {
        $deny = Config::get('runtime.tool_deny', []);
        $allow = Config::get('runtime.tool_allow', []);

        if ($deny === [] && $allow === []) {
            $findings[] = [
                'check_id' => 'runtime.no_tool_restrictions',
                'severity' => self::SEVERITY_INFO,
                'message' => 'No tool deny or allow list is configured. All tools enabled by mode are available.',
                'fix' => 'Set RUNTIME_TOOL_DENY for sensitive tools (e.g. fs.write,runtime.spawn) or RUNTIME_TOOL_ALLOW for allowlist mode',
            ];
        }
    }

    /**
     * @param  array<int, array{check_id: string, severity: string, message: string, fix: string|null}>  $findings
     */
    private function checkLoggingRedaction(array &$findings): void
    {
        $redact = Config::get('logging.redact_sensitive', true);
        if (! $redact) {
            $findings[] = [
                'check_id' => 'logging.redact_sensitive_off',
                'severity' => self::SEVERITY_WARN,
                'message' => 'Sensitive value redaction in logs is disabled. Tokens and secrets may appear in log output.',
                'fix' => 'Enable logging.redact_sensitive in config or LOG_REDACT_SENSITIVE=true',
            ];
        }
    }

    /**
     * @param  array<int, array{check_id: string, severity: string, message: string, fix: string|null}>  $findings
     */
    private function checkSessionTimeout(array &$findings): void
    {
        $timeout = Config::get('runtime.session_timeout');
        if ($timeout === null) {
            $findings[] = [
                'check_id' => 'runtime.session_timeout_unset',
                'severity' => self::SEVERITY_INFO,
                'message' => 'Runtime session timeout is not set. Sessions may run indefinitely until manually stopped.',
                'fix' => 'Set runtime.session_timeout (seconds) in config/runtime.php for automatic cleanup',
            ];
        }
    }

    /**
     * @param  array<int, array{check_id: string, severity: string, message: string, fix: string|null}>  $findings
     */
    private function checkContentTrustActive(array &$findings): void
    {
        $findings[] = [
            'check_id' => 'security.content_trust_active',
            'severity' => self::SEVERITY_INFO,
            'message' => 'Content trust classification is active. All tool results are classified by trust level.',
            'fix' => null,
        ];
    }

    /**
     * @param  array<int, array{check_id: string, severity: string, message: string, fix: string|null}>  $findings
     */
    private function checkInjectionDetectionActive(array &$findings): void
    {
        $findings[] = [
            'check_id' => 'security.injection_detection_active',
            'severity' => self::SEVERITY_INFO,
            'message' => 'Injection detection engine is active. Untrusted content is scanned for prompt injection patterns.',
            'fix' => null,
        ];
    }

    /**
     * @param  array<int, array{check_id: string, severity: string, message: string, fix: string|null}>  $findings
     */
    private function checkExfiltrationDetectionActive(array &$findings): void
    {
        $findings[] = [
            'check_id' => 'security.exfiltration_detection_active',
            'severity' => self::SEVERITY_INFO,
            'message' => 'Exfiltration detection is active. Outbound requests are monitored for data leakage patterns.',
            'fix' => null,
        ];
    }

    /**
     * @param  array<int, array{check_id: string, severity: string, message: string, fix: string|null}>  $findings
     */
    private function checkDefaultDenyDisabled(array &$findings): void
    {
        if (! $this->configProvider->get('default_deny_external')) {
            $findings[] = [
                'check_id' => 'security.default_deny_disabled',
                'severity' => self::SEVERITY_WARN,
                'message' => 'Default deny for external tool calls is disabled. Tainted turns may execute mutations without user confirmation.',
                'fix' => 'Set SECURITY_DEFAULT_DENY_EXTERNAL=true in .env',
            ];
        }
    }

    /**
     * @param  array<int, array{check_id: string, severity: string, message: string, fix: string|null}>  $findings
     */
    private function checkStripHtmlDisabled(array &$findings): void
    {
        if (! $this->configProvider->get('strip_html')) {
            $findings[] = [
                'check_id' => 'security.strip_html_disabled',
                'severity' => self::SEVERITY_WARN,
                'message' => 'HTML/JS stripping is disabled. Tool results may contain executable content.',
                'fix' => 'Set SECURITY_STRIP_HTML=true in .env',
            ];
        }
    }

    /**
     * @param  array<int, array{check_id: string, severity: string, message: string, fix: string|null}>  $findings
     */
    private function checkInjectionThresholdHigh(array &$findings): void
    {
        $thresholds = [
            'injection_threshold_safe' => $this->configProvider->get('injection_threshold_safe'),
            'injection_threshold_standard' => $this->configProvider->get('injection_threshold_standard'),
            'injection_threshold_full' => $this->configProvider->get('injection_threshold_full'),
        ];

        foreach ($thresholds as $key => $value) {
            if ((float) $value > 0.7) {
                $findings[] = [
                    'check_id' => 'security.injection_threshold_high',
                    'severity' => self::SEVERITY_WARN,
                    'message' => "Injection detection threshold {$key} is set to {$value}, above recommended maximum of 0.7. This may allow more injection attempts through.",
                    'fix' => "Lower {$key} to 0.7 or below via SECURITY_".strtoupper($key).' env variable',
                ];

                return;
            }
        }
    }

    /**
     * @param  array<int, array{check_id: string, severity: string, message: string, fix: string|null}>  $findings
     */
    private function checkDetectionRulesCount(array &$findings): void
    {
        $count = SecurityDetectionRule::where('enabled', true)->count();

        if ($count < 20) {
            $findings[] = [
                'check_id' => 'security.detection_rules_count',
                'severity' => self::SEVERITY_WARN,
                'message' => "Only {$count} enabled detection rules found. At least 20 are recommended for comprehensive coverage.",
                'fix' => 'Run the security detection rules seeder or add custom rules via the security_detection_rules table',
            ];
        }
    }

    /**
     * @param  array<int, array{check_id: string, severity: string, message: string, fix: string|null}>  $findings
     */
    private function checkMessengerRateLimitHigh(array &$findings): void
    {
        $limit = (int) $this->configProvider->get('messenger_rate_limit');

        if ($limit > 50) {
            $findings[] = [
                'check_id' => 'security.messenger_rate_limit_high',
                'severity' => self::SEVERITY_INFO,
                'message' => "Messenger rate limit is set to {$limit} actions/5min, above the recommended maximum of 50.",
                'fix' => 'Set SECURITY_MESSENGER_RATE_LIMIT to 50 or lower in .env',
            ];
        }
    }

    /**
     * @param  array<int, array{check_id: string, severity: string, message: string, fix: string|null}>  $findings
     */
    private function checkPromptIsolationDisabled(array &$findings): void
    {
        if (! $this->configProvider->get('prompt_isolation')) {
            $findings[] = [
                'check_id' => 'security.prompt_isolation_disabled',
                'severity' => self::SEVERITY_WARN,
                'message' => 'Prompt isolation is disabled. User-provided context will not be wrapped in security boundaries.',
                'fix' => 'Set SECURITY_PROMPT_ISOLATION=true in .env',
            ];
        }
    }

    /**
     * @param  array<int, array{check_id: string, severity: string, message: string, fix: string|null}>  $findings
     */
    private function checkImmutableConfigIntact(array &$findings): void
    {
        $immutableKeys = ['content_trust_enabled', 'injection_detection_enabled', 'exfiltration_detection'];

        foreach ($immutableKeys as $key) {
            if (! $this->configProvider->get($key)) {
                $findings[] = [
                    'check_id' => 'security.immutable_config_intact',
                    'severity' => self::SEVERITY_CRITICAL,
                    'message' => "Immutable security config key '{$key}' returned false. This indicates a critical configuration bypass.",
                    'fix' => 'Investigate SecurityConfigProvider immutability enforcement — these keys must always return true',
                ];

                return;
            }
        }
    }
}
