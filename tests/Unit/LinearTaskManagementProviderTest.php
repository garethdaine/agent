<?php

namespace Tests\Unit;

use App\Models\ConnectedProvider;
use App\Models\InterrogationSession;
use App\Support\TaskProviders\Drivers\LinearTaskManagementProvider;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LinearTaskManagementProviderTest extends TestCase
{
    public function test_update_task_status_sends_issue_id_as_top_level_argument(): void
    {
        $provider = new ConnectedProvider([
            'access_token' => 'linear-token',
            'metadata_json' => [
                'team_id' => 'team-123',
            ],
        ]);

        $session = new InterrogationSession();
        $driver = new LinearTaskManagementProvider();

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
