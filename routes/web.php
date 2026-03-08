<?php

declare(strict_types=1);

use App\Http\Controllers\Agent\OperatorPageController;
use App\Http\Controllers\Api\TunnelStatusController;
use App\Http\Controllers\Docs\DocsPageController;
use App\Http\Controllers\Messenger\AccountLinkController;
use App\Http\Controllers\Messenger\DeadLetterController;
use App\Http\Controllers\Messenger\MessengerHealthController;
use App\Http\Controllers\RuntimeWebController;
use App\Http\Controllers\Settings\TunnelController;
use App\Http\Controllers\TaskProviderOAuthController;
use App\Models\RepoAnalysisSession;
use App\Support\Agent\FeatureFlagManager;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

// Messenger account linking routes
Route::get('/messenger/link/{token}', [AccountLinkController::class, 'show'])
    ->name('messenger.link.show');

// Public messenger health endpoint for monitoring tools (no auth required)
Route::get('/messenger/health', [MessengerHealthController::class, 'index'])
    ->name('messenger.health');

Route::get('/agent/health/deployment', \App\Http\Controllers\DeploymentHealthController::class)
    ->name('agent.health.deployment');

Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/messenger/link/{token}', [AccountLinkController::class, 'store'])
        ->name('messenger.link.store');
});

Route::middleware(['auth:sanctum', config('jetstream.auth_session')])->group(function () {
    Route::get('/license/required', fn () => Inertia::render('License/Unlicensed'))
        ->name('license.required');
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
    'onboarding',
    'license',
])->group(function () {
    Route::get('/integrations/oauth/{provider}/callback', [TaskProviderOAuthController::class, 'callback'])
        ->name('integrations.oauth.callback');

    // Backward compatibility for previously configured OAuth callback URLs.
    Route::get('/tools/discovery/providers/{driver}/oauth/callback', function (string $driver) {
        return redirect()->route('integrations.oauth.callback', [
            'provider' => $driver,
            ...request()->query(),
        ]);
    })->name('tools.discovery.provider.callback');

    Route::get('/dashboard', \App\Http\Controllers\DashboardController::class)
        ->name('dashboard');

    Route::get('/billing/portal', \App\Http\Controllers\BillingPortalController::class)
        ->name('billing.portal');

    Route::get('/onboarding', [\App\Http\Controllers\Onboarding\OnboardingController::class, 'welcome'])
        ->name('onboarding.welcome');
    Route::post('/onboarding/complete', [\App\Http\Controllers\Onboarding\OnboardingController::class, 'complete'])
        ->name('onboarding.complete');
    Route::get('/onboarding/first-job', [\App\Http\Controllers\Onboarding\OnboardingController::class, 'firstJob'])
        ->name('onboarding.first-job');

    Route::get('/docs', [DocsPageController::class, 'index'])
        ->name('docs.index');
    Route::get('/docs/{slug}', [DocsPageController::class, 'show'])
        ->name('docs.show')
        ->where('slug', '^(?!api\.json$|api$).*');

    Route::get('/agent/jobs', function () {
        return Inertia::render('Agent/Jobs/Index');
    })->name('agent.jobs.index');

    Route::get('/agent/jobs/create', function () {
        $templates = config('vertical_templates.templates', []);
        $verticalTemplates = collect($templates)->map(fn ($t, $key) => [
            'key' => $key,
            'name' => $t['name'] ?? $key,
            'description' => $t['description'] ?? '',
            'cron_expression' => $t['cron_expression'] ?? '0 9 * * *',
            'runner_type' => $t['runner_type'] ?? 'codex',
        ])->values()->all();

        return Inertia::render('Agent/Jobs/Create', [
            'config' => [
                'targeted_retry' => config('agent.targeted_retry'),
                'star_preamble' => config('agent.star_preamble'),
            ],
            'verticalTemplates' => $verticalTemplates,
        ]);
    })->name('agent.jobs.create');

    Route::get('/agent/jobs/{id}/edit', function (int $id) {
        return Inertia::render('Agent/Jobs/Edit', [
            'jobId' => $id,
            'config' => [
                'targeted_retry' => config('agent.targeted_retry'),
                'star_preamble' => config('agent.star_preamble'),
            ],
        ]);
    })->name('agent.jobs.edit');

    Route::get('/agent/monitor', function () {
        return Inertia::render('Agent/Monitor/Index');
    })->name('agent.monitor.index');

    // Legacy route: redirect to unified dashboard.
    Route::get('/agent/operator-dashboard', fn () => redirect()->route('dashboard'))
        ->name('agent.operator-dashboard');

    Route::get('/help', fn () => redirect()->route('docs.index'))
        ->name('help');

    Route::get('/agent/deployments', [OperatorPageController::class, 'deployments'])
        ->name('agent.deployments.index');
    Route::get('/agent/deployments/{workflowKey}', [OperatorPageController::class, 'deployment'])
        ->name('agent.deployments.show');
    Route::get('/agent/system-overview', [OperatorPageController::class, 'systemOverview'])
        ->name('agent.system-overview.index');
    Route::get('/agent/escalations', [OperatorPageController::class, 'escalations'])
        ->name('agent.escalations.index');
    Route::get('/agent/budgets', [OperatorPageController::class, 'budgets'])
        ->name('agent.budgets.index');
    Route::get('/agent/replay-builds', [OperatorPageController::class, 'replayBuilds'])
        ->name('agent.replay-builds.index');
    Route::get('/agent/replay-builds/{buildId}', [OperatorPageController::class, 'replayBuild'])
        ->name('agent.replay-builds.show');

    Route::get('/tools', function () {
        $user = request()->user();
        $flags = app(FeatureFlagManager::class);
        $codeAnalysisAvailable = $user !== null
            && $flags->enabled(FeatureFlagManager::REPO_ANALYSIS_ENABLED)
            && Gate::forUser($user)->allows('create', RepoAnalysisSession::class);

        return Inertia::render('Tools/Index', [
            'codeAnalysis' => [
                'available' => $codeAnalysisAvailable,
                'indexRoute' => $codeAnalysisAvailable ? route('tools.code-analysis.index') : null,
                'blockedMessage' => $codeAnalysisAvailable
                    ? null
                    : 'Code Analysis is unavailable. Enable the feature or request access from an administrator.',
            ],
            'skills' => [
                'available' => $flags->enabled(FeatureFlagManager::SKILLS_ENABLED)
                    && $flags->enabled(FeatureFlagManager::SKILLS_UI_ENABLED),
            ],
        ]);
    })->name('tools.index');

    Route::get('/tools/discovery', function () {
        return Inertia::render('Tools/Discovery/Index');
    })->name('tools.discovery.index');

    Route::get('/tools/discovery/create', function () {
        return Inertia::render('Tools/Discovery/Create');
    })->name('tools.discovery.create');

    // Backward compatibility for legacy links.
    Route::get('/tools/discovery/new', function () {
        return redirect()->route('tools.discovery.create');
    });

    Route::get('/tools/discovery/settings', function () {
        return Inertia::render('Tools/Discovery/Settings');
    })->name('tools.discovery.settings');

    Route::get('/tools/discovery/{id}/settings', function (int $id) {
        return Inertia::render('Tools/Discovery/SessionSettings', [
            'sessionId' => $id,
        ]);
    })->name('tools.discovery.session.settings');

    Route::get('/tools/backups/settings', function () {
        return Inertia::render('Tools/Backups/Settings');
    })->name('tools.backups.settings');

    Route::get('/tools/features/settings', function () {
        return Inertia::render('Tools/Features/Settings');
    })->name('tools.features.settings');

    Route::get('/tools/security', function () {
        return Inertia::render('Tools/Security/Index');
    })->name('tools.security.index');

    Route::get('/tools/diagnostics', function () {
        return Inertia::render('Tools/Diagnostics/Index');
    })->name('tools.diagnostics.index');

    Route::get('/tools/services', function () {
        return Inertia::render('Tools/Services/Index');
    })->name('tools.services.index');

    Route::get('/tools/logs', function () {
        return Inertia::render('Tools/Logs/Index');
    })->name('tools.logs.index');

    // Legacy route: redirect runtime to dashboard.
    Route::get('/tools/runtime', fn () => redirect()->route('dashboard'))
        ->name('tools.runtime.index');

    Route::get('/tools/audit', function () {
        return Inertia::render('Tools/Audit/Index');
    })->name('tools.audit.index');

    Route::get('/messenger/pairings', function () {
        return Inertia::render('Messenger/Pairings/Index');
    })->name('messenger.pairings.index');

    Route::get('/messenger/sessions', function () {
        return Inertia::render('Messenger/Sessions/Index');
    })->name('messenger.sessions.index');

    Route::get('/settings/configuration', function () {
        return Inertia::render('Settings/Configuration/Index');
    })->name('settings.configuration.index');

    Route::get('/settings/credentials', function () {
        return Inertia::render('Settings/Secrets/Index');
    })->name('settings.credentials.index');

    Route::get('/tools/messenger', function () {
        return Inertia::render('Tools/Messenger/Index');
    })->name('tools.messenger.index');

    // Memory routes
    Route::get('/tools/memory', function () {
        return Inertia::render('Tools/Memory/Index');
    })->name('tools.memory.index');

    Route::get('/tools/memory/settings', function () {
        return Inertia::render('Tools/Memory/Settings');
    })->name('tools.memory.settings');

    // Skills routes (guarded by skills UI feature flag)
    Route::middleware(['skills.ui'])->group(function () {
        Route::get('/tools/skills', function () {
            return Inertia::render('Tools/Skills/Index');
        })->name('tools.skills.index');

        Route::get('/tools/skills/install', function () {
            return Inertia::render('Tools/Skills/Install');
        })->name('tools.skills.install');

        Route::get('/tools/skills/library', function () {
            return Inertia::render('Tools/Skills/Library');
        })->name('tools.skills.library');

        Route::get('/tools/skills/{id}', function (string $id) {
            return Inertia::render('Tools/Skills/Show', ['skillId' => $id]);
        })->name('tools.skills.show');
    });

    // Connectors routes (guarded by connectors UI feature flag)
    Route::middleware(['connectors.ui'])->group(function () {
        Route::get('/tools/connectors', function () {
            return Inertia::render('Tools/Connectors/Index');
        })->name('tools.connectors.index');

        Route::get('/tools/connectors/library', function () {
            return Inertia::render('Tools/Connectors/Library');
        })->name('tools.connectors.library');

        Route::get('/tools/connectors/{id}', function (string $id) {
            return Inertia::render('Tools/Connectors/Show', ['connectorId' => $id]);
        })->name('tools.connectors.show');
    });

    // Messenger health dashboard (authenticated)
    Route::get('/messenger/health/dashboard', [MessengerHealthController::class, 'dashboard'])
        ->name('messenger.health.dashboard');

    // Messenger dead-letter queue routes
    Route::get('/messenger/dead-letters', [DeadLetterController::class, 'index'])
        ->name('messenger.dead-letters.index');
    Route::get('/messenger/dead-letters/{id}', [DeadLetterController::class, 'show'])
        ->name('messenger.dead-letters.show');
    Route::post('/messenger/dead-letters/{id}/retry', [DeadLetterController::class, 'retry'])
        ->name('messenger.dead-letters.retry');
    Route::post('/messenger/dead-letters/retry-bulk', [DeadLetterController::class, 'retryBulk'])
        ->name('messenger.dead-letters.retry-bulk');
    Route::delete('/messenger/dead-letters/{id}', [DeadLetterController::class, 'destroy'])
        ->name('messenger.dead-letters.destroy');

    // Messenger runtime sessions routes
    Route::get('/messenger/runtime', [RuntimeWebController::class, 'index'])
        ->name('messenger.runtime.index');
    Route::get('/messenger/runtime/{id}', [RuntimeWebController::class, 'show'])
        ->name('messenger.runtime.show');

    Route::get('/tools/discovery/{id}', function (int $id) {
        return Inertia::render('Tools/Discovery/Wizard', [
            'sessionId' => $id,
        ]);
    })->name('tools.discovery.wizard');

    Route::get('/tools/code-analysis', function () {
        if (! app(FeatureFlagManager::class)->enabled(FeatureFlagManager::REPO_ANALYSIS_ENABLED)) {
            return response('Code Analysis is currently disabled. Enable REPO_ANALYSIS_ENABLED to access this tool.', 403);
        }

        $user = request()->user();
        if ($user === null || ! Gate::forUser($user)->allows('create', RepoAnalysisSession::class)) {
            return response('You do not have access to Code Analysis. Ask an administrator for access.', 403);
        }

        $query = RepoAnalysisSession::query()->withCount('reports')->latest();
        if (! $user->hasRole('admin')) {
            $query->where('user_id', (int) $user->id);
        }

        $sessions = $query
            ->limit(50)
            ->get()
            ->map(static fn (RepoAnalysisSession $session): array => [
                'id' => (int) $session->id,
                'user_id' => (int) $session->user_id,
                'name' => $session->name,
                'project_directory' => $session->project_directory,
                'analyzer_profile' => $session->analyzer_profile,
                'runner_type' => (string) $session->runner_type,
                'phase' => (int) $session->phase,
                'status' => (string) $session->status,
                'report_count' => (int) ($session->reports_count ?? 0),
                'updated_at' => optional($session->updated_at)?->toIso8601String(),
                'wizard_url' => route('tools.code-analysis.wizard', $session->id),
                'settings_url' => route('tools.code-analysis.settings', $session->id),
            ])
            ->values();

        return Inertia::render('Tools/CodeAnalysis/Index', [
            'sessions' => $sessions,
            'viewer' => [
                'user_id' => (int) $user->id,
                'is_admin' => $user->hasRole('admin'),
                'can_create' => true,
            ],
            'defaultProjectDirectory' => base_path(),
        ]);
    })->name('tools.code-analysis.index');

    Route::get('/tools/code-analysis/create', function () {
        if (! app(FeatureFlagManager::class)->enabled(FeatureFlagManager::REPO_ANALYSIS_ENABLED)) {
            return response('Code Analysis is currently disabled. Enable REPO_ANALYSIS_ENABLED to access this tool.', 403);
        }

        $user = request()->user();
        if ($user === null || ! Gate::forUser($user)->allows('create', RepoAnalysisSession::class)) {
            return response('You do not have access to Code Analysis. Ask an administrator for access.', 403);
        }

        return Inertia::render('Tools/CodeAnalysis/Create', [
            'defaultProjectDirectory' => base_path(),
        ]);
    })->name('tools.code-analysis.create');

    Route::get('/tools/code-analysis/{id}', function (int $id) {
        if (! app(FeatureFlagManager::class)->enabled(FeatureFlagManager::REPO_ANALYSIS_ENABLED)) {
            return response('Code Analysis is currently disabled. Enable REPO_ANALYSIS_ENABLED to access this tool.', 403);
        }

        $user = request()->user();
        $session = RepoAnalysisSession::query()->findOrFail($id);

        if ($user === null || ! Gate::forUser($user)->allows('view', $session)) {
            return response('You do not have access to this Code Analysis session. Ask an administrator if you need access.', 403);
        }

        $canMutate = Gate::forUser($user)->allows('update', $session);
        $runningStatuses = [
            'snapshotting',
            'planning',
            'executing',
            'validating',
            'reporting',
        ];

        $isRunning = in_array((string) $session->status, $runningStatuses, true);
        $hasExport = $session->reports()->exists();

        return Inertia::render('Tools/CodeAnalysis/Wizard', [
            'sessionId' => (int) $session->id,
            'initialSession' => [
                'id' => (int) $session->id,
                'user_id' => (int) $session->user_id,
                'name' => $session->name,
                'project_directory' => $session->project_directory,
                'analyzer_profile' => $session->analyzer_profile,
                'runner_type' => (string) $session->runner_type,
                'phase' => (int) $session->phase,
                'status' => (string) $session->status,
                'error_code' => $session->error_code,
                'error_summary' => $session->error_summary,
                'metadata' => $session->metadata_json ?? [],
                'updated_at' => optional($session->updated_at)?->toIso8601String(),
            ],
            'viewer' => [
                'user_id' => (int) $user->id,
                'is_admin_override' => $user->hasRole('admin') && (int) $session->user_id !== (int) $user->id,
                'can_mutate' => $canMutate,
            ],
            'actionVisibility' => [
                'pause' => $canMutate && $isRunning,
                'resume' => $canMutate && (string) $session->status === 'paused',
                'retry' => $canMutate && (string) $session->status === 'failed',
                'restart' => $canMutate,
                'export' => $hasExport,
            ],
        ]);
    })->name('tools.code-analysis.wizard');

    Route::get('/tools/code-analysis/{id}/settings', function (int $id) {
        if (! app(FeatureFlagManager::class)->enabled(FeatureFlagManager::REPO_ANALYSIS_ENABLED)) {
            return response('Code Analysis is currently disabled. Enable REPO_ANALYSIS_ENABLED to access this tool.', 403);
        }

        $user = request()->user();
        $session = RepoAnalysisSession::query()->findOrFail($id);

        if ($user === null || ! Gate::forUser($user)->allows('update', $session)) {
            return response('You do not have access to this Code Analysis session. Ask an administrator if you need access.', 403);
        }

        return Inertia::render('Tools/CodeAnalysis/Settings', [
            'sessionId' => (int) $session->id,
        ]);
    })->name('tools.code-analysis.settings');

    // Workforce routes (guarded by org UI feature flag)
    Route::middleware(['org.ui'])->prefix('agent/org')->group(function () {
        Route::get('/', fn () => Inertia::render('Agent/Org/Index'))
            ->name('org.index');
        Route::get('/builder', fn () => Inertia::render('Agent/Org/OrgLayerBuilder'))
            ->name('org.builder');
        Route::get('/agents', fn () => Inertia::render('Agent/Org/Agents/Index'))
            ->name('org.agents.index');
        Route::get('/agents/create', fn () => Inertia::render('Agent/Org/Agents/Create'))
            ->name('org.agents.create');
        Route::get('/agents/{id}/edit', fn (string $id) => Inertia::render('Agent/Org/Agents/Edit', ['agentId' => $id]))
            ->name('org.agents.edit');
        Route::get('/rituals', fn () => Inertia::render('Agent/Org/Rituals/Index'))
            ->name('org.rituals.index');
        Route::get('/rituals/create', fn () => Inertia::render('Agent/Org/Rituals/Create'))
            ->name('org.rituals.create');
        Route::get('/rituals/{id}', fn (string $id) => Inertia::render('Agent/Org/Rituals/Show', ['ritualId' => $id]))
            ->name('org.rituals.show');
        Route::get('/rituals/{ritualId}/runs/{runId}', fn (string $ritualId, string $runId) => Inertia::render('Agent/Org/Rituals/RunDetail', [
            'ritualId' => $ritualId,
            'runId' => $runId,
        ]))->name('org.rituals.runs.show');
        Route::get('/councils', fn () => Inertia::render('Agent/Org/Councils/Index'))
            ->name('org.councils.index');
        Route::get('/councils/create', fn () => Inertia::render('Agent/Org/Councils/Create'))
            ->name('org.councils.create');
        Route::get('/escalations', fn () => Inertia::render('Agent/Org/Escalations/Index'))
            ->name('org.escalations.index');
        Route::get('/costs', fn () => Inertia::render('Agent/Org/Costs/Index'))
            ->name('org.costs.index');
    });

    // Delegation routes (guarded by delegation UI feature flag)
    Route::middleware(['delegation.ui'])->group(function () {
        Route::get('/agent/delegation', fn () => Inertia::render('Agent/Delegation/Index'))
            ->name('agent.delegation.index');
        Route::get('/agent/delegation/create', fn () => Inertia::render('Agent/Delegation/Create'))
            ->name('agent.delegation.create');
        Route::get('/agent/delegation/graphs/builder', fn () => Inertia::render('Agent/Delegation/GraphBuilder'))
            ->name('agent.delegation.graphs.builder');
        Route::get('/agent/delegation/{id}/builder', fn (int $id) => Inertia::render('Agent/Delegation/GraphBuilder', ['graphId' => $id]))
            ->name('agent.delegation.graphs.builder.edit');
        Route::get('/agent/delegation/{id}', fn (int $id) => Inertia::render('Agent/Delegation/Show', ['graphId' => $id]))
            ->name('agent.delegation.show');
        Route::get('/agent/delegation/{graphId}/tasks/{taskId}', fn (int $graphId, int $taskId) => Inertia::render('Agent/Delegation/TaskDetail', ['graphId' => $graphId, 'taskId' => $taskId]))
            ->name('agent.delegation.task');
        Route::get('/agent/delegation/{graphId}/tasks/{taskId}/approve', fn (int $graphId, int $taskId) => Inertia::render('Agent/Delegation/VerificationApproval', ['graphId' => $graphId, 'taskId' => $taskId]))
            ->name('agent.delegation.task.approve');
        Route::get('/agent/delegatee-profiles', fn () => Inertia::render('Agent/Delegation/ProfileIndex'))
            ->name('agent.delegation.profiles.index');
        Route::get('/agent/delegatee-profiles/create', fn () => Inertia::render('Agent/Delegation/ProfileForm'))
            ->name('agent.delegation.profiles.create');
        Route::get('/agent/delegatee-profiles/{id}/edit', fn (int $id) => Inertia::render('Agent/Delegation/ProfileForm', ['profileId' => $id]))
            ->name('agent.delegation.profiles.edit');
    });

    Route::middleware(['office.ui'])->group(function () {
        Route::get('/agent/office', fn () => Inertia::render('Agent/Office/AgentOffice'))
            ->name('agent.office');
    });

    // Tunnel routes
    Route::middleware(['tunnel.feature'])->group(function () {
        Route::get('/settings/tunnel', [TunnelController::class, 'index'])
            ->name('settings.tunnel.index');
        Route::get('/settings/tunnel/wizard', [TunnelController::class, 'wizard'])
            ->name('settings.tunnel.wizard');
        Route::post('/settings/tunnel', [TunnelController::class, 'update'])
            ->name('settings.tunnel.update');
        Route::post('/settings/tunnel/start', [TunnelController::class, 'start'])
            ->name('settings.tunnel.start');
        Route::post('/settings/tunnel/stop', [TunnelController::class, 'stop'])
            ->name('settings.tunnel.stop');
        Route::post('/settings/tunnel/delete', [TunnelController::class, 'delete'])
            ->name('settings.tunnel.delete');
        Route::post('/settings/tunnel/install-check', [TunnelController::class, 'installCheck'])
            ->name('settings.tunnel.install-check');
        Route::post('/settings/tunnel/install', [TunnelController::class, 'install'])
            ->name('settings.tunnel.install');
        Route::post('/settings/tunnel/auth-start', [TunnelController::class, 'authStart'])
            ->name('settings.tunnel.auth-start');
        Route::post('/settings/tunnel/auth-poll', [TunnelController::class, 'authPoll'])
            ->name('settings.tunnel.auth-poll');
        Route::post('/settings/tunnel/create', [TunnelController::class, 'createTunnel'])
            ->name('settings.tunnel.create');
        Route::post('/settings/tunnel/use-existing', [TunnelController::class, 'useExistingTunnel'])
            ->name('settings.tunnel.use-existing');
        Route::post('/settings/tunnel/route-dns', [TunnelController::class, 'routeDns'])
            ->name('settings.tunnel.route-dns');
        Route::post('/settings/tunnel/access-token', [TunnelController::class, 'generateAccessToken'])
            ->name('settings.tunnel.access-token');
        Route::get('/api/tunnel/status', TunnelStatusController::class)
            ->name('api.tunnel.status');
    });
});
