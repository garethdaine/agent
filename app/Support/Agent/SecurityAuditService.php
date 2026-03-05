<?php

namespace App\Support\Agent;

use Illuminate\Support\Facades\Config;

class SecurityAuditService
{
    public const SEVERITY_CRITICAL = 'critical';

    public const SEVERITY_WARN = 'warn';

    public const SEVERITY_INFO = 'info';

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
        $this->checkLoggingRedaction($findings);
        $this->checkSessionTimeout($findings);

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
}
