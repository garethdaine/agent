<?php

declare(strict_types=1);

namespace Tests\Unit\Skills;

use App\Services\Skills\DTOs\SkillParseResult;
use App\Services\Skills\SkillParser;
use Tests\TestCase;

class SkillParserTest extends TestCase
{
    private SkillParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new SkillParser;
    }

    // ---------------------------------------------------------------
    // Positive cases
    // ---------------------------------------------------------------

    public function test_parses_valid_skill_md_with_all_fields(): void
    {
        $content = file_get_contents(base_path('tests/Fixtures/Skills/valid-skill/SKILL.md'));
        $result = $this->parser->parse($content);

        $this->assertInstanceOf(SkillParseResult::class, $result);
        $this->assertTrue($result->isValid());
        $this->assertEmpty($result->errors);

        $this->assertSame('financial-compliance-check', $result->name);
        $this->assertStringContainsString('financial compliance requirements', $result->description);
        $this->assertSame('1.0.0', $result->version);
        $this->assertSame('agentops', $result->author);
        $this->assertSame('MIT', $result->license);
        $this->assertSame('elevated', $result->riskLevel);
        $this->assertSame(['financial-services'], $result->industries);
        $this->assertSame(['compliance-rules'], $result->memoryBlocks);
        $this->assertSame(['database'], $result->mcpDependencies);
        $this->assertSame(['file-read', 'web-search'], $result->toolsRequired);
        $this->assertFalse($result->requiresApproval);
        $this->assertSame([], $result->runAfter);
        $this->assertSame('Agent Platform >= 1.0', $result->compatibility);
        $this->assertNotEmpty($result->triggerKeywords);
        $this->assertNotEmpty($result->rawFrontmatter);
    }

    public function test_parses_minimal_skill_md(): void
    {
        $content = <<<'SKILL'
---
name: simple-task
description: "A simple task skill that performs basic automation workflows."
version: "1.0.0"
author: "testauthor"
---

# Simple Task
SKILL;

        $result = $this->parser->parse($content);

        $this->assertTrue($result->isValid());
        $this->assertSame('simple-task', $result->name);
        $this->assertSame('1.0.0', $result->version);
        $this->assertSame('testauthor', $result->author);
        $this->assertNull($result->license);
        $this->assertSame('standard', $result->riskLevel);
        $this->assertFalse($result->requiresApproval);
        $this->assertSame([], $result->industries);
        $this->assertSame([], $result->memoryBlocks);
        $this->assertSame([], $result->mcpDependencies);
        $this->assertSame([], $result->toolsRequired);
        $this->assertSame([], $result->runAfter);
        $this->assertNull($result->compatibility);
    }

    public function test_extracts_markdown_body(): void
    {
        $content = <<<'SKILL'
---
name: body-test
description: "Validates that the markdown body is extracted correctly from skill."
version: "1.0.0"
author: "tester"
---

# Heading

Some body content here.

## Sub-heading

More content.
SKILL;

        $result = $this->parser->parse($content);

        $this->assertTrue($result->isValid());
        $this->assertStringContainsString('# Heading', $result->markdownBody);
        $this->assertStringContainsString('Some body content here.', $result->markdownBody);
        $this->assertStringContainsString('## Sub-heading', $result->markdownBody);
    }

    public function test_unknown_x_agent_keys_produce_warnings(): void
    {
        $content = <<<'SKILL'
---
name: warn-test
description: "A skill with unknown x-agent keys for testing warning output."
version: "1.0.0"
author: "tester"
x-agent:
  unknown_key: some-value
  another_unknown: true
---

Body
SKILL;

        $result = $this->parser->parse($content);

        $this->assertTrue($result->isValid());
        $this->assertNotEmpty($result->warnings);
        $this->assertCount(2, $result->warnings);
        $this->assertStringContainsString('unknown_key', $result->warnings[0]);
        $this->assertStringContainsString('another_unknown', $result->warnings[1]);
    }

    public function test_unknown_root_keys_ignored_silently(): void
    {
        $content = <<<'SKILL'
---
name: root-keys-test
description: "A skill with unknown root-level keys that should be silently ignored."
version: "1.0.0"
author: "tester"
custom_field: some-value
another_custom: true
---

Body
SKILL;

        $result = $this->parser->parse($content);

        $this->assertTrue($result->isValid());
        $this->assertEmpty($result->warnings);
    }

    // ---------------------------------------------------------------
    // Trigger keyword cases
    // ---------------------------------------------------------------

    public function test_extracts_keywords_from_description(): void
    {
        $content = <<<'SKILL'
---
name: keyword-test
description: "Performs financial compliance auditing and regulatory verification across multiple jurisdictions."
version: "1.0.0"
author: "tester"
---

Body
SKILL;

        $result = $this->parser->parse($content);

        $this->assertTrue($result->isValid());
        $this->assertNotEmpty($result->triggerKeywords);

        // Should contain domain terms, not stopwords
        $keywords = $result->triggerKeywords;
        $this->assertNotContains('and', $keywords);
        $this->assertNotContains('the', $keywords);

        // Should contain domain-relevant terms
        $keywordsStr = implode(' ', $keywords);
        $this->assertStringContainsString('financial', $keywordsStr);
        $this->assertStringContainsString('compliance', $keywordsStr);
    }

    public function test_merges_author_keywords_with_extracted(): void
    {
        $content = <<<'SKILL'
---
name: merge-test
description: "Performs financial compliance auditing and regulatory verification across jurisdictions."
version: "1.0.0"
author: "tester"
x-agent:
  trigger_keywords: [custom-audit, reporting]
---

Body
SKILL;

        $result = $this->parser->parse($content);

        $this->assertTrue($result->isValid());

        // Author keywords should be present
        $this->assertContains('custom-audit', $result->triggerKeywords);
        $this->assertContains('reporting', $result->triggerKeywords);

        // Extracted keywords should also be present
        $this->assertContains('financial', $result->triggerKeywords);
    }

    public function test_author_keywords_override_takes_priority(): void
    {
        $content = <<<'SKILL'
---
name: priority-test
description: "Performs financial compliance auditing and regulatory verification across jurisdictions."
version: "1.0.0"
author: "tester"
x-agent:
  trigger_keywords: [alpha-keyword, beta-keyword]
---

Body
SKILL;

        $result = $this->parser->parse($content);

        $this->assertTrue($result->isValid());

        // Author keywords should appear first (priority)
        $this->assertSame('alpha-keyword', $result->triggerKeywords[0]);
        $this->assertSame('beta-keyword', $result->triggerKeywords[1]);
    }

    public function test_caps_keywords_at_max(): void
    {
        // Build a description with many unique terms to generate lots of keywords
        $terms = [];
        for ($i = 0; $i < 50; $i++) {
            $terms[] = "keyword{$i}";
        }
        $description = implode(' ', $terms);

        $content = <<<SKILL
---
name: cap-test
description: "{$description}"
version: "1.0.0"
author: "tester"
---

Body
SKILL;

        $result = $this->parser->parse($content);

        $maxKeywords = config('agent.skills.trigger_keyword_extraction.max_keywords');
        $this->assertLessThanOrEqual($maxKeywords, count($result->triggerKeywords));
    }

    // ---------------------------------------------------------------
    // Negative cases
    // ---------------------------------------------------------------

    public function test_returns_errors_for_missing_frontmatter(): void
    {
        $content = "# No frontmatter here\n\nJust some markdown.";

        $result = $this->parser->parse($content);

        $this->assertTrue($result->hasErrors());
        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('frontmatter', $result->errors[0]);
    }

    public function test_returns_errors_for_invalid_yaml(): void
    {
        $content = <<<'SKILL'
---
name: [invalid yaml
  missing: closing bracket
---

Body
SKILL;

        $result = $this->parser->parse($content);

        $this->assertTrue($result->hasErrors());
        $this->assertStringContainsString('Invalid YAML', $result->errors[0]);
    }

    public function test_returns_errors_for_missing_required_fields(): void
    {
        $content = <<<'SKILL'
---
description: "A valid description that meets the minimum length requirement."
version: "1.0.0"
author: "tester"
---

Body
SKILL;

        $result = $this->parser->parse($content);

        $this->assertTrue($result->hasErrors());
        $hasNameError = false;
        foreach ($result->errors as $error) {
            if (str_contains($error, 'name')) {
                $hasNameError = true;
            }
        }
        $this->assertTrue($hasNameError, 'Expected error about missing name field.');
    }

    public function test_returns_errors_for_invalid_name_format(): void
    {
        $content = <<<'SKILL'
---
name: "Invalid Name With Spaces"
description: "A valid description that meets the minimum length requirement."
version: "1.0.0"
author: "tester"
---

Body
SKILL;

        $result = $this->parser->parse($content);

        $this->assertTrue($result->hasErrors());
        $hasFormatError = false;
        foreach ($result->errors as $error) {
            if (str_contains($error, 'lowercase') || str_contains($error, 'pattern')) {
                $hasFormatError = true;
            }
        }
        $this->assertTrue($hasFormatError, 'Expected error about name format.');
    }

    public function test_returns_errors_for_name_too_long(): void
    {
        $longName = str_repeat('a', 65);
        $content = <<<SKILL
---
name: "{$longName}"
description: "A valid description that meets the minimum length requirement."
version: "1.0.0"
author: "tester"
---

Body
SKILL;

        $result = $this->parser->parse($content);

        $this->assertTrue($result->hasErrors());
        $hasLengthError = false;
        foreach ($result->errors as $error) {
            if (str_contains($error, '64')) {
                $hasLengthError = true;
            }
        }
        $this->assertTrue($hasLengthError, 'Expected error about name length.');
    }

    public function test_returns_errors_for_description_too_short(): void
    {
        $content = <<<'SKILL'
---
name: short-desc
description: "Too short"
version: "1.0.0"
author: "tester"
---

Body
SKILL;

        $result = $this->parser->parse($content);

        $this->assertTrue($result->hasErrors());
        $hasDescError = false;
        foreach ($result->errors as $error) {
            if (str_contains($error, 'Description') && str_contains($error, 'at least')) {
                $hasDescError = true;
            }
        }
        $this->assertTrue($hasDescError, 'Expected error about description being too short.');
    }

    public function test_returns_errors_for_description_too_long(): void
    {
        $longDesc = str_repeat('x', 1025);
        $content = <<<SKILL
---
name: long-desc
description: "{$longDesc}"
version: "1.0.0"
author: "tester"
---

Body
SKILL;

        $result = $this->parser->parse($content);

        $this->assertTrue($result->hasErrors());
        $hasDescError = false;
        foreach ($result->errors as $error) {
            if (str_contains($error, 'Description') && str_contains($error, '1024')) {
                $hasDescError = true;
            }
        }
        $this->assertTrue($hasDescError, 'Expected error about description being too long.');
    }

    public function test_returns_errors_for_invalid_semver(): void
    {
        $versions = ['1.0', 'abc', '1.0.0.0', 'v1.0.0'];

        foreach ($versions as $version) {
            $content = <<<SKILL
---
name: semver-test
description: "A valid description that meets the minimum length requirement."
version: "{$version}"
author: "tester"
---

Body
SKILL;

            $result = $this->parser->parse($content);

            $this->assertTrue($result->hasErrors(), "Expected error for version '{$version}'.");
            $hasSemverError = false;
            foreach ($result->errors as $error) {
                if (str_contains($error, 'semver') || str_contains($error, 'Version')) {
                    $hasSemverError = true;
                }
            }
            $this->assertTrue($hasSemverError, "Expected semver error for version '{$version}'.");
        }
    }

    // ---------------------------------------------------------------
    // Edge cases
    // ---------------------------------------------------------------

    public function test_empty_content_returns_error(): void
    {
        $result = $this->parser->parse('');

        $this->assertTrue($result->hasErrors());
        $this->assertStringContainsString('empty', $result->errors[0]);
    }

    public function test_frontmatter_only_no_body(): void
    {
        $content = <<<'SKILL'
---
name: no-body
description: "A valid description that meets the minimum length requirement."
version: "1.0.0"
author: "tester"
---
SKILL;

        $result = $this->parser->parse($content);

        $this->assertTrue($result->isValid());
        $this->assertSame('', $result->markdownBody);
    }

    public function test_invalid_risk_level_defaults_to_standard_with_warning(): void
    {
        $content = <<<'SKILL'
---
name: risk-test
description: "A valid description that meets the minimum length requirement."
version: "1.0.0"
author: "tester"
x-agent:
  risk_level: invalid-level
---

Body
SKILL;

        $result = $this->parser->parse($content);

        $this->assertTrue($result->isValid());
        $this->assertSame('standard', $result->riskLevel);
        $this->assertNotEmpty($result->warnings);
        $this->assertStringContainsString('invalid-level', $result->warnings[0]);
    }
}
