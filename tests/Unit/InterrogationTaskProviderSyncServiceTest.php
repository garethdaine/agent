<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\ConnectedProvider;
use App\Models\InterrogationBuildTask;
use App\Models\InterrogationSession;
use App\Models\User;
use App\Support\TaskProviders\Contracts\TaskManagementProviderDriver;
use App\Support\TaskProviders\InterrogationTaskProviderSyncService;
use App\Support\TaskProviders\TaskManagementProviderManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InterrogationTaskProviderSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_build_tasks_creates_phase_milestones_parent_issues_and_sub_issues(): void
    {
        $user = User::factory()->create();

        $session = InterrogationSession::query()->create([
            'user_id' => $user->id,
            'name' => 'Hierarchy sync session',
            'runner_type' => 'codex',
            'project_directory' => base_path(),
            'interrogation_type' => InterrogationSession::TYPE_FEATURE,
            'status' => InterrogationSession::STATUS_BUILD_TASKS,
            'phase' => InterrogationSession::PHASE_BUILD_TASKS,
            'plan_json' => [
                'sections' => [
                    'Phase A: Foundation and Core Infrastructure',
                    'Phase B: Connector Expansion',
                ],
            ],
            'metadata_json' => [
                'build' => [
                    'task_provider_sync' => [
                        'status' => 'queued',
                    ],
                ],
            ],
        ]);

        ConnectedProvider::query()->create([
            'user_id' => $user->id,
            'providerable_type' => InterrogationSession::class,
            'providerable_id' => $session->id,
            'category' => 'task_management',
            'driver' => 'linear',
            'metadata_json' => [
                'team_id' => 'team-1',
                'project_sync' => [
                    'mode' => 'create_new',
                ],
            ],
        ]);

        InterrogationBuildTask::query()->create([
            'interrogation_session_id' => $session->id,
            'sequence' => 1,
            'title' => 'A.1 Database foundations',
            'description' => 'Define base schema.',
            'instructions_markdown' => "- Create migrations\n- Add models\n",
            'status' => InterrogationBuildTask::STATUS_PENDING,
            'attempt_count' => 0,
            'metadata_json' => [],
        ]);

        InterrogationBuildTask::query()->create([
            'interrogation_session_id' => $session->id,
            'sequence' => 2,
            'title' => 'B.1 Connector rollout',
            'description' => 'Implement provider adapters.',
            'instructions_markdown' => "- Build Slack adapter\n- Build Telegram adapter\n",
            'status' => InterrogationBuildTask::STATUS_PENDING,
            'attempt_count' => 0,
            'metadata_json' => [],
        ]);

        $driver = new class implements TaskManagementProviderDriver
        {
            /** @var array<int, array<string, mixed>> */
            public array $createTaskCalls = [];

            /** @var array<int, string> */
            public array $createdMilestones = [];

            private int $milestoneCounter = 0;

            private int $issueCounter = 0;

            public function key(): string
            {
                return 'linear';
            }

            public function authorizationUrl(string $state, string $redirectUri): string
            {
                return '';
            }

            public function exchangeAuthorizationCode(string $code, string $redirectUri): array
            {
                return [];
            }

            public function fetchIdentity(string $accessToken): array
            {
                return [];
            }

            public function createProject(ConnectedProvider $provider, InterrogationSession $session, string $projectName): array
            {
                return [
                    'id' => 'project-1',
                    'name' => $projectName,
                    'url' => null,
                ];
            }

            public function listProjects(ConnectedProvider $provider): array
            {
                return [];
            }

            public function listTeams(ConnectedProvider $provider): array
            {
                return [];
            }

            public function listProjectMilestones(ConnectedProvider $provider, string $projectId): array
            {
                return [];
            }

            public function createProjectMilestone(
                ConnectedProvider $provider,
                InterrogationSession $session,
                string $projectId,
                string $name,
                ?string $description = null,
            ): array {
                $this->milestoneCounter++;
                $this->createdMilestones[] = $name;

                return [
                    'id' => 'milestone-'.$this->milestoneCounter,
                    'name' => $name,
                ];
            }

            public function createTask(
                ConnectedProvider $provider,
                InterrogationSession $session,
                InterrogationBuildTask $task,
                string $projectId,
                int $priority,
                array $labels,
                string $description,
                ?string $projectMilestoneId = null,
                ?string $parentTaskId = null,
                ?string $title = null,
            ): array {
                $this->issueCounter++;
                $id = 'issue-'.$this->issueCounter;
                $resolvedTitle = trim((string) ($title ?? $task->title));

                $this->createTaskCalls[] = [
                    'id' => $id,
                    'title' => $resolvedTitle,
                    'project_milestone_id' => $projectMilestoneId,
                    'parent_task_id' => $parentTaskId,
                ];

                return [
                    'id' => $id,
                    'identifier' => 'AGE-'.$this->issueCounter,
                    'url' => 'https://linear.app/acme/issue/AGE-'.$this->issueCounter,
                ];
            }

            public function updateTaskStatus(
                ConnectedProvider $provider,
                InterrogationSession $session,
                string $externalTaskId,
                string $status,
                ?string $note = null,
            ): void {}
        };

        $this->app->instance(TaskManagementProviderManager::class, new class($driver) extends TaskManagementProviderManager
        {
            public function __construct(private readonly TaskManagementProviderDriver $driver) {}

            public function driver(string $driver): TaskManagementProviderDriver
            {
                return $this->driver;
            }
        });

        $service = app(InterrogationTaskProviderSyncService::class);
        $service->syncBuildTasks($session->fresh());

        $session->refresh();

        $this->assertSame('synced', (string) data_get($session->metadata_json, 'build.task_provider_sync.status'));
        $this->assertSame(2, (int) data_get($session->metadata_json, 'build.task_provider_sync.milestone_count'));
        $this->assertSame(2, (int) data_get($session->metadata_json, 'build.task_provider_sync.synced_task_count'));
        $this->assertSame(4, (int) data_get($session->metadata_json, 'build.task_provider_sync.synced_subtask_count'));

        $this->assertCount(2, $driver->createdMilestones);
        $this->assertCount(6, $driver->createTaskCalls);

        $parentCalls = array_values(array_filter($driver->createTaskCalls, static fn (array $call): bool => $call['parent_task_id'] === null));
        $childCalls = array_values(array_filter($driver->createTaskCalls, static fn (array $call): bool => $call['parent_task_id'] !== null));

        $this->assertCount(2, $parentCalls);
        $this->assertCount(4, $childCalls);
        $this->assertSame('issue-1', (string) ($childCalls[0]['parent_task_id'] ?? ''));

        $firstTask = $session->buildTasks()->ordered()->firstOrFail();
        $firstTaskLink = data_get($firstTask->metadata_json, 'task_provider_links.linear', []);

        $this->assertSame('issue-1', (string) ($firstTaskLink['external_task_id'] ?? ''));
        $this->assertSame(2, (int) ($firstTaskLink['subtask_issue_count'] ?? 0));
        $this->assertSame('milestone-1', (string) ($firstTaskLink['milestone_id'] ?? ''));
    }

    public function test_sync_task_status_updates_parent_and_sub_issues_and_recomputes_milestone_progress(): void
    {
        $user = User::factory()->create();

        $session = InterrogationSession::query()->create([
            'user_id' => $user->id,
            'name' => 'Runtime status sync session',
            'runner_type' => 'codex',
            'project_directory' => base_path(),
            'interrogation_type' => InterrogationSession::TYPE_FEATURE,
            'status' => InterrogationSession::STATUS_BUILD_EXECUTING,
            'phase' => InterrogationSession::PHASE_BUILD_EXECUTION,
            'metadata_json' => [
                'build' => [
                    'task_provider_sync' => [
                        'status' => 'synced',
                    ],
                ],
            ],
        ]);

        ConnectedProvider::query()->create([
            'user_id' => $user->id,
            'providerable_type' => InterrogationSession::class,
            'providerable_id' => $session->id,
            'category' => 'task_management',
            'driver' => 'linear',
            'metadata_json' => [
                'team_id' => 'team-1',
            ],
        ]);

        $taskInProgress = InterrogationBuildTask::query()->create([
            'interrogation_session_id' => $session->id,
            'sequence' => 1,
            'title' => 'A.1 Active task',
            'status' => InterrogationBuildTask::STATUS_IN_PROGRESS,
            'last_error' => 'Waiting for adapter confirmation.',
            'attempt_count' => 1,
            'metadata_json' => [
                'task_provider_links' => [
                    'linear' => [
                        'external_task_id' => 'issue-parent-1',
                        'milestone_id' => 'milestone-a',
                        'milestone_name' => 'Phase A: Foundation',
                        'subtask_issues' => [
                            ['id' => 'issue-child-1', 'title' => 'Subtask 1'],
                            ['id' => 'issue-child-2', 'title' => 'Subtask 2'],
                        ],
                    ],
                ],
            ],
        ]);

        InterrogationBuildTask::query()->create([
            'interrogation_session_id' => $session->id,
            'sequence' => 2,
            'title' => 'A.2 Completed task',
            'status' => InterrogationBuildTask::STATUS_COMPLETED,
            'attempt_count' => 1,
            'metadata_json' => [
                'task_provider_links' => [
                    'linear' => [
                        'external_task_id' => 'issue-parent-2',
                        'milestone_id' => 'milestone-a',
                        'milestone_name' => 'Phase A: Foundation',
                    ],
                ],
            ],
        ]);

        InterrogationBuildTask::query()->create([
            'interrogation_session_id' => $session->id,
            'sequence' => 3,
            'title' => 'B.1 Pending task',
            'status' => InterrogationBuildTask::STATUS_PENDING,
            'attempt_count' => 0,
            'metadata_json' => [
                'task_provider_links' => [
                    'linear' => [
                        'external_task_id' => 'issue-parent-3',
                        'milestone_id' => 'milestone-b',
                        'milestone_name' => 'Phase B: Expansion',
                    ],
                ],
            ],
        ]);

        $driver = new class implements TaskManagementProviderDriver
        {
            /** @var array<int, array<string, mixed>> */
            public array $statusUpdates = [];

            public function key(): string
            {
                return 'linear';
            }

            public function authorizationUrl(string $state, string $redirectUri): string
            {
                return '';
            }

            public function exchangeAuthorizationCode(string $code, string $redirectUri): array
            {
                return [];
            }

            public function fetchIdentity(string $accessToken): array
            {
                return [];
            }

            public function createProject(ConnectedProvider $provider, InterrogationSession $session, string $projectName): array
            {
                return [];
            }

            public function listProjects(ConnectedProvider $provider): array
            {
                return [];
            }

            public function listTeams(ConnectedProvider $provider): array
            {
                return [];
            }

            public function listProjectMilestones(ConnectedProvider $provider, string $projectId): array
            {
                return [];
            }

            public function createProjectMilestone(
                ConnectedProvider $provider,
                InterrogationSession $session,
                string $projectId,
                string $name,
                ?string $description = null,
            ): array {
                return [];
            }

            public function createTask(
                ConnectedProvider $provider,
                InterrogationSession $session,
                InterrogationBuildTask $task,
                string $projectId,
                int $priority,
                array $labels,
                string $description,
                ?string $projectMilestoneId = null,
                ?string $parentTaskId = null,
                ?string $title = null,
            ): array {
                return [];
            }

            public function updateTaskStatus(
                ConnectedProvider $provider,
                InterrogationSession $session,
                string $externalTaskId,
                string $status,
                ?string $note = null,
            ): void {
                $this->statusUpdates[] = [
                    'external_task_id' => $externalTaskId,
                    'status' => $status,
                    'note' => $note,
                ];
            }
        };

        $this->app->instance(TaskManagementProviderManager::class, new class($driver) extends TaskManagementProviderManager
        {
            public function __construct(private readonly TaskManagementProviderDriver $driver) {}

            public function driver(string $driver): TaskManagementProviderDriver
            {
                return $this->driver;
            }
        });

        $service = app(InterrogationTaskProviderSyncService::class);
        $service->syncTaskStatus($session->fresh(), $taskInProgress->fresh());

        $session->refresh();
        $taskInProgress->refresh();

        $this->assertCount(3, $driver->statusUpdates);
        $this->assertSame('issue-parent-1', (string) $driver->statusUpdates[0]['external_task_id']);
        $this->assertSame('issue-child-1', (string) $driver->statusUpdates[1]['external_task_id']);
        $this->assertSame('issue-child-2', (string) $driver->statusUpdates[2]['external_task_id']);

        foreach ($driver->statusUpdates as $statusUpdate) {
            $this->assertSame(InterrogationBuildTask::STATUS_IN_PROGRESS, (string) $statusUpdate['status']);
            $this->assertSame(
                'Latest build execution note: Waiting for adapter confirmation.',
                (string) $statusUpdate['note']
            );
        }

        $link = data_get($taskInProgress->metadata_json, 'task_provider_links.linear', []);
        $this->assertSame(InterrogationBuildTask::STATUS_IN_PROGRESS, (string) ($link['last_synced_status'] ?? ''));
        $this->assertNotNull($link['status_synced_at'] ?? null);

        $subtaskIssues = is_array($link['subtask_issues'] ?? null) ? $link['subtask_issues'] : [];
        $this->assertCount(2, $subtaskIssues);
        $this->assertNotNull(data_get($subtaskIssues, '0.status_synced_at'));
        $this->assertSame(InterrogationBuildTask::STATUS_IN_PROGRESS, data_get($subtaskIssues, '0.last_synced_status'));
        $this->assertNotNull(data_get($subtaskIssues, '1.status_synced_at'));
        $this->assertSame(InterrogationBuildTask::STATUS_IN_PROGRESS, data_get($subtaskIssues, '1.last_synced_status'));

        $milestones = collect(data_get($session->metadata_json, 'build.task_provider_sync.milestones', []))
            ->keyBy(fn (array $milestone): string => (string) ($milestone['milestone_id'] ?? ''));

        $phaseA = $milestones->get('milestone-a');
        $phaseB = $milestones->get('milestone-b');

        $this->assertIsArray($phaseA);
        $this->assertSame(2, (int) ($phaseA['total'] ?? 0));
        $this->assertSame(1, (int) ($phaseA['in_progress'] ?? 0));
        $this->assertSame(1, (int) ($phaseA['completed'] ?? 0));
        $this->assertSame('in_progress', (string) ($phaseA['status'] ?? ''));
        $this->assertSame(50, (int) ($phaseA['progress_percent'] ?? 0));

        $this->assertIsArray($phaseB);
        $this->assertSame(1, (int) ($phaseB['total'] ?? 0));
        $this->assertSame(1, (int) ($phaseB['pending'] ?? 0));
        $this->assertSame('pending', (string) ($phaseB['status'] ?? ''));
        $this->assertSame(0, (int) ($phaseB['progress_percent'] ?? 0));
    }

    public function test_sync_task_status_backfills_missing_task_link_before_updating_provider_status(): void
    {
        $user = User::factory()->create();

        $session = InterrogationSession::query()->create([
            'user_id' => $user->id,
            'name' => 'Backfill sync session',
            'runner_type' => 'codex',
            'project_directory' => base_path(),
            'interrogation_type' => InterrogationSession::TYPE_FEATURE,
            'status' => InterrogationSession::STATUS_BUILD_EXECUTING,
            'phase' => InterrogationSession::PHASE_BUILD_EXECUTION,
            'plan_json' => [
                'sections' => [
                    'Phase A: Foundation',
                ],
            ],
            'metadata_json' => [
                'build' => [
                    'task_provider_sync' => [
                        'status' => 'synced',
                    ],
                ],
            ],
        ]);

        ConnectedProvider::query()->create([
            'user_id' => $user->id,
            'providerable_type' => InterrogationSession::class,
            'providerable_id' => $session->id,
            'category' => 'task_management',
            'driver' => 'linear',
            'metadata_json' => [
                'team_id' => 'team-1',
                'project_sync' => [
                    'mode' => 'create_new',
                ],
            ],
        ]);

        $task = InterrogationBuildTask::query()->create([
            'interrogation_session_id' => $session->id,
            'sequence' => 1,
            'title' => 'A.1 Backfill and sync',
            'description' => 'Task exists without provider link metadata.',
            'instructions_markdown' => '- Create integration mapping',
            'status' => InterrogationBuildTask::STATUS_IN_PROGRESS,
            'attempt_count' => 1,
            'last_error' => 'Re-running with updated metadata.',
            'metadata_json' => [
                'generated_at' => now('UTC')->toIso8601String(),
            ],
        ]);

        $driver = new class implements TaskManagementProviderDriver
        {
            /** @var array<int, array<string, mixed>> */
            public array $createTaskCalls = [];

            /** @var array<int, array<string, mixed>> */
            public array $statusUpdates = [];

            private int $issueCounter = 0;

            public function key(): string
            {
                return 'linear';
            }

            public function authorizationUrl(string $state, string $redirectUri): string
            {
                return '';
            }

            public function exchangeAuthorizationCode(string $code, string $redirectUri): array
            {
                return [];
            }

            public function fetchIdentity(string $accessToken): array
            {
                return [];
            }

            public function createProject(ConnectedProvider $provider, InterrogationSession $session, string $projectName): array
            {
                return [
                    'id' => 'project-1',
                    'name' => $projectName,
                    'url' => null,
                ];
            }

            public function listProjects(ConnectedProvider $provider): array
            {
                return [];
            }

            public function listTeams(ConnectedProvider $provider): array
            {
                return [];
            }

            public function listProjectMilestones(ConnectedProvider $provider, string $projectId): array
            {
                return [];
            }

            public function createProjectMilestone(
                ConnectedProvider $provider,
                InterrogationSession $session,
                string $projectId,
                string $name,
                ?string $description = null,
            ): array {
                return [
                    'id' => 'milestone-1',
                    'name' => $name,
                ];
            }

            public function createTask(
                ConnectedProvider $provider,
                InterrogationSession $session,
                InterrogationBuildTask $task,
                string $projectId,
                int $priority,
                array $labels,
                string $description,
                ?string $projectMilestoneId = null,
                ?string $parentTaskId = null,
                ?string $title = null,
            ): array {
                $this->issueCounter++;
                $id = 'issue-'.$this->issueCounter;

                $this->createTaskCalls[] = [
                    'id' => $id,
                    'parent_task_id' => $parentTaskId,
                    'project_milestone_id' => $projectMilestoneId,
                    'title' => $title ?? $task->title,
                ];

                return [
                    'id' => $id,
                    'identifier' => 'AGE-'.$this->issueCounter,
                    'url' => 'https://linear.app/acme/issue/AGE-'.$this->issueCounter,
                ];
            }

            public function updateTaskStatus(
                ConnectedProvider $provider,
                InterrogationSession $session,
                string $externalTaskId,
                string $status,
                ?string $note = null,
            ): void {
                $this->statusUpdates[] = [
                    'external_task_id' => $externalTaskId,
                    'status' => $status,
                    'note' => $note,
                ];
            }
        };

        $this->app->instance(TaskManagementProviderManager::class, new class($driver) extends TaskManagementProviderManager
        {
            public function __construct(private readonly TaskManagementProviderDriver $driver) {}

            public function driver(string $driver): TaskManagementProviderDriver
            {
                return $this->driver;
            }
        });

        $service = app(InterrogationTaskProviderSyncService::class);
        $service->syncTaskStatus($session->fresh(), $task->fresh());

        $task->refresh();
        $session->refresh();

        $link = data_get($task->metadata_json, 'task_provider_links.linear', []);
        $this->assertNotSame('', trim((string) ($link['external_task_id'] ?? '')));
        $this->assertSame(InterrogationBuildTask::STATUS_IN_PROGRESS, (string) ($link['last_synced_status'] ?? ''));
        $this->assertNull($link['status_sync_error'] ?? null);
        $this->assertSame('synced', (string) data_get($session->metadata_json, 'build.task_provider_sync.status'));

        $this->assertNotEmpty($driver->createTaskCalls);
        $this->assertNotEmpty($driver->statusUpdates);
        $this->assertContains('issue-1', array_map(
            static fn (array $update): string => (string) ($update['external_task_id'] ?? ''),
            $driver->statusUpdates
        ));
    }
}
