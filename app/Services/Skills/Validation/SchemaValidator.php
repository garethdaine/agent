<?php

declare(strict_types=1);

namespace App\Services\Skills\Validation;

use App\Models\AgentSkill;
use App\Services\Skills\DTOs\SkillParseResult;

class SchemaValidator
{
    private const VALID_RISK_LEVELS = ['low', 'standard', 'elevated', 'critical'];

    private const KNOWN_X_AGENT_KEYS = [
        'industries', 'risk_level', 'requires_approval', 'memory_blocks',
        'mcp_dependencies', 'tools', 'trigger_keywords', 'run_after', 'compatibility',
    ];

    public function validate(SkillParseResult $parsed, int $teamId): StageResult
    {
        $failures = [];
        $warnings = [];
        $checks = 0;

        // Required: name
        $checks++;
        if ($parsed->name === null || $parsed->name === '') {
            $failures[] = [
                'check' => 'name_required',
                'severity' => StageResult::SEVERITY_BLOCK,
                'message' => 'Skill name is required.',
            ];
        }

        // Name format: lowercase + hyphens only, max 64 chars
        $checks++;
        if ($parsed->name !== null && ! preg_match('/^[a-z0-9][a-z0-9-]{0,63}$/', $parsed->name)) {
            $failures[] = [
                'check' => 'name_format',
                'severity' => StageResult::SEVERITY_BLOCK,
                'message' => 'Skill name must be lowercase alphanumeric with hyphens, max 64 characters.',
            ];
        }

        // Required: description
        $checks++;
        if ($parsed->description === null || $parsed->description === '') {
            $failures[] = [
                'check' => 'description_required',
                'severity' => StageResult::SEVERITY_BLOCK,
                'message' => 'Skill description is required.',
            ];
        }

        // Required: version
        $checks++;
        if ($parsed->version === null || $parsed->version === '') {
            $failures[] = [
                'check' => 'version_required',
                'severity' => StageResult::SEVERITY_BLOCK,
                'message' => 'Skill version is required.',
            ];
        }

        // Required: author
        $checks++;
        if ($parsed->author === null || $parsed->author === '') {
            $failures[] = [
                'check' => 'author_required',
                'severity' => StageResult::SEVERITY_BLOCK,
                'message' => 'Skill author is required.',
            ];
        }

        // Uniqueness: team_id + slug
        $checks++;
        if ($parsed->name !== null) {
            $slug = $parsed->name;
            $exists = AgentSkill::where('team_id', $teamId)
                ->where('slug', $slug)
                ->exists();

            if ($exists) {
                $failures[] = [
                    'check' => 'duplicate_slug',
                    'severity' => StageResult::SEVERITY_BLOCK,
                    'message' => "A skill with slug '{$slug}' already exists for this team.",
                ];
            }
        }

        // Risk level validation
        $checks++;
        if (! in_array($parsed->riskLevel, self::VALID_RISK_LEVELS, true)) {
            $warnings[] = "Unknown risk level '{$parsed->riskLevel}'. Valid values: ".implode(', ', self::VALID_RISK_LEVELS);
        }

        // Body length warning
        $checks++;
        $bodyLines = substr_count($parsed->markdownBody, "\n") + 1;
        $maxLines = (int) config('agent.skills.max_skill_body_lines_warning', 500);
        if ($bodyLines > $maxLines) {
            $warnings[] = "Skill body has {$bodyLines} lines, exceeding recommended maximum of {$maxLines}.";
        }

        // Unknown x-agent keys warning
        $checks++;
        $xAgentData = $parsed->rawFrontmatter['x-agent'] ?? [];
        if (is_array($xAgentData)) {
            $unknownKeys = array_diff(array_keys($xAgentData), self::KNOWN_X_AGENT_KEYS);
            if (! empty($unknownKeys)) {
                $warnings[] = 'Unknown x-agent keys: '.implode(', ', $unknownKeys);
            }
        }

        if (! empty($failures)) {
            return StageResult::fail('schema', $failures, $checks, $warnings);
        }

        if (! empty($warnings)) {
            return StageResult::warn('schema', $warnings, $checks);
        }

        return StageResult::pass('schema', $checks);
    }
}
