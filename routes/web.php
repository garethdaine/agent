<?php

use App\Http\Controllers\Messenger\AccountLinkController;
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
        return Inertia::render('Agent/Jobs/Create');
    })->name('agent.jobs.create');

    Route::get('/agent/jobs/{id}/edit', function (int $id) {
        return Inertia::render('Agent/Jobs/Edit', [
            'jobId' => $id,
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

    Route::get('/tools/discovery/new', function () {
        return Inertia::render('Tools/Discovery/Create');
    })->name('tools.discovery.create');

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

    Route::get('/tools/discovery/{id}', function (int $id) {
        return Inertia::render('Tools/Discovery/Wizard', [
            'sessionId' => $id,
        ]);
    })->name('tools.discovery.wizard');

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
