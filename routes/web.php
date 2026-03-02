<?php

use App\Http\Controllers\Messenger\AccountLinkController;
use App\Http\Controllers\Messenger\DeadLetterController;
use App\Http\Controllers\Messenger\MessengerHealthController;
use App\Http\Controllers\TaskProviderOAuthController;
use App\Http\Controllers\Docs\DocsPageController;
use App\Models\RepoAnalysisSession;
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

Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/messenger/link/{token}', [AccountLinkController::class, 'store'])
        ->name('messenger.link.store');
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
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

    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    Route::get('/docs', [DocsPageController::class, 'index'])
        ->name('docs.index');
    Route::get('/docs/{slug}', [DocsPageController::class, 'show'])
        ->name('docs.show');

    Route::get('/agent/jobs', function () {
        return Inertia::render('Agent/Jobs/Index');
    })->name('agent.jobs.index');

    Route::get('/agent/jobs/create', function () {
        return Inertia::render('Agent/Jobs/Create', [
            'config' => [
                'targeted_retry' => config('agent.targeted_retry'),
                'star_preamble' => config('agent.star_preamble'),
            ],
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

    Route::get('/tools', function () {
        $user = request()->user();
        $repoAnalysisAvailable = $user !== null
            && (bool) config('repo_analysis.enabled', false)
            && Gate::forUser($user)->allows('create', RepoAnalysisSession::class);

        return Inertia::render('Tools/Index', [
            'repoAnalysis' => [
                'available' => $repoAnalysisAvailable,
                'indexRoute' => $repoAnalysisAvailable ? route('tools.repo-analysis.index') : null,
                'blockedMessage' => $repoAnalysisAvailable
                    ? null
                    : 'Repo Analysis is unavailable. Enable the feature or request access from an administrator.',
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

    Route::get('/tools/discovery/{id}', function (int $id) {
        return Inertia::render('Tools/Discovery/Wizard', [
            'sessionId' => $id,
        ]);
    })->name('tools.discovery.wizard');

    Route::get('/tools/repo-analysis', function () {
        if (! (bool) config('repo_analysis.enabled', false)) {
            return response('Repo Analysis is currently disabled. Enable REPO_ANALYSIS_ENABLED to access this tool.', 403);
        }

        $user = request()->user();
        if ($user === null || ! Gate::forUser($user)->allows('create', RepoAnalysisSession::class)) {
            return response('You do not have access to Repo Analysis. Ask an administrator for access.', 403);
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
                'phase' => (int) $session->phase,
                'status' => (string) $session->status,
                'report_count' => (int) ($session->reports_count ?? 0),
                'updated_at' => optional($session->updated_at)?->toIso8601String(),
                'wizard_url' => route('tools.repo-analysis.wizard', $session->id),
                'settings_url' => route('tools.repo-analysis.settings', $session->id),
            ])
            ->values();

        return Inertia::render('Tools/RepoAnalysis/Index', [
            'sessions' => $sessions,
            'viewer' => [
                'user_id' => (int) $user->id,
                'is_admin' => $user->hasRole('admin'),
                'can_create' => true,
            ],
            'defaultProjectDirectory' => base_path(),
        ]);
    })->name('tools.repo-analysis.index');

    Route::get('/tools/repo-analysis/create', function () {
        if (! (bool) config('repo_analysis.enabled', false)) {
            return response('Repo Analysis is currently disabled. Enable REPO_ANALYSIS_ENABLED to access this tool.', 403);
        }

        $user = request()->user();
        if ($user === null || ! Gate::forUser($user)->allows('create', RepoAnalysisSession::class)) {
            return response('You do not have access to Repo Analysis. Ask an administrator for access.', 403);
        }

        return Inertia::render('Tools/RepoAnalysis/Create', [
            'defaultProjectDirectory' => base_path(),
        ]);
    })->name('tools.repo-analysis.create');

    Route::get('/tools/repo-analysis/{id}', function (int $id) {
        if (! (bool) config('repo_analysis.enabled', false)) {
            return response('Repo Analysis is currently disabled. Enable REPO_ANALYSIS_ENABLED to access this tool.', 403);
        }

        $user = request()->user();
        $session = RepoAnalysisSession::query()->findOrFail($id);

        if ($user === null || ! Gate::forUser($user)->allows('view', $session)) {
            return response('You do not have access to this Repo Analysis session. Ask an administrator if you need access.', 403);
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

        return Inertia::render('Tools/RepoAnalysis/Wizard', [
            'sessionId' => (int) $session->id,
            'initialSession' => [
                'id' => (int) $session->id,
                'user_id' => (int) $session->user_id,
                'name' => $session->name,
                'project_directory' => $session->project_directory,
                'analyzer_profile' => $session->analyzer_profile,
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
    })->name('tools.repo-analysis.wizard');

    Route::get('/tools/repo-analysis/{id}/settings', function (int $id) {
        if (! (bool) config('repo_analysis.enabled', false)) {
            return response('Repo Analysis is currently disabled. Enable REPO_ANALYSIS_ENABLED to access this tool.', 403);
        }

        $user = request()->user();
        $session = RepoAnalysisSession::query()->findOrFail($id);

        if ($user === null || ! Gate::forUser($user)->allows('update', $session)) {
            return response('You do not have access to this Repo Analysis session. Ask an administrator if you need access.', 403);
        }

        return Inertia::render('Tools/RepoAnalysis/Settings', [
            'sessionId' => (int) $session->id,
        ]);
    })->name('tools.repo-analysis.settings');

    // Org Layer routes (guarded by org UI feature flag)
    Route::middleware(['org.ui'])->prefix('agent/org')->group(function () {
        Route::get('/', fn () => Inertia::render('Agent/Org/Index'))
            ->name('org.index');
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
});
