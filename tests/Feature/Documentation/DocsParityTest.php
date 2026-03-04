<?php

declare(strict_types=1);

namespace Tests\Feature\Documentation;

use Tests\TestCase;

class DocsParityTest extends TestCase
{
    public function test_canonical_docs_exist_and_publish_required_phase_one_contracts(): void
    {
        $this->assertFileContainsAll(
            base_path('README.md'),
            [
                'We help companies deploy AI agents safely and keep them reliable in production.',
                'Canonical Phase 1 contracts live in `docs/system-overview.md`.',
                'local-first Laravel runtime with provider-agnostic telemetry contract',
                'event-id stability',
                'terminal catalog drift',
                'projection query restrictions',
            ]
        );

        $this->assertFileContainsAll(
            base_path('docs/PROJECT-STATUS.md'),
            [
                'We help companies deploy AI agents safely and keep them reliable in production.',
                'Canonical source of truth: `docs/system-overview.md`.',
                'Workflow key regex: `^[a-z0-9._-]+[.]v[1-9][0-9]*$`.',
                'WeightedReliability = (sum(run_weight) / count(scored_runs)) * 100',
                'active_build_age_seconds',
                'event-id stability',
                'terminal catalog drift',
                'projection query restrictions',
            ]
        );

        $this->assertFileContainsAll(
            base_path('docs/system-overview.md'),
            [
                'We help companies deploy AI agents safely and keep them reliable in production.',
                'local-first Laravel runtime with provider-agnostic telemetry contract',
                'Workflow key regex: `^[a-z0-9._-]+[.]v[1-9][0-9]*$`.',
                'WeightedReliability = (sum(run_weight) / count(scored_runs)) * 100',
                'Reliability gates evaluate both rolling `14-day` and rolling `50-run` windows and enforce the stricter result.',
                'Assisted SLA breach auto-reclassifies to `Failed` after `<=24h`.',
                'hard_fail is set when `failure_class=hard_fail` or when `policy_blocked` / `guardrail_blocked` terminates execution.',
                '`event_id` is producer-generated and unique within a `run_attempt_id`; global uniqueness is not required.',
                'Terminalization is catalog-driven: an attempt is terminal only when an ingested event has `terminal=true` and the event type is in the configured terminal catalog.',
                'projection tables are internal infrastructure data; no external ad hoc/reporting queries are permitted.',
                'Only one rebuild may be active at a time.',
                '`active_build_age_seconds` = server UTC now - active projection build `activated_at`.',
                'Known risk boundary: event-id stability mistakes from producers can break dedupe semantics.',
                'Known risk boundary: terminal catalog drift can misclassify attempt terminalization until catalog updates and replay complete.',
                'Known risk boundary: projection query restrictions must be enforced so external consumers cannot bypass active-build scoped APIs.',
            ]
        );
    }

    public function test_pull_request_template_requires_docs_and_contract_drift_checks(): void
    {
        $this->assertFileContainsAll(
            base_path('.github/pull_request_template.md'),
            [
                'docs parity',
                'route/page discoverability impacts',
                'additive API compatibility',
                'trigger-taxonomy consistency',
                'active-build read-scope consistency',
            ]
        );
    }

    /**
     * @param  array<int, string>  $needles
     */
    private function assertFileContainsAll(string $path, array $needles): void
    {
        $this->assertFileExists($path, sprintf('Expected docs parity file to exist: %s', $path));

        $contents = file_get_contents($path);

        $this->assertIsString($contents, sprintf('Unable to read docs parity file: %s', $path));

        foreach ($needles as $needle) {
            $this->assertStringContainsString(
                $needle,
                $contents,
                sprintf('Missing parity marker "%s" in %s', $needle, $path)
            );
        }
    }
}
