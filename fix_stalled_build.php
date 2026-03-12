<?php

// Find stalled build sessions
$sessions = \App\Models\InterrogationSession::query()
    ->whereJsonContains('metadata_json->build->status', 'running')
    ->get(['id', 'name', 'status', 'metadata_json']);

if ($sessions->isEmpty()) {
    echo "No stalled builds found with build.status = 'running'\n";
    // Try broader search
    $all = \App\Models\InterrogationSession::query()
        ->whereNotNull('metadata_json')
        ->orderByDesc('id')
        ->limit(10)
        ->get(['id', 'name', 'status', 'metadata_json']);

    foreach ($all as $s) {
        $build = $s->metadata_json['build'] ?? [];
        $buildStatus = $build['status'] ?? 'n/a';
        echo "Session {$s->id}: {$s->name} | session.status={$s->status} | build.status={$buildStatus}\n";
    }

    return;
}

foreach ($sessions as $s) {
    $build = $s->metadata_json['build'] ?? [];
    $taskCount = $s->buildTasks()->count();
    $completedCount = $s->buildTasks()->where('status', 'completed')->count();
    $pendingCount = $s->buildTasks()->where('status', 'pending')->count();
    $inProgressCount = $s->buildTasks()->where('status', 'in_progress')->count();
    $failedCount = $s->buildTasks()->where('status', 'failed')->count();

    echo "Session {$s->id}: {$s->name}\n";
    echo "  session.status = {$s->status}\n";
    echo "  build.status   = {$build['status']}\n";
    echo "  tasks: {$taskCount} total, {$completedCount} completed, {$pendingCount} pending, {$inProgressCount} in_progress, {$failedCount} failed\n";
    echo "\n";
}
