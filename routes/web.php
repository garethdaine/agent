<?php

use App\Http\Controllers\Messenger\AccountLinkController;
use App\Http\Controllers\Messenger\DeadLetterController;
use App\Http\Controllers\Messenger\MessengerHealthController;
use App\Http\Controllers\TaskProviderOAuthController;
use Illuminate\Foundation\Application;
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
        return Inertia::render('Tools/Index');
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
