<?php

namespace App\Support\TaskProviders;

use App\Models\ConnectedProvider;
use App\Models\InterrogationBuildTask;
use App\Models\InterrogationSession;
use App\Support\TaskProviders\Contracts\TaskManagementProviderDriver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;

class InterrogationTaskProviderSyncService
{
    private const MAX_PHASE_MILESTONES = 12;

    private const MAX_SUBTASKS_PER_TASK = 20;

    private const MAX_SUBTASK_TITLE_LENGTH = 180;

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

        $phaseDefinitions = $this->extractPhaseDefinitions($session);
        $milestonesByKey = $this->ensurePhaseMilestones(
            $driver,
            $provider,
            $session,
            $projectId,
            $phaseDefinitions,
        );

        $syncedCount = 0;
        $syncedSubtaskCount = 0;

        foreach ($tasks as $task) {
            $phase = $this->resolveTaskPhase($task, $phaseDefinitions);
            $phaseKey = $phase['key'];
            $milestone = $milestonesByKey[$phaseKey] ?? null;
            $milestoneId = is_array($milestone) ? trim((string) ($milestone['id'] ?? '')) : '';
            $milestoneName = is_array($milestone) ? trim((string) ($milestone['name'] ?? '')) : '';

            $priority = $this->resolveTaskPriority($task);
            $labels = $this->resolveTaskLabels($session, $task);
            $taskDescription = $this->buildTaskDescription($session, $task, $labels);
            $link = $this->taskProviderLink($task, $driver);
            $mainIssueId = trim((string) ($link['external_task_id'] ?? ''));
            $mainIssue = null;

            if ($mainIssueId === '') {
                $mainIssue = $driver->createTask(
                    $provider,
                    $session,
                    $task,
                    $projectId,
                    $priority,
                    $labels,
                    $taskDescription,
                    $milestoneId !== '' ? $milestoneId : null,
                );

                $mainIssueId = trim((string) ($mainIssue['id'] ?? ''));
            }

            if ($mainIssueId === '') {
                continue;
            }

            $subtaskTitles = $this->extractSubtaskTitles($task);
            $existingSubtaskIssues = is_array($link['subtask_issues'] ?? null)
                ? array_values(array_filter($link['subtask_issues'], static fn ($subtask): bool => is_array($subtask)))
                : [];

            $existingSubtaskByKey = [];
            foreach ($existingSubtaskIssues as $subtaskIssue) {
                $title = $this->normalizeSubtaskKey((string) ($subtaskIssue['title'] ?? ''));
                $id = trim((string) ($subtaskIssue['id'] ?? ''));
                if ($title === '' || $id === '') {
                    continue;
                }

                $existingSubtaskByKey[$title] = $subtaskIssue;
            }

            $resolvedSubtaskIssues = [];

            foreach ($subtaskTitles as $subtaskIndex => $subtaskTitle) {
                $subtaskKey = $this->normalizeSubtaskKey($subtaskTitle);
                $existingSubtask = $existingSubtaskByKey[$subtaskKey] ?? null;

                if (is_array($existingSubtask) && trim((string) ($existingSubtask['id'] ?? '')) !== '') {
                    $resolvedSubtaskIssues[] = [
                        'id' => trim((string) ($existingSubtask['id'] ?? '')),
                        'identifier' => is_string($existingSubtask['identifier'] ?? null)
                            ? trim((string) $existingSubtask['identifier']) ?: null
                            : null,
                        'url' => is_string($existingSubtask['url'] ?? null)
                            ? trim((string) $existingSubtask['url']) ?: null
                            : null,
                        'title' => $subtaskTitle,
                    ];

                    continue;
                }

                $subtaskIssue = $driver->createTask(
                    $provider,
                    $session,
                    $task,
                    $projectId,
                    $priority,
                    [...$labels, 'subtask'],
                    $this->buildSubtaskDescription($session, $task, $subtaskTitle, $subtaskIndex + 1, count($subtaskTitles)),
                    $milestoneId !== '' ? $milestoneId : null,
                    $mainIssueId,
                    $subtaskTitle,
                );

                $resolvedSubtaskIssues[] = [
                    'id' => trim((string) ($subtaskIssue['id'] ?? '')),
                    'identifier' => $subtaskIssue['identifier'] ?? null,
                    'url' => $subtaskIssue['url'] ?? null,
                    'title' => $subtaskTitle,
                ];
            }

            $this->storeTaskProviderLink($task, $driver, [
                'external_task_id' => $mainIssueId,
                'external_task_identifier' => $mainIssue['identifier'] ?? ($link['external_task_identifier'] ?? null),
                'external_task_url' => $mainIssue['url'] ?? ($link['external_task_url'] ?? null),
                'phase' => $phase['name'],
                'milestone_id' => $milestoneId !== '' ? $milestoneId : null,
                'milestone_name' => $milestoneName !== '' ? $milestoneName : null,
                'priority' => $priority,
                'labels' => $labels,
                'subtask_issues' => $resolvedSubtaskIssues,
                'subtask_issue_count' => count($resolvedSubtaskIssues),
                'synced_at' => CarbonImmutable::now('UTC')->toIso8601String(),
                'status_synced_at' => $link['status_synced_at'] ?? null,
                'status_sync_error' => null,
                'last_synced_status' => $link['last_synced_status'] ?? null,
            ]);

            $syncedCount++;
            $syncedSubtaskCount += count($resolvedSubtaskIssues);
        }

        $sync['status'] = 'synced';
        $sync['synced_task_count'] = $syncedCount;
        $sync['synced_subtask_count'] = $syncedSubtaskCount;
        $sync['milestone_count'] = count($milestonesByKey);
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
        [$task, $link, $externalTaskId] = $this->resolveTaskLinkForStatusSync($session, $task, $driver);

        if ($externalTaskId === '') {
            $this->storeTaskProviderLink($task, $driver, [
                ...$link,
                'status_synced_at' => CarbonImmutable::now('UTC')->toIso8601String(),
                'status_sync_error' => 'Task is not linked to a provider issue yet.',
            ]);

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

        $statusSyncedAt = CarbonImmutable::now('UTC')->toIso8601String();
        $subtaskIssues = is_array($link['subtask_issues'] ?? null)
            ? array_values(array_filter($link['subtask_issues'], static fn ($subtask): bool => is_array($subtask)))
            : [];
        $syncedSubtaskIssues = [];

        foreach ($subtaskIssues as $subtaskIssue) {
            $subtaskId = trim((string) ($subtaskIssue['id'] ?? ''));
            if ($subtaskId === '') {
                continue;
            }

            $driver->updateTaskStatus(
                $provider,
                $session,
                $subtaskId,
                (string) $task->status,
                $note,
            );

            $syncedSubtaskIssues[] = [
                ...$subtaskIssue,
                'status_synced_at' => $statusSyncedAt,
                'last_synced_status' => (string) $task->status,
                'status_sync_error' => null,
            ];
        }

        $this->storeTaskProviderLink($task, $driver, [
            ...$link,
            'subtask_issues' => $syncedSubtaskIssues === [] ? $subtaskIssues : $syncedSubtaskIssues,
            'subtask_issue_count' => count($syncedSubtaskIssues === [] ? $subtaskIssues : $syncedSubtaskIssues),
            'status_synced_at' => $statusSyncedAt,
            'status_sync_error' => null,
            'last_synced_status' => (string) $task->status,
        ]);

        $this->updateSyncMilestoneProgress($session, $driver);
    }

    /**
     * @return array{0:InterrogationBuildTask,1:array<string,mixed>,2:string}
     */
    private function resolveTaskLinkForStatusSync(
        InterrogationSession $session,
        InterrogationBuildTask $task,
        TaskManagementProviderDriver $driver,
    ): array {
        $link = $this->taskProviderLink($task, $driver);
        $externalTaskId = trim((string) ($link['external_task_id'] ?? ''));

        if ($externalTaskId !== '') {
            return [$task, $link, $externalTaskId];
        }

        // Backfill missing links (for example after task regeneration/reset) before syncing runtime status.
        $this->syncBuildTasks($session);

        $freshTask = InterrogationBuildTask::query()
            ->where('interrogation_session_id', $session->id)
            ->whereKey($task->id)
            ->first();

        if ($freshTask instanceof InterrogationBuildTask) {
            $task = $freshTask;
        }

        $link = $this->taskProviderLink($task, $driver);
        $externalTaskId = trim((string) ($link['external_task_id'] ?? ''));

        return [$task, $link, $externalTaskId];
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
     * @return array<int, array{name:string,key:string,token:?string,keywords:array<int,string>}>
     */
    private function extractPhaseDefinitions(InterrogationSession $session): array
    {
        $plan = is_array($session->plan_json) ? $session->plan_json : [];
        $sections = is_array($plan['sections'] ?? null) ? array_values($plan['sections']) : [];
        $definitions = [];
        $seen = [];

        foreach ($sections as $section) {
            if (! is_string($section)) {
                continue;
            }

            $name = trim($section);
            if ($name === '' || ! preg_match('/^phase\s+[a-z0-9]+/i', $name)) {
                continue;
            }

            $definition = $this->buildPhaseDefinition($name);
            if ($definition === null || isset($seen[$definition['key']])) {
                continue;
            }

            $seen[$definition['key']] = true;
            $definitions[] = $definition;
        }

        if ($definitions === []) {
            $planMarkdown = trim((string) ($plan['plan_markdown'] ?? ''));
            if ($planMarkdown !== '') {
                preg_match_all('/^(?:#{1,6}\s*)?(phase\s+[^\n]+)$/im', $planMarkdown, $matches);
                foreach ((array) ($matches[1] ?? []) as $phaseHeading) {
                    $definition = $this->buildPhaseDefinition((string) $phaseHeading);
                    if ($definition === null || isset($seen[$definition['key']])) {
                        continue;
                    }

                    $seen[$definition['key']] = true;
                    $definitions[] = $definition;
                }
            }
        }

        if ($definitions === []) {
            $definitions[] = $this->buildPhaseDefinition('Phase 1: Build') ?? [
                'name' => 'Phase 1: Build',
                'key' => 'phase-1-build',
                'token' => '1',
                'keywords' => ['build'],
            ];
        }

        return array_slice($definitions, 0, self::MAX_PHASE_MILESTONES);
    }

    /**
     * @param  array<int, array{name:string,key:string,token:?string,keywords:array<int,string>}>  $phaseDefinitions
     * @return array<string, array{id:string,name:?string}>
     */
    private function ensurePhaseMilestones(
        TaskManagementProviderDriver $driver,
        ConnectedProvider $provider,
        InterrogationSession $session,
        string $projectId,
        array $phaseDefinitions,
    ): array {
        $existingMilestones = $driver->listProjectMilestones($provider, $projectId);
        $milestonesByKey = [];

        foreach ($existingMilestones as $existingMilestone) {
            if (! is_array($existingMilestone)) {
                continue;
            }

            $id = trim((string) ($existingMilestone['id'] ?? ''));
            $name = trim((string) ($existingMilestone['name'] ?? ''));
            if ($id === '' || $name === '') {
                continue;
            }

            $milestonesByKey[$this->normalizePhaseKey($name)] = [
                'id' => $id,
                'name' => $name,
            ];
        }

        foreach ($phaseDefinitions as $phaseDefinition) {
            $phaseKey = $phaseDefinition['key'];
            if (isset($milestonesByKey[$phaseKey])) {
                continue;
            }

            $createdMilestone = $driver->createProjectMilestone(
                $provider,
                $session,
                $projectId,
                $phaseDefinition['name'],
                sprintf('Build phase milestone for session #%d.', (int) $session->id),
            );

            $milestonesByKey[$phaseKey] = [
                'id' => trim((string) ($createdMilestone['id'] ?? '')),
                'name' => trim((string) ($createdMilestone['name'] ?? $phaseDefinition['name'])) ?: $phaseDefinition['name'],
            ];
        }

        return $milestonesByKey;
    }

    /**
     * @param  array<int, array{name:string,key:string,token:?string,keywords:array<int,string>}>  $phaseDefinitions
     * @return array{name:string,key:string}
     */
    private function resolveTaskPhase(InterrogationBuildTask $task, array $phaseDefinitions): array
    {
        if ($phaseDefinitions === []) {
            return [
                'name' => 'Phase 1: Build',
                'key' => 'phase-1-build',
            ];
        }

        if (count($phaseDefinitions) === 1) {
            return [
                'name' => (string) $phaseDefinitions[0]['name'],
                'key' => (string) $phaseDefinitions[0]['key'],
            ];
        }

        $metadata = is_array($task->metadata_json) ? $task->metadata_json : [];
        $metadataPhase = trim((string) data_get($metadata, 'phase_name', ''));
        if ($metadataPhase !== '') {
            $metadataPhaseKey = $this->normalizePhaseKey($metadataPhase);
            $metadataMatch = Arr::first($phaseDefinitions, static fn (array $phase): bool => $phase['key'] === $metadataPhaseKey);
            if (is_array($metadataMatch)) {
                return [
                    'name' => (string) $metadataMatch['name'],
                    'key' => (string) $metadataMatch['key'],
                ];
            }
        }

        $text = strtolower(trim(implode("\n", [
            (string) $task->title,
            (string) ($task->description ?? ''),
            (string) ($task->instructions_markdown ?? ''),
        ])));

        if (preg_match('/\bphase\s+([a-z0-9]+)\b/i', $text, $phaseMatch) === 1) {
            $token = strtolower(trim((string) ($phaseMatch[1] ?? '')));
            $tokenMatch = Arr::first($phaseDefinitions, static fn (array $phase): bool => strtolower((string) ($phase['token'] ?? '')) === $token);
            if (is_array($tokenMatch)) {
                return [
                    'name' => (string) $tokenMatch['name'],
                    'key' => (string) $tokenMatch['key'],
                ];
            }
        }

        if (preg_match('/\b([a-z])\.(\d+)\b/i', (string) $task->title, $sectionMatch) === 1) {
            $token = strtolower(trim((string) ($sectionMatch[1] ?? '')));
            $tokenMatch = Arr::first($phaseDefinitions, static fn (array $phase): bool => strtolower((string) ($phase['token'] ?? '')) === $token);
            if (is_array($tokenMatch)) {
                return [
                    'name' => (string) $tokenMatch['name'],
                    'key' => (string) $tokenMatch['key'],
                ];
            }
        }

        $bestPhase = null;
        $bestScore = 0;

        foreach ($phaseDefinitions as $phaseDefinition) {
            $score = 0;
            foreach ((array) ($phaseDefinition['keywords'] ?? []) as $keyword) {
                if ($keyword !== '' && str_contains($text, $keyword)) {
                    $score++;
                }
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestPhase = $phaseDefinition;
            }
        }

        $resolvedPhase = is_array($bestPhase) ? $bestPhase : $phaseDefinitions[0];

        return [
            'name' => (string) $resolvedPhase['name'],
            'key' => (string) $resolvedPhase['key'],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function extractSubtaskTitles(InterrogationBuildTask $task): array
    {
        $instructions = trim((string) ($task->instructions_markdown ?? ''));
        $description = trim((string) ($task->description ?? ''));
        $source = $instructions !== '' ? $instructions : $description;
        $lines = preg_split('/\R/', $source) ?: [];
        $titles = [];
        $insideCodeBlock = false;

        foreach ($lines as $line) {
            $trimmed = trim((string) $line);
            if ($trimmed === '') {
                continue;
            }

            if (str_starts_with($trimmed, '```')) {
                $insideCodeBlock = ! $insideCodeBlock;

                continue;
            }

            if ($insideCodeBlock) {
                continue;
            }

            if (preg_match('/^(?:[-*+]\s+(?:\[[ xX]\]\s*)?|\d+[.)]\s+)(.+)$/', $trimmed, $match) !== 1) {
                continue;
            }

            $candidate = $this->cleanSubtaskTitle((string) ($match[1] ?? ''));
            if ($candidate === '') {
                continue;
            }

            $titles[] = $candidate;
        }

        if ($titles === []) {
            $sentences = preg_split('/(?<=[.!?])\s+/', preg_replace('/\s+/', ' ', $source) ?? '') ?: [];
            foreach ($sentences as $sentence) {
                $candidate = $this->cleanSubtaskTitle((string) $sentence);
                if ($candidate === '') {
                    continue;
                }

                $titles[] = $candidate;
                if (count($titles) >= 5) {
                    break;
                }
            }
        }

        if ($titles === []) {
            $titles[] = $this->cleanSubtaskTitle('Execute and verify: '.trim((string) $task->title));
        }

        $titles = array_values(array_unique(array_filter($titles, static fn (string $value): bool => $value !== '')));

        return array_slice($titles, 0, self::MAX_SUBTASKS_PER_TASK);
    }

    private function buildSubtaskDescription(
        InterrogationSession $session,
        InterrogationBuildTask $task,
        string $subtaskTitle,
        int $position,
        int $total,
    ): string {
        $lines = [
            'Parent build task: '.trim((string) $task->title),
            'Session: #'.(int) $session->id,
            sprintf('Sub-task %d of %d', $position, $total),
            '',
            trim($subtaskTitle),
        ];

        return trim(implode("\n", $lines));
    }

    private function cleanSubtaskTitle(string $title): string
    {
        $clean = trim($title);
        if ($clean === '') {
            return '';
        }

        $clean = preg_replace('/\[(.*?)\]\((.*?)\)/', '$1', $clean) ?? $clean;
        $clean = preg_replace('/[`*_~>#]/', '', $clean) ?? $clean;
        $clean = trim(preg_replace('/\s+/', ' ', $clean) ?? $clean);
        $clean = trim($clean, "- \t\n\r\0\x0B");

        if ($clean === '') {
            return '';
        }

        if (mb_strlen($clean) > self::MAX_SUBTASK_TITLE_LENGTH) {
            $clean = mb_substr($clean, 0, self::MAX_SUBTASK_TITLE_LENGTH - 1).'…';
        }

        return $clean;
    }

    private function normalizeSubtaskKey(string $title): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', $title) ?? $title));
    }

    /**
     * @return array{name:string,key:string,token:?string,keywords:array<int,string>}|null
     */
    private function buildPhaseDefinition(string $name): ?array
    {
        $phaseName = trim($name);
        if ($phaseName === '') {
            return null;
        }

        $phaseName = preg_replace('/^\#+\s*/', '', $phaseName) ?? $phaseName;
        $phaseName = trim($phaseName);
        if ($phaseName === '') {
            return null;
        }

        $token = null;
        if (preg_match('/^phase\s+([a-z0-9]+)/i', $phaseName, $phaseTokenMatch) === 1) {
            $token = strtolower(trim((string) ($phaseTokenMatch[1] ?? '')));
        }

        $keywordSource = $phaseName;
        if (preg_match('/^phase\s+[a-z0-9]+\s*:\s*(.+)$/i', $phaseName, $keywordMatch) === 1) {
            $keywordSource = (string) ($keywordMatch[1] ?? $phaseName);
        }

        preg_match_all('/[a-z0-9]{4,}/i', strtolower($keywordSource), $keywordTokens);
        $keywords = array_values(array_unique(array_filter(
            (array) ($keywordTokens[0] ?? []),
            static fn (string $keyword): bool => ! in_array($keyword, ['phase', 'with', 'from', 'into', 'that', 'this', 'core'], true),
        )));

        return [
            'name' => $phaseName,
            'key' => $this->normalizePhaseKey($phaseName),
            'token' => $token !== '' ? $token : null,
            'keywords' => $keywords,
        ];
    }

    private function normalizePhaseKey(string $name): string
    {
        $key = strtolower(trim($name));
        $key = preg_replace('/^\#+\s*/', '', $key) ?? $key;
        $key = preg_replace('/\s+/', ' ', $key) ?? $key;

        return trim($key);
    }

    private function updateSyncMilestoneProgress(InterrogationSession $session, TaskManagementProviderDriver $driver): void
    {
        $tasks = $session->buildTasks()->ordered()->get();
        $milestones = [];

        foreach ($tasks as $buildTask) {
            $link = $this->taskProviderLink($buildTask, $driver);
            $milestoneId = trim((string) ($link['milestone_id'] ?? ''));
            $milestoneName = trim((string) ($link['milestone_name'] ?? ''));

            if ($milestoneId === '' && $milestoneName === '') {
                continue;
            }

            $milestoneKey = $milestoneId !== '' ? $milestoneId : $this->normalizePhaseKey($milestoneName);
            if (! isset($milestones[$milestoneKey])) {
                $milestones[$milestoneKey] = [
                    'milestone_id' => $milestoneId !== '' ? $milestoneId : null,
                    'milestone_name' => $milestoneName !== '' ? $milestoneName : null,
                    'total' => 0,
                    'pending' => 0,
                    'in_progress' => 0,
                    'blocked' => 0,
                    'completed' => 0,
                    'failed' => 0,
                    'skipped' => 0,
                ];
            }

            $status = (string) $buildTask->status;
            $milestones[$milestoneKey]['total']++;

            if (array_key_exists($status, $milestones[$milestoneKey])) {
                $milestones[$milestoneKey][$status]++;
            } else {
                $milestones[$milestoneKey]['pending']++;
            }
        }

        $summary = [];

        foreach ($milestones as $milestone) {
            $total = (int) ($milestone['total'] ?? 0);
            $completed = (int) ($milestone['completed'] ?? 0);

            $status = 'pending';
            if ($total > 0 && $completed === $total) {
                $status = 'completed';
            } elseif ((int) ($milestone['failed'] ?? 0) > 0) {
                $status = 'failed';
            } elseif ((int) ($milestone['blocked'] ?? 0) > 0) {
                $status = 'blocked';
            } elseif ((int) ($milestone['in_progress'] ?? 0) > 0) {
                $status = 'in_progress';
            }

            $summary[] = [
                ...$milestone,
                'status' => $status,
                'progress_percent' => $total > 0 ? (int) round(($completed / $total) * 100) : 0,
            ];
        }

        usort($summary, static function (array $left, array $right): int {
            return strcasecmp((string) ($left['milestone_name'] ?? ''), (string) ($right['milestone_name'] ?? ''));
        });

        $build = $this->buildMetadata($session);
        $sync = $this->providerSyncState($build);
        $sync['milestones'] = array_values($summary);
        $sync['updated_at'] = CarbonImmutable::now('UTC')->toIso8601String();
        $build['task_provider_sync'] = $sync;
        $this->saveBuildMetadata($session, $build);
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
