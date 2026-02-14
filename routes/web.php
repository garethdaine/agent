<?php

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

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
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

    Route::get('/tools/discovery/{id}', function (int $id) {
        return Inertia::render('Tools/Discovery/Wizard', [
            'sessionId' => $id,
        ]);
    })->name('tools.discovery.wizard');
});
