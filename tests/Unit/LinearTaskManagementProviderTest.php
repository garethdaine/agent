<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\ConnectedProvider;
use App\Models\InterrogationBuildTask;
use App\Models\InterrogationSession;
use App\Support\TaskProviders\Drivers\LinearTaskManagementProvider;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LinearTaskManagementProviderTest extends TestCase
{
    public function test_create_task_supports_milestone_parent_and_custom_title(): void
    {
        $provider = new ConnectedProvider([
            'access_token' => 'linear-token',
            'metadata_json' => [
                'team_id' => 'team-123',
            ],
        ]);

        $session = new InterrogationSession([
            'id' => 42,
        ]);

        $task = new InterrogationBuildTask([
            'title' => 'Original parent title',
        ]);

        $driver = new LinearTaskManagementProvider;
        $capturedPayload = null;

        Http::fake(function (HttpRequest $request) use (&$capturedPayload) {
            $payload = $request->data();
            $query = (string) ($payload['query'] ?? '');

            if (str_contains($query, 'query IssueLabels')) {
                return Http::response([
                    'data' => [
                        'issueLabels' => [
                            'nodes' => [],
                        ],
                    ],
                ]);
            }

            if (str_contains($query, 'mutation CreateIssue')) {
                $capturedPayload = $payload;

                return Http::response([
                    'data' => [
                        'issueCreate' => [
                            'success' => true,
                            'issue' => [
                                'id' => 'issue-123',
                                'identifier' => 'AGE-1',
                                'url' => 'https://linear.app/acme/issue/AGE-1',
                            ],
                        ],
                    ],
                ]);
            }

            return Http::response([
                'errors' => [
                    ['message' => 'Unexpected query'],
                ],
            ], 400);
        });

        $issue = $driver->createTask(
            $provider,
            $session,
            $task,
            'project-123',
            2,
            ['agent-build'],
            'Task description',
            'milestone-123',
            'parent-123',
            'Custom child title',
        );

        $this->assertSame('issue-123', $issue['id']);
        $this->assertIsArray($capturedPayload);
        $this->assertSame('Custom child title', data_get($capturedPayload, 'variables.input.title'));
        $this->assertSame('milestone-123', data_get($capturedPayload, 'variables.input.projectMilestoneId'));
        $this->assertSame('parent-123', data_get($capturedPayload, 'variables.input.parentId'));
    }

    public function test_create_project_uses_short_description_and_full_content(): void
    {
        $provider = new ConnectedProvider([
            'access_token' => 'linear-token',
            'metadata_json' => [
                'team_id' => 'team-123',
            ],
        ]);

        $session = new InterrogationSession([
            'id' => 42,
            'feature_brief' => str_repeat('b', 3_000),
            'summary_json' => [
                'summary_markdown' => str_repeat('s', 8_000),
            ],
        ]);

        $driver = new LinearTaskManagementProvider;
        $capturedPayload = null;

        Http::fake(function (HttpRequest $request) use (&$capturedPayload) {
            $payload = $request->data();
            $query = (string) ($payload['query'] ?? '');

            if (str_contains($query, 'mutation CreateProject')) {
                $capturedPayload = $payload;

                return Http::response([
                    'data' => [
                        'projectCreate' => [
                            'success' => true,
                            'project' => [
                                'id' => 'project-123',
                                'name' => 'Agent Build',
                                'url' => 'https://linear.app/project-123',
                            ],
                        ],
                    ],
                ]);
            }

            return Http::response([
                'errors' => [
                    ['message' => 'Unexpected query'],
                ],
            ], 400);
        });

        $project = $driver->createProject($provider, $session, 'Agent Build');

        $this->assertSame('project-123', $project['id']);
        $this->assertIsArray($capturedPayload);

        $description = (string) data_get($capturedPayload, 'variables.input.description', '');
        $this->assertNotSame('', $description);
        $this->assertLessThanOrEqual(255, mb_strlen($description));
        $this->assertStringNotContainsString('##', $description);

        $content = (string) data_get($capturedPayload, 'variables.input.content', '');
        $this->assertNotSame('', $content);
        $this->assertStringContainsString('## Feature Brief', $content);
        $this->assertStringContainsString('## Summary Context', $content);
        $this->assertStringContainsString(str_repeat('s', 120), $content);
    }

    public function test_create_project_prefers_user_presentable_graphql_error_message(): void
    {
        $provider = new ConnectedProvider([
            'access_token' => 'linear-token',
            'metadata_json' => [
                'team_id' => 'team-123',
            ],
        ]);

        $session = new InterrogationSession([
            'id' => 42,
        ]);

        $driver = new LinearTaskManagementProvider;

        Http::fake([
            '*' => Http::response([
                'errors' => [
                    [
                        'message' => 'Argument Validation Error',
                        'extensions' => [
                            'userPresentableMessage' => 'description must be shorter than or equal to 255 characters.',
                        ],
                    ],
                ],
                'data' => null,
            ], 200),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('description must be shorter than or equal to 255 characters.');

        $driver->createProject($provider, $session, 'Agent Build');
    }

    public function test_update_task_status_sends_issue_id_as_top_level_argument(): void
    {
        $provider = new ConnectedProvider([
            'access_token' => 'linear-token',
            'metadata_json' => [
                'team_id' => 'team-123',
            ],
        ]);

        $session = new InterrogationSession;
        $driver = new LinearTaskManagementProvider;

        $capturedUpdatePayload = null;

        Http::fake(function (HttpRequest $request) use (&$capturedUpdatePayload) {
            $payload = $request->data();
            $query = (string) ($payload['query'] ?? '');

            if (str_contains($query, 'TeamWorkflowStates')) {
                return Http::response([
                    'data' => [
                        'team' => [
                            'states' => [
                                'nodes' => [
                                    [
                                        'id' => 'state-completed',
                                        'type' => 'completed',
                                        'name' => 'Done',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ]);
            }

            if (str_contains($query, 'mutation UpdateIssue')) {
                $capturedUpdatePayload = $payload;

                return Http::response([
                    'data' => [
                        'issueUpdate' => [
                            'success' => true,
                        ],
                    ],
                ]);
            }

            return Http::response([
                'errors' => [
                    ['message' => 'Unexpected query'],
                ],
            ], 400);
        });

        $driver->updateTaskStatus(
            $provider,
            $session,
            'issue-123',
            'completed',
            null,
        );

        $this->assertIsArray($capturedUpdatePayload);
        $this->assertSame('issue-123', data_get($capturedUpdatePayload, 'variables.id'));
        $this->assertSame('state-completed', data_get($capturedUpdatePayload, 'variables.input.stateId'));
        $this->assertFalse(array_key_exists('id', (array) data_get($capturedUpdatePayload, 'variables.input', [])));
    }
}
