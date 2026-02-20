<?php

namespace App\Support\TaskProviders;

use App\Models\ConnectedProvider;
use App\Models\InterrogationBuildTask;
use App\Models\InterrogationSession;
use App\Support\TaskProviders\Contracts\TaskManagementProviderDriver;
use Carbon\CarbonImmutable;

class InterrogationTaskProviderSyncService
{
    public function __construct(
        private readonly TaskManagementProviderManager $providerManager,
    ) {}

    public function syncBuildTasks(InterrogationSession $session): void
    {
        $provider = $this->taskManagementProviderForSession($session);
        if ($provider === null) {
            return;
        }

        $driver = $this->providerManager->driver((string) $provider->driver);
        $tasks = $session->buildTasks()->ordered()->get();

        if ($tasks->isEmpty()) {
            return;
        }

        $build = $this->buildMetadata($session);
        $sync = $this->providerSyncState($build);
        $sync['driver'] = $driver->key();
        $sync['status'] = 'syncing';
        $sync['error'] = null;
        $sync['updated_at'] = CarbonImmutable::now('UTC')->toIso8601String();

        $projectId = trim((string) ($sync['project_id'] ?? ''));
        $projectSelection = $this->providerProjectSelection($provider);
        $sync['project_mode'] = $projectSelection['mode'];

        if ($projectId === '') {
            if ($projectSelection['mode'] === 'existing' && $projectSelection['project_id'] !== null) {
                $projectId = $projectSelection['project_id'];
                $sync['project_id'] = $projectId;
                $sync['project_name'] = $projectSelection['project_name'];
                $sync['project_url'] = $projectSelection['project_url'];
            } else {
                $project = $driver->createProject(
                    $provider,
                    $session,
                    $this->buildProjectName($session),
                );

                $projectId = trim((string) ($project['id'] ?? ''));
                $sync['project_id'] = $projectId;
                $sync['project_name'] = $project['name'] ?? null;
                $sync['project_url'] = $project['url'] ?? null;
            }
        }

        $syncedCount = 0;

        foreach ($tasks as $task) {
            $link = $this->taskProviderLink($task, $driver);
            if (trim((string) ($link['external_task_id'] ?? '')) !== '') {
                $syncedCount++;

                continue;
            }

            $priority = $this->resolveTaskPriority($task);
            $labels = $this->resolveTaskLabels($session, $task);
            $issue = $driver->createTask(
                $provider,
                $session,
                $task,
                $projectId,
                $priority,
                $labels,
                $this->buildTaskDescription($session, $task, $labels),
            );

            $this->storeTaskProviderLink($task, $driver, [
                'external_task_id' => (string) ($issue['id'] ?? ''),
                'external_task_identifier' => $issue['identifier'] ?? null,
                'external_task_url' => $issue['url'] ?? null,
                'priority' => $priority,
                'labels' => $labels,
                'synced_at' => CarbonImmutable::now('UTC')->toIso8601String(),
                'status_synced_at' => null,
                'status_sync_error' => null,
                'last_synced_status' => null,
            ]);

            $syncedCount++;
        }

        $sync['status'] = 'synced';
        $sync['synced_task_count'] = $syncedCount;
        $sync['error'] = null;
        $sync['updated_at'] = CarbonImmutable::now('UTC')->toIso8601String();

        $build['task_provider_sync'] = $sync;
        $this->saveBuildMetadata($session, $build);
    }

    public function syncTaskStatus(InterrogationSession $session, InterrogationBuildTask $task): void
    {
        $provider = $this->taskManagementProviderForSession($session);
        if ($provider === null) {
            return;
        }

        $driver = $this->providerManager->driver((string) $provider->driver);
        $link = $this->taskProviderLink($task, $driver);
        $externalTaskId = trim((string) ($link['external_task_id'] ?? ''));

        if ($externalTaskId === '') {
            return;
        }

        $note = null;
        if (is_string($task->last_error) && trim($task->last_error) !== '') {
            $note = 'Latest build execution note: '.trim($task->last_error);
        }

        $driver->updateTaskStatus(
            $provider,
            $session,
            $externalTaskId,
            (string) $task->status,
            $note,
        );

        $this->storeTaskProviderLink($task, $driver, [
            ...$link,
            'status_synced_at' => CarbonImmutable::now('UTC')->toIso8601String(),
            'status_sync_error' => null,
            'last_synced_status' => (string) $task->status,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function markSyncFailed(InterrogationSession $session, string $message): array
    {
        $build = $this->buildMetadata($session);
        $sync = $this->providerSyncState($build);
        $sync['status'] = 'failed';
        $sync['error'] = mb_substr(trim($message), 0, 1000);
        $sync['updated_at'] = CarbonImmutable::now('UTC')->toIso8601String();

        $build['task_provider_sync'] = $sync;
        $this->saveBuildMetadata($session, $build);

        return $sync;
    }

    private function buildProjectName(InterrogationSession $session): string
    {
        $name = trim((string) ($session->name ?? ''));

        return $name !== ''
            ? sprintf('Agent Build: %s (S%d)', mb_substr($name, 0, 60), (int) $session->id)
            : sprintf('Agent Build Session %d', (int) $session->id);
    }

    /**
     * @param  array<int, string>  $labels
     */
    private function buildTaskDescription(InterrogationSession $session, InterrogationBuildTask $task, array $labels): string
    {
        $lines = [
            'Session: #'.(int) $session->id,
            'Task Sequence: '.(int) $task->sequence,
            'Task Status: '.(string) $task->status,
        ];

        if ($labels !== []) {
            $lines[] = 'Labels: '.implode(', ', $labels);
        }

        $description = trim((string) ($task->description ?? ''));
        $instructions = trim((string) ($task->instructions_markdown ?? ''));

        $content = ['## Agent Build Task', '', implode("\n", $lines), ''];

        if ($description !== '') {
            $content[] = '## Objective';
            $content[] = '';
            $content[] = $description;
            $content[] = '';
        }

        if ($instructions !== '') {
            $content[] = '## Instructions';
            $content[] = '';
            $content[] = $instructions;
            $content[] = '';
        }

        $techStacks = $session->techStacks()->ordered()->get(['name', 'documentation_url']);
        if ($techStacks->isNotEmpty()) {
            $content[] = '## Tech Stack Context';
            $content[] = '';

            foreach ($techStacks as $stack) {
                $content[] = sprintf('- %s: %s', trim((string) $stack->name), trim((string) $stack->documentation_url));
            }

            $content[] = '';
        }

        return trim(implode("\n", $content));
    }

    /**
     * @return array<int, string>
     */
    private function resolveTaskLabels(InterrogationSession $session, InterrogationBuildTask $task): array
    {
        $labels = [
            'agent-build',
            'session-'.(int) $session->id,
            (string) $session->interrogation_type,
        ];

        if ($task->sequence === 1) {
            $labels[] = 'kickoff';
        }

        return array_values(array_unique(array_filter(array_map(
            static fn (string $label): string => trim(strtolower($label)),
            $labels,
        ), static fn (string $label): bool => $label !== '')));
    }

    private function resolveTaskPriority(InterrogationBuildTask $task): int
    {
        $text = strtolower(trim((string) $task->title.' '.(string) $task->description));

        if (str_contains($text, 'urgent') || str_contains($text, 'critical')) {
            return 1;
        }

        if ((int) $task->sequence <= 2) {
            return 2;
        }

        if (str_contains($text, 'cleanup') || str_contains($text, 'docs') || str_contains($text, 'documentation')) {
            return 4;
        }

        return 3;
    }

    private function taskManagementProviderForSession(InterrogationSession $session): ?ConnectedProvider
    {
        return $session->providerIntegrations()
            ->where('category', 'task_management')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function taskProviderLink(InterrogationBuildTask $task, TaskManagementProviderDriver $driver): array
    {
        $metadata = is_array($task->metadata_json) ? $task->metadata_json : [];

        return is_array(data_get($metadata, 'task_provider_links.'.$driver->key()))
            ? data_get($metadata, 'task_provider_links.'.$driver->key())
            : [];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function storeTaskProviderLink(InterrogationBuildTask $task, TaskManagementProviderDriver $driver, array $payload): void
    {
        $metadata = is_array($task->metadata_json) ? $task->metadata_json : [];
        $links = is_array($metadata['task_provider_links'] ?? null) ? $metadata['task_provider_links'] : [];
        $links[$driver->key()] = $payload;
        $metadata['task_provider_links'] = $links;
        $task->metadata_json = $metadata;
        $task->save();
    }

    /**
     * @return array<string, mixed>
     */
    private function buildMetadata(InterrogationSession $session): array
    {
        $metadata = is_array($session->metadata_json) ? $session->metadata_json : [];

        return is_array($metadata['build'] ?? null) ? $metadata['build'] : [];
    }

    /**
     * @param  array<string, mixed>  $build
     */
    private function saveBuildMetadata(InterrogationSession $session, array $build): void
    {
        $metadata = is_array($session->metadata_json) ? $session->metadata_json : [];
        $metadata['build'] = $build;
        $session->metadata_json = $metadata;
        $session->save();
    }

    /**
     * @param  array<string, mixed>  $build
     * @return array<string, mixed>
     */
    private function providerSyncState(array $build): array
    {
        return is_array($build['task_provider_sync'] ?? null)
            ? $build['task_provider_sync']
            : [];
    }

    /**
     * @return array{mode:string,project_id:?string,project_name:?string,project_url:?string}
     */
    private function providerProjectSelection(ConnectedProvider $provider): array
    {
        $metadata = is_array($provider->metadata_json) ? $provider->metadata_json : [];
        $projectSync = is_array($metadata['project_sync'] ?? null) ? $metadata['project_sync'] : [];
        $mode = in_array(($projectSync['mode'] ?? null), ['create_new', 'existing'], true)
            ? (string) $projectSync['mode']
            : 'create_new';

        if ($mode !== 'existing') {
            return [
                'mode' => 'create_new',
                'project_id' => null,
                'project_name' => null,
                'project_url' => null,
            ];
        }

        $projectId = trim((string) ($projectSync['selected_project_id'] ?? ''));
        if ($projectId === '') {
            return [
                'mode' => 'create_new',
                'project_id' => null,
                'project_name' => null,
                'project_url' => null,
            ];
        }

        return [
            'mode' => 'existing',
            'project_id' => $projectId,
            'project_name' => is_string($projectSync['selected_project_name'] ?? null)
                ? trim((string) $projectSync['selected_project_name'])
                : null,
            'project_url' => is_string($projectSync['selected_project_url'] ?? null)
                ? trim((string) $projectSync['selected_project_url'])
                : null,
        ];
    }
}
