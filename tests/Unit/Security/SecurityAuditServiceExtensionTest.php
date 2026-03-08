<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Models\Security\SecurityDetectionRule;
use App\Services\Security\SecurityConfigProvider;
use App\Support\Agent\SecurityAuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class SecurityAuditServiceExtensionTest extends TestCase
{
    use RefreshDatabase;

    private SecurityAuditService $service;

    private SecurityConfigProvider $configProvider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configProvider = new SecurityConfigProvider;
        $this->service = new SecurityAuditService($this->configProvider);
    }

    // --- INFO findings that are always produced ---

    public function test_content_trust_active_finding_always_produced(): void
    {
        $findings = $this->service->run();

        $found = collect($findings)->firstWhere('check_id', 'security.content_trust_active');
        $this->assertNotNull($found);
        $this->assertSame('info', $found['severity']);
    }

    public function test_injection_detection_active_finding_always_produced(): void
    {
        $findings = $this->service->run();

        $found = collect($findings)->firstWhere('check_id', 'security.injection_detection_active');
        $this->assertNotNull($found);
        $this->assertSame('info', $found['severity']);
    }

    public function test_exfiltration_detection_active_finding_always_produced(): void
    {
        $findings = $this->service->run();

        $found = collect($findings)->firstWhere('check_id', 'security.exfiltration_detection_active');
        $this->assertNotNull($found);
        $this->assertSame('info', $found['severity']);
    }

    // --- Default deny disabled ---

    public function test_default_deny_disabled_warn_when_env_false(): void
    {
        putenv('SECURITY_DEFAULT_DENY_EXTERNAL=false');

        $provider = new SecurityConfigProvider;
        $service = new SecurityAuditService($provider);
        $findings = $service->run();

        $found = collect($findings)->firstWhere('check_id', 'security.default_deny_disabled');
        $this->assertNotNull($found);
        $this->assertSame('warn', $found['severity']);

        putenv('SECURITY_DEFAULT_DENY_EXTERNAL');
    }

    public function test_default_deny_disabled_not_produced_when_env_true(): void
    {
        putenv('SECURITY_DEFAULT_DENY_EXTERNAL=true');

        $provider = new SecurityConfigProvider;
        $service = new SecurityAuditService($provider);
        $findings = $service->run();

        $found = collect($findings)->firstWhere('check_id', 'security.default_deny_disabled');
        $this->assertNull($found);

        putenv('SECURITY_DEFAULT_DENY_EXTERNAL');
    }

    // --- Strip HTML disabled ---

    public function test_strip_html_disabled_warn_when_env_false(): void
    {
        putenv('SECURITY_STRIP_HTML=false');

        $provider = new SecurityConfigProvider;
        $service = new SecurityAuditService($provider);
        $findings = $service->run();

        $found = collect($findings)->firstWhere('check_id', 'security.strip_html_disabled');
        $this->assertNotNull($found);
        $this->assertSame('warn', $found['severity']);

        putenv('SECURITY_STRIP_HTML');
    }

    // --- Injection threshold high ---

    public function test_injection_threshold_high_warn_when_any_threshold_above_0_7(): void
    {
        putenv('SECURITY_INJECTION_THRESHOLD_SAFE=0.8');

        $provider = new SecurityConfigProvider;
        $service = new SecurityAuditService($provider);
        $findings = $service->run();

        $found = collect($findings)->firstWhere('check_id', 'security.injection_threshold_high');
        $this->assertNotNull($found);
        $this->assertSame('warn', $found['severity']);

        putenv('SECURITY_INJECTION_THRESHOLD_SAFE');
    }

    // --- Detection rules count ---

    public function test_detection_rules_count_warn_when_fewer_than_20_enabled(): void
    {
        // Ensure fewer than 20 rules exist
        SecurityDetectionRule::query()->delete();
        SecurityDetectionRule::factory()->count(5)->create(['enabled' => true]);

        $findings = $this->service->run();

        $found = collect($findings)->firstWhere('check_id', 'security.detection_rules_count');
        $this->assertNotNull($found);
        $this->assertSame('warn', $found['severity']);
    }

    public function test_detection_rules_count_not_produced_when_at_least_20(): void
    {
        SecurityDetectionRule::query()->delete();
        SecurityDetectionRule::factory()->count(20)->create(['enabled' => true]);

        $findings = $this->service->run();

        $found = collect($findings)->firstWhere('check_id', 'security.detection_rules_count');
        $this->assertNull($found);
    }

    // --- Messenger rate limit high ---

    public function test_messenger_rate_limit_high_info_when_above_50(): void
    {
        putenv('SECURITY_MESSENGER_RATE_LIMIT=60');

        $provider = new SecurityConfigProvider;
        $service = new SecurityAuditService($provider);
        $findings = $service->run();

        $found = collect($findings)->firstWhere('check_id', 'security.messenger_rate_limit_high');
        $this->assertNotNull($found);
        $this->assertSame('info', $found['severity']);

        putenv('SECURITY_MESSENGER_RATE_LIMIT');
    }

    // --- Prompt isolation disabled ---

    public function test_prompt_isolation_disabled_warn_when_env_false(): void
    {
        putenv('SECURITY_PROMPT_ISOLATION=false');

        $provider = new SecurityConfigProvider;
        $service = new SecurityAuditService($provider);
        $findings = $service->run();

        $found = collect($findings)->firstWhere('check_id', 'security.prompt_isolation_disabled');
        $this->assertNotNull($found);
        $this->assertSame('warn', $found['severity']);

        putenv('SECURITY_PROMPT_ISOLATION');
    }

    // --- Immutable config intact ---

    public function test_immutable_config_intact_always_passes(): void
    {
        $findings = $this->service->run();

        $found = collect($findings)->firstWhere('check_id', 'security.immutable_config_intact');
        $this->assertNull($found, 'Immutable config check should not produce a finding when intact');
    }

    // --- No false findings with secure defaults ---

    public function test_secure_defaults_only_produce_info_findings(): void
    {
        // Set all recommended security settings
        putenv('SECURITY_DEFAULT_DENY_EXTERNAL=true');
        putenv('SECURITY_STRIP_HTML=true');
        putenv('SECURITY_PROMPT_ISOLATION=true');

        SecurityDetectionRule::query()->delete();
        SecurityDetectionRule::factory()->count(25)->create(['enabled' => true]);

        $provider = new SecurityConfigProvider;
        $service = new SecurityAuditService($provider);
        $findings = $service->run();

        $securityFindings = collect($findings)->filter(
            fn (array $f) => str_starts_with($f['check_id'], 'security.')
        );

        foreach ($securityFindings as $finding) {
            $this->assertNotSame(
                'warn',
                $finding['severity'],
                "Security finding {$finding['check_id']} should not be WARN with secure defaults"
            );
            $this->assertNotSame(
                'critical',
                $finding['severity'],
                "Security finding {$finding['check_id']} should not be CRITICAL with secure defaults"
            );
        }

        putenv('SECURITY_DEFAULT_DENY_EXTERNAL');
        putenv('SECURITY_STRIP_HTML');
        putenv('SECURITY_PROMPT_ISOLATION');
    }

    // --- Existing checks preserved ---

    public function test_existing_checks_still_produced(): void
    {
        Config::set('app.debug', true);

        $findings = $this->service->run();

        $debugFinding = collect($findings)->firstWhere('check_id', 'app.debug_enabled');
        $this->assertNotNull($debugFinding, 'Existing app.debug_enabled check should still be produced');
    }
}
