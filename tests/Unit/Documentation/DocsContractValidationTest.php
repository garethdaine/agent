<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use App\Support\Documentation\Ingestion\DocsContractValidator;
use App\Support\Documentation\Schemas\DocumentationValidationException;
use Tests\TestCase;

class DocsContractValidationTest extends TestCase
{
    public function test_markdown_front_matter_requires_all_contract_fields(): void
    {
        $validator = app(DocsContractValidator::class);

        $markdown = <<<'MD'
---
slug: monitor-overview
title: Monitor Overview
summary: Monitor page docs.
section: monitor
audience: operator
status: published
version: "1.0.0"
tags:
  - monitor
route_names:
  - agent.monitor.index
setting_keys:
  - monitor.refresh_interval
feature_flags:
  - docs_search_enabled
locale: en
reviewed_at: 2026-03-01
---
# Monitor
MD;

        $this->expectException(DocumentationValidationException::class);
        $this->expectExceptionMessage('owner');

        $validator->validateMarkdownString($markdown, 'docs/product/monitor/overview.md');
    }

    public function test_markdown_front_matter_rejects_malformed_yaml(): void
    {
        $validator = app(DocsContractValidator::class);

        $markdown = <<<'MD'
---
slug: bad-yaml
title: Broken
summary: Broken front matter
section monitor
---
# Broken
MD;

        $this->expectException(DocumentationValidationException::class);
        $this->expectExceptionMessage('Malformed YAML front matter');

        $validator->validateMarkdownString($markdown, 'docs/product/monitor/broken.md');
    }

    public function test_markdown_front_matter_rejects_invalid_linkage_arrays_and_non_english_locale(): void
    {
        $validator = app(DocsContractValidator::class);

        $markdown = <<<'MD'
---
slug: non-english-locale
title: Monitor in Spanish
summary: Should fail in phase 1.
section: monitor
audience: operator
status: published
version: "1.0.0"
tags:
  - monitor
owner: docs-team
route_names: agent.monitor.index
setting_keys:
  - monitor.refresh_interval
feature_flags:
  - docs_search_enabled
locale: es
reviewed_at: 2026-03-01
---
# Monitor
MD;

        $this->expectException(DocumentationValidationException::class);

        try {
            $validator->validateMarkdownString($markdown, 'docs/product/monitor/spanish.md');
        } catch (DocumentationValidationException $exception) {
            $this->assertStringContainsString('route_names', $exception->getMessage());
            $this->assertStringContainsString('locale', $exception->getMessage());

            throw $exception;
        }
    }

    public function test_tooltip_yaml_requires_schema_and_rejects_unknown_severity(): void
    {
        $validator = app(DocsContractValidator::class);

        $yaml = <<<'YAML'
- ui_key: monitor.latency
  short_text: Latency metrics
  severity: critical
  links:
    - label: API Docs
      url: https://laravel.com/docs/12.x
  metadata:
    owner: docs-team
    locale: en
YAML;

        $this->expectException(DocumentationValidationException::class);
        $this->expectExceptionMessage('severity');

        $validator->validateTooltipYamlString($yaml, 'docs/tooltips/monitor.yaml');
    }

    public function test_tooltip_yaml_rejects_invalid_link_domains_and_short_text_limits(): void
    {
        $validator = app(DocsContractValidator::class);

        $yaml = <<<'YAML'
- ui_key: monitor.latency
  short_text: This tooltip text is intentionally made much longer than the configured maximum to prove strict validator behavior for maintainers who accidentally add verbose helper copy.
  severity: info
  links:
    - label: Suspicious
      url: https://example.com/not-allowed
  metadata:
    owner: docs-team
    locale: en
YAML;

        $this->expectException(DocumentationValidationException::class);

        try {
            $validator->validateTooltipYamlString($yaml, 'docs/tooltips/monitor.yaml');
        } catch (DocumentationValidationException $exception) {
            $this->assertStringContainsString('short_text', $exception->getMessage());
            $this->assertStringContainsString('allowed domains', $exception->getMessage());

            throw $exception;
        }
    }

    public function test_valid_markdown_and_tooltip_payloads_pass_validation(): void
    {
        $validator = app(DocsContractValidator::class);

        $markdown = <<<'MD'
---
slug: dashboard-overview
title: Dashboard Overview
summary: Dashboard intent and usage.
section: dashboard
audience: operator
status: published
version: "1.0.0"
tags:
  - dashboard
owner: docs-team
route_names:
  - dashboard
setting_keys:
  - dashboard.default_range
feature_flags:
  - docs_center_enabled
locale: en
reviewed_at: 2026-03-01
---
# Dashboard
This is dashboard documentation.
MD;

        $yaml = <<<'YAML'
- ui_key: dashboard.load_time
  short_text: Shows recent dashboard load-time trend.
  severity: warning
  links:
    - label: Laravel Docs
      url: https://laravel.com/docs/12.x
  metadata:
    owner: docs-team
    locale: en
YAML;

        $entry = $validator->validateMarkdownString($markdown, 'docs/product/dashboard/overview.md');
        $fragments = $validator->validateTooltipYamlString($yaml, 'docs/tooltips/dashboard.yaml');

        $this->assertSame('dashboard-overview', $entry['front_matter']['slug']);
        $this->assertSame('dashboard.load_time', $fragments[0]['ui_key']);
    }
}
