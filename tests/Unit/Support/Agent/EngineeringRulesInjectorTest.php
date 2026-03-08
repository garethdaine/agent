<?php

namespace Tests\Unit\Support\Agent;

use App\Support\Agent\EngineeringRulesInjector;
use App\Support\Agent\FeatureFlagManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class EngineeringRulesInjectorTest extends TestCase
{
    private EngineeringRulesInjector $injector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->injector = new EngineeringRulesInjector;

        // Default: enable the feature flag via config
        config(['agent.engineering_rules.enabled' => true]);
        config(['agent.engineering_rules.source_path' => base_path('docs/refactoring/agent-ops-engineering-rules.md')]);
        config(['agent.engineering_rules.cache_ttl_seconds' => 3600]);
        config(['agent.engineering_rules.default_profile' => 'full']);
        config(['agent.engineering_rules.max_tokens.full' => 8000]);
        config(['agent.engineering_rules.max_tokens.core' => 4000]);
        config(['agent.engineering_rules.max_tokens.interrogation' => 3000]);
        config(['agent.engineering_rules.max_tokens.build' => 6000]);

        // Clear cached rules between tests
        Cache::store('redis')->forget('engineering_rules:compiled:full');
        Cache::store('redis')->forget('engineering_rules:compiled:core');
        Cache::store('redis')->forget('engineering_rules:compiled:interrogation');
        Cache::store('redis')->forget('engineering_rules:compiled:build');
    }

    public function test_returns_null_when_disabled(): void
    {
        config(['agent.engineering_rules.enabled' => false]);

        $this->assertNull($this->injector->getCompiledRules());
    }

    public function test_is_enabled_reads_feature_flag(): void
    {
        config(['agent.engineering_rules.enabled' => true]);
        $this->assertTrue($this->injector->isEnabled());

        config(['agent.engineering_rules.enabled' => false]);
        // Need fresh instance since FeatureFlagManager may cache
        $injector = new EngineeringRulesInjector;
        $this->assertFalse($injector->isEnabled());
    }

    public function test_compiles_full_profile_with_all_rule_sections(): void
    {
        $rules = $this->injector->getCompiledRules('full');

        $this->assertNotNull($rules);
        $this->assertNotEmpty($rules);

        // Should contain key rule sections
        $this->assertStringContainsString('Software Architecture', $rules);
        $this->assertStringContainsString('Security', $rules);
        $this->assertStringContainsString('SOLID Principles', $rules);
        $this->assertStringContainsString('Clean Code', $rules);
        $this->assertStringContainsString('Testing', $rules);
        $this->assertStringContainsString('AgentOps-Specific', $rules);

        // Should NOT contain the document preamble
        $this->assertStringNotContainsString('## How to Use This Document', $rules);
        $this->assertStringNotContainsString('## Skill Format & Installation', $rules);
    }

    public function test_compiles_core_profile_with_subset(): void
    {
        $rules = $this->injector->getCompiledRules('core');

        $this->assertNotNull($rules);

        // Core profile should include these sections
        $this->assertStringContainsString('Software Architecture', $rules);
        $this->assertStringContainsString('Security', $rules);
        $this->assertStringContainsString('SOLID Principles', $rules);
        $this->assertStringContainsString('Clean Code', $rules);
        $this->assertStringContainsString('Testing', $rules);
        $this->assertStringContainsString('AgentOps-Specific', $rules);

        // Core profile should NOT include these sections
        $this->assertStringNotContainsString('Marketing', $rules);
        $this->assertStringNotContainsString('UX / UI', $rules);
        $this->assertStringNotContainsString('REST APIs', $rules);
    }

    public function test_compiles_interrogation_profile(): void
    {
        $rules = $this->injector->getCompiledRules('interrogation');

        $this->assertNotNull($rules);

        // Interrogation profile should include
        $this->assertStringContainsString('Planning', $rules);
        $this->assertStringContainsString('Testing', $rules);
        $this->assertStringContainsString('Databases', $rules);
        $this->assertStringContainsString('AgentOps-Specific', $rules);

        // Should NOT include
        $this->assertStringNotContainsString('Marketing', $rules);
        $this->assertStringNotContainsString('UX / UI', $rules);
        $this->assertStringNotContainsString('Debugging', $rules);
    }

    public function test_compiles_build_profile(): void
    {
        $rules = $this->injector->getCompiledRules('build');

        $this->assertNotNull($rules);

        // Build includes most sections except Marketing/UX/Design/Research
        $this->assertStringContainsString('Software Architecture', $rules);
        $this->assertStringContainsString('Security', $rules);
        $this->assertStringContainsString('Testing', $rules);
        $this->assertStringContainsString('AgentOps-Specific', $rules);

        // Build should NOT include
        $this->assertStringNotContainsString('Marketing', $rules);
        $this->assertStringNotContainsString('Research & Analysis', $rules);
    }

    public function test_inject_prepends_rules_block_to_content(): void
    {
        $originalContent = "# My Task\n\nDo something useful.";
        $result = $this->injector->inject($originalContent);

        // Rules header should appear first
        $this->assertStringStartsWith('## Engineering Rules (Active)', $result);

        // Original content should appear after separator
        $this->assertStringContainsString("---\n\n# My Task", $result);
        $this->assertStringContainsString('Do something useful.', $result);

        // Rules content should be between header and original
        $this->assertStringContainsString('Software Architecture', $result);
    }

    public function test_inject_returns_original_content_when_disabled(): void
    {
        config(['agent.engineering_rules.enabled' => false]);
        $injector = new EngineeringRulesInjector;

        $original = '# My Task';
        $result = $injector->inject($original);

        $this->assertSame($original, $result);
    }

    public function test_inject_truncates_at_max_tokens(): void
    {
        // Set very small token limit to force truncation
        config(['agent.engineering_rules.max_tokens.full' => 50]);

        $result = $this->injector->inject('# Task');

        // Should contain truncation marker
        $this->assertStringContainsString('[truncated]', $result);
        // Should still contain the original task
        $this->assertStringContainsString('# Task', $result);
    }

    public function test_inject_into_system_prompt_prepends_rules(): void
    {
        $systemPrompt = 'You are an AI assistant.';
        $result = $this->injector->injectIntoSystemPrompt($systemPrompt, 'core');

        // Should start with rules header
        $this->assertStringStartsWith('## Engineering Rules', $result);

        // Should contain original system prompt after separator
        $this->assertStringContainsString("---\n\nYou are an AI assistant.", $result);
    }

    public function test_inject_into_system_prompt_returns_original_when_disabled(): void
    {
        config(['agent.engineering_rules.enabled' => false]);
        $injector = new EngineeringRulesInjector;

        $prompt = 'You are an AI assistant.';
        $this->assertSame($prompt, $injector->injectIntoSystemPrompt($prompt));
    }

    public function test_caches_compiled_rules_in_redis(): void
    {
        // First call should compile and cache
        $rules1 = $this->injector->getCompiledRules('core');
        $this->assertNotNull($rules1);

        // Verify it's in cache
        $cached = Cache::store('redis')->get('engineering_rules:compiled:core');
        $this->assertNotNull($cached);
        $this->assertSame($rules1, $cached);

        // Second call should return cached version
        $rules2 = $this->injector->getCompiledRules('core');
        $this->assertSame($rules1, $rules2);
    }

    public function test_handles_missing_rules_file_gracefully(): void
    {
        config(['agent.engineering_rules.source_path' => '/nonexistent/path/rules.md']);

        // Clear cache so it needs to compile
        Cache::store('redis')->forget('engineering_rules:compiled:full');

        $result = $this->injector->getCompiledRules('full');
        $this->assertNull($result);
    }

    public function test_handles_missing_file_in_inject_gracefully(): void
    {
        config(['agent.engineering_rules.source_path' => '/nonexistent/path/rules.md']);
        Cache::store('redis')->forget('engineering_rules:compiled:full');

        $original = '# My Task';
        $result = $this->injector->inject($original);

        // Should return original content unchanged
        $this->assertSame($original, $result);
    }

    public function test_strips_preamble_from_full_profile(): void
    {
        $rules = $this->injector->getCompiledRules('full');

        $this->assertNotNull($rules);

        // Preamble sections should be stripped
        $this->assertStringNotContainsString('# AgentOps Engineering Rules v2.0', $rules);
        $this->assertStringNotContainsString('## How to Use This Document', $rules);
        $this->assertStringNotContainsString('## Skill Format & Installation', $rules);
    }

    public function test_core_profile_is_smaller_than_full(): void
    {
        $full = $this->injector->getCompiledRules('full');
        $core = $this->injector->getCompiledRules('core');

        $this->assertNotNull($full);
        $this->assertNotNull($core);
        $this->assertLessThan(strlen($full), strlen($core));
    }

    public function test_interrogation_profile_is_smaller_than_core(): void
    {
        $core = $this->injector->getCompiledRules('core');
        $interrogation = $this->injector->getCompiledRules('interrogation');

        $this->assertNotNull($core);
        $this->assertNotNull($interrogation);
        $this->assertLessThan(strlen($core), strlen($interrogation));
    }
}
