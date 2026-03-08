<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\DelegateeProfile;
use App\Models\DelegationCapability;
use App\Models\OrgAgentProfile;
use App\Models\OrgCouncilTemplate;
use App\Models\OrgReportingEdge;
use App\Models\OrgRitualTemplate;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeds a complete Code Review Organization with:
 * - 6 delegatee profiles (claude runners for different review specialties)
 * - 6 org agent profiles with reporting hierarchy
 * - 1 council template for code review deliberation
 * - 1 ritual template (hourly) for automated codebase review
 */
class CodeReviewOrgSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'gareth@garethdaine.com')->first()
            ?? User::first();

        if (! $user) {
            $this->command->error('No user found. Run UserSeeder first.');

            return;
        }

        $this->command->info("Seeding org for user: {$user->name} ({$user->email})");

        $this->ensureCapabilitiesExist();

        $this->command->info('Creating delegatee profiles...');
        $profiles = $this->createDelegateeProfiles($user);

        $this->command->info('Creating org agent profiles...');
        $agents = $this->createOrgAgents($user, $profiles);

        $this->command->info('Setting up reporting hierarchy...');
        $this->createReportingEdges($user, $agents);

        $this->command->info('Creating council template...');
        $council = $this->createCouncilTemplate($user, $agents);

        $this->command->info('Creating ritual template...');
        $this->createRitualTemplate($user);

        $this->command->info('Code Review Organization seeded successfully.');
        $this->command->table(
            ['Agent', 'Role', 'Reports To'],
            collect($agents)->map(fn ($a, $key) => [
                $a->name,
                $a->role_slug,
                $key === 'lead' ? '—' : 'Engineering Lead',
            ])->values()->toArray()
        );
    }

    private function ensureCapabilitiesExist(): void
    {
        $required = ['code_execution', 'review', 'research', 'documentation'];

        foreach ($required as $slug) {
            DelegationCapability::firstOrCreate(
                ['slug' => $slug],
                ['name' => str_replace('_', ' ', ucfirst($slug)), 'is_active' => true]
            );
        }
    }

    private function createDelegateeProfiles(User $user): array
    {
        $reviewCapIds = DelegationCapability::whereIn('slug', ['code_execution', 'review', 'research'])
            ->pluck('id')
            ->toArray();

        $docCapIds = DelegationCapability::whereIn('slug', ['review', 'documentation', 'research'])
            ->pluck('id')
            ->toArray();

        $claudeTemplate = config('agent.default_templates.claude');

        $specs = [
            'lead' => [
                'name' => 'Code Review Lead Runner',
                'runner_type' => 'claude',
                'command_template' => $claudeTemplate,
                'capabilities' => $reviewCapIds,
            ],
            'solid' => [
                'name' => 'SOLID Reviewer Runner',
                'runner_type' => 'claude',
                'command_template' => $claudeTemplate,
                'capabilities' => $reviewCapIds,
            ],
            'laravel' => [
                'name' => 'Laravel Best Practice Runner',
                'runner_type' => 'claude',
                'command_template' => $claudeTemplate,
                'capabilities' => $reviewCapIds,
            ],
            'patterns' => [
                'name' => 'Design Pattern Reviewer Runner',
                'runner_type' => 'claude',
                'command_template' => $claudeTemplate,
                'capabilities' => $reviewCapIds,
            ],
            'adversarial' => [
                'name' => 'Adversarial Reviewer Runner',
                'runner_type' => 'claude',
                'command_template' => $claudeTemplate,
                'capabilities' => $reviewCapIds,
            ],
            'quality' => [
                'name' => 'Code Quality Reviewer Runner',
                'runner_type' => 'claude',
                'command_template' => $claudeTemplate,
                'capabilities' => $docCapIds,
            ],
        ];

        $profiles = [];
        $workDir = '/Users/garethdaine/Code/agent';

        foreach ($specs as $key => $spec) {
            $profile = DelegateeProfile::updateOrCreate(
                ['user_id' => $user->id, 'name' => $spec['name']],
                [
                    'runner_type' => $spec['runner_type'],
                    'command_template' => $spec['command_template'],
                    'working_directory' => $workDir,
                    'is_active' => true,
                    'trust_score' => 0.85,
                ]
            );

            $profile->capabilities()->sync($spec['capabilities']);
            $profiles[$key] = $profile;
        }

        return $profiles;
    }

    private function createOrgAgents(User $user, array $profiles): array
    {
        $specs = [
            'lead' => [
                'name' => 'Engineering Lead',
                'role_slug' => 'coordinator',
                'role_description' => 'Coordinates the code review process, synthesizes findings from specialist reviewers, produces final reports, and manages escalations.',
                'capability_bindings' => ['code_execution', 'review', 'research'],
            ],
            'solid' => [
                'name' => 'SOLID Analyst',
                'role_slug' => 'solid_reviewer',
                'role_description' => 'Reviews codebase for SOLID principle violations and architectural quality.',
                'capability_bindings' => ['code_execution', 'review', 'research'],
            ],
            'laravel' => [
                'name' => 'Laravel Specialist',
                'role_slug' => 'laravel_reviewer',
                'role_description' => 'Reviews codebase for Laravel best practices, conventions, and framework feature utilization.',
                'capability_bindings' => ['code_execution', 'review', 'research'],
            ],
            'patterns' => [
                'name' => 'Design Pattern Expert',
                'role_slug' => 'pattern_reviewer',
                'role_description' => 'Reviews for proper design pattern usage, DRY violations, and code organization.',
                'capability_bindings' => ['code_execution', 'review', 'research'],
            ],
            'adversarial' => [
                'name' => 'Adversarial Reviewer',
                'role_slug' => 'adversarial_reviewer',
                'role_description' => 'Challenges all findings, identifies false positives, finds bugs and security issues others missed.',
                'capability_bindings' => ['code_execution', 'review', 'research'],
            ],
            'quality' => [
                'name' => 'Quality Inspector',
                'role_slug' => 'quality_reviewer',
                'role_description' => 'Reviews for bugs, dead code, test coverage gaps, performance issues, and maintainability.',
                'capability_bindings' => ['review', 'documentation', 'research'],
            ],
        ];

        $agents = [];

        foreach ($specs as $key => $spec) {
            $agents[$key] = OrgAgentProfile::updateOrCreate(
                ['user_id' => $user->id, 'role_slug' => $spec['role_slug']],
                [
                    'name' => $spec['name'],
                    'role_description' => $spec['role_description'],
                    'delegatee_profile_id' => $profiles[$key]->id,
                    'capability_bindings' => $spec['capability_bindings'],
                    'authority_overrides' => [],
                    'parent_agent_id' => $key === 'lead' ? null : ($agents['lead']->id ?? null),
                ]
            );
        }

        return $agents;
    }

    private function createReportingEdges(User $user, array $agents): void
    {
        $lead = $agents['lead'];

        foreach ($agents as $key => $agent) {
            if ($key === 'lead') {
                continue;
            }

            OrgReportingEdge::updateOrCreate(
                ['subordinate_agent_id' => $agent->id],
                [
                    'manager_agent_id' => $lead->id,
                    'user_id' => $user->id,
                ]
            );
        }
    }

    private function createCouncilTemplate(User $user, array $agents): OrgCouncilTemplate
    {
        $memberList = [];

        foreach ($agents as $key => $agent) {
            $memberList[] = [
                'agent_id' => $agent->id,
                'name' => $agent->name,
                'role' => $agent->role_slug,
                'weight' => match ($key) {
                    'lead' => 2.0,
                    'adversarial' => 1.5,
                    default => 1.0,
                },
                'is_chair' => $key === 'lead',
            ];
        }

        return OrgCouncilTemplate::updateOrCreate(
            ['user_id' => $user->id, 'name' => 'Code Review Council'],
            [
                'description' => 'Deliberation council for code review findings. The Engineering Lead chairs, adversarial reviewer challenges findings, and all members vote on severity and priority of issues.',
                'member_list' => $memberList,
                'evidence_payload_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'findings' => ['type' => 'array', 'description' => 'Review findings from all phases'],
                        'file_paths' => ['type' => 'array', 'description' => 'Files reviewed'],
                        'severity_counts' => ['type' => 'object', 'description' => 'Count by severity'],
                    ],
                ],
                'member_response_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'vote' => ['type' => 'string', 'enum' => ['approve', 'reject', 'abstain']],
                        'severity_assessment' => ['type' => 'string'],
                        'priority_items' => ['type' => 'array'],
                        'dissenting_notes' => ['type' => 'string'],
                    ],
                ],
                'synthesis_mode' => 'weighted',
                'use_model_synthesis' => true,
                'report_sections' => [
                    'executive_summary',
                    'critical_findings',
                    'solid_violations',
                    'laravel_anti_patterns',
                    'design_pattern_issues',
                    'bugs_and_security',
                    'code_quality_metrics',
                    'adversarial_notes',
                    'recommendations',
                ],
            ]
        );
    }

    private function createRitualTemplate(User $user): OrgRitualTemplate
    {
        return OrgRitualTemplate::updateOrCreate(
            ['user_id' => $user->id, 'name' => 'Hourly Codebase Review'],
            [
                'description' => 'Automated hourly code review of /Users/garethdaine/Code/agent. Runs 6 parallel specialist reviewers, followed by adversarial challenge, council deliberation, and report generation.',
                'cron_expression' => '0 * * * *',
                'timezone' => config('app.timezone', 'UTC'),
                'phase_graph' => [
                    [
                        'id' => 'analyze_solid',
                        'name' => 'SOLID Analysis',
                        'depends_on' => [],
                        'instructions' => 'You are a SOLID principles expert reviewing a Laravel 12 codebase. Analyze the codebase for violations of Single Responsibility, Open/Closed, Liskov Substitution, Interface Segregation, and Dependency Inversion principles. For each violation found, produce structured findings including: file path, violation type, severity (critical/high/medium/low), description, and remediation suggestion. Focus on app/Http/Controllers, app/Support, app/Services, and app/Models directories.',
                    ],
                    [
                        'id' => 'analyze_laravel',
                        'name' => 'Laravel Best Practice Review',
                        'depends_on' => [],
                        'instructions' => 'You are a Laravel 12 expert. Review the codebase for Laravel anti-patterns including: missed framework features, incorrect Eloquent usage (N+1 queries, missing scopes), improper service/controller structure, missing FormRequest classes, convention violations, unused routes, and incorrect middleware usage. Produce structured findings with file paths, issue descriptions, and recommended fixes.',
                    ],
                    [
                        'id' => 'analyze_patterns',
                        'name' => 'Design Pattern Review',
                        'depends_on' => [],
                        'instructions' => 'You are a design patterns expert. Review the codebase for: proper design pattern usage, anti-patterns, DRY violations, inappropriate abstraction levels, missing abstractions, over-engineering, and code organization issues. Evaluate whether services, repositories, and support classes follow consistent patterns. Produce structured findings.',
                    ],
                    [
                        'id' => 'analyze_quality',
                        'name' => 'Code Quality Review',
                        'depends_on' => [],
                        'instructions' => 'You are a code quality specialist. Review for: potential bugs, dead code, test coverage gaps, error handling issues, performance problems, naming convention violations, type safety issues, and maintainability concerns. Check for proper use of PHP 8.3+ features. Produce actionable findings with severity ratings.',
                    ],
                    [
                        'id' => 'adversarial_review',
                        'name' => 'Adversarial Review',
                        'depends_on' => ['analyze_solid', 'analyze_laravel', 'analyze_patterns', 'analyze_quality'],
                        'instructions' => 'You are an adversarial code reviewer. Your job is to challenge findings from other reviewers. Identify false positives, over-engineering suggestions, premature abstractions, and cases where simplicity should win over pattern purity. Also independently find bugs, security vulnerabilities, and edge cases others may have missed.',
                    ],
                    [
                        'id' => 'synthesize_report',
                        'name' => 'Report Synthesis',
                        'depends_on' => ['adversarial_review'],
                        'instructions' => 'You are the Engineering Lead. Synthesize all findings from the specialist reviewers and adversarial review into a final gap analysis report. Prioritize findings by impact, remove duplicates, and produce the report in the format specified by the report template. Focus on actionable recommendations.',
                    ],
                ],
                'phase_role_mappings' => [
                    'analyze_solid' => 'solid_reviewer',
                    'analyze_laravel' => 'laravel_reviewer',
                    'analyze_patterns' => 'pattern_reviewer',
                    'analyze_quality' => 'quality_reviewer',
                    'adversarial_review' => 'adversarial_reviewer',
                    'synthesize_report' => 'coordinator',
                ],
                'context_inputs' => [
                    'target_directory' => '/Users/garethdaine/Code/agent',
                    'report_output_directory' => '/Users/garethdaine/Code/agent/docs/review',
                    'focus_areas' => ['SOLID', 'Laravel Best Practices', 'DRY', 'Design Patterns', 'Code Quality', 'Bugs', 'Security'],
                    'exclude_directories' => ['vendor', 'node_modules', 'storage', '.git'],
                ],
                'verification_strategy' => 'ai_critic',
                'delivery_targets' => ['file'],
                'escalation_timeout_seconds' => 1800,
                'notification_level' => 'all',
                'is_paused' => false,
            ]
        );
    }
}
