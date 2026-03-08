<?php

declare(strict_types=1);

namespace App\Support\TaskProviders\Drivers;

use App\Models\ConnectedProvider;
use App\Models\InterrogationBuildTask;
use App\Models\InterrogationSession;
use App\Support\TaskProviders\Contracts\TaskManagementProviderDriver;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class LinearTaskManagementProvider implements TaskManagementProviderDriver
{
    private const LINEAR_PROJECT_DESCRIPTION_MAX_LENGTH = 255;

    public function key(): string
    {
        return 'linear';
    }

    public function authorizationUrl(string $state, string $redirectUri): string
    {
        $clientId = trim((string) config('services.linear.client_id'));
        if ($clientId === '') {
            throw new RuntimeException('Linear client id is not configured.');
        }

        $endpoint = trim((string) config('services.linear.authorize_url', 'https://linear.app/oauth/authorize'));

        return $endpoint.'?'.http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => trim((string) config('services.linear.oauth_scopes', 'read write')),
            'state' => $state,
        ]);
    }

    public function exchangeAuthorizationCode(string $code, string $redirectUri): array
    {
        $response = Http::asForm()
            ->acceptJson()
            ->timeout(20)
            ->post($this->tokenEndpoint(), [
                'grant_type' => 'authorization_code',
                'client_id' => trim((string) config('services.linear.client_id')),
                'client_secret' => trim((string) config('services.linear.client_secret')),
                'redirect_uri' => $redirectUri,
                'code' => $code,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Linear OAuth token exchange failed: '.$response->status());
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            throw new RuntimeException('Linear OAuth token response was invalid.');
        }

        $accessToken = trim((string) ($payload['access_token'] ?? ''));
        if ($accessToken === '') {
            throw new RuntimeException('Linear OAuth token response did not include an access token.');
        }

        $scopes = [];
        if (is_string($payload['scope'] ?? null)) {
            $scopes = array_values(array_filter(explode(' ', (string) $payload['scope'])));
        }

        $expiresAt = null;
        if (is_numeric($payload['expires_in'] ?? null)) {
            $expiresAt = now('UTC')->addSeconds((int) $payload['expires_in']);
        }

        return [
            'access_token' => $accessToken,
            'refresh_token' => trim((string) ($payload['refresh_token'] ?? '')) ?: null,
            'token_type' => trim((string) ($payload['token_type'] ?? 'Bearer')) ?: 'Bearer',
            'expires_at' => $expiresAt,
            'scopes' => $scopes,
            'raw' => $payload,
        ];
    }

    public function fetchIdentity(string $accessToken): array
    {
        $data = $this->graphql(
            $accessToken,
            <<<'GRAPHQL'
query ViewerContext {
  viewer {
    id
    name
    email
  }
  teams(first: 1) {
    nodes {
      id
      name
      key
    }
  }
  organization {
    id
    name
    urlKey
  }
}
GRAPHQL,
        );

        $viewer = is_array($data['viewer'] ?? null) ? $data['viewer'] : [];
        $team = Arr::first((array) data_get($data, 'teams.nodes', []));
        $team = is_array($team) ? $team : [];
        $organization = is_array($data['organization'] ?? null) ? $data['organization'] : [];

        $teamId = trim((string) ($team['id'] ?? ''));
        if ($teamId === '') {
            throw new RuntimeException('Linear OAuth succeeded but no accessible team was found.');
        }

        return [
            'provider_user_id' => trim((string) ($viewer['id'] ?? '')) ?: null,
            'provider_user_name' => trim((string) ($viewer['name'] ?? '')) ?: null,
            'provider_user_email' => trim((string) ($viewer['email'] ?? '')) ?: null,
            'workspace_id' => trim((string) ($organization['id'] ?? '')) ?: null,
            'workspace_name' => trim((string) ($organization['name'] ?? '')) ?: null,
            'workspace_key' => trim((string) ($organization['urlKey'] ?? '')) ?: null,
            'team_id' => $teamId,
            'team_name' => trim((string) ($team['name'] ?? '')) ?: null,
            'team_key' => trim((string) ($team['key'] ?? '')) ?: null,
        ];
    }

    public function createProject(ConnectedProvider $provider, InterrogationSession $session, string $projectName): array
    {
        $teamId = $this->teamIdFromProvider($provider);

        $data = $this->graphql(
            $this->providerAccessToken($provider),
            <<<'GRAPHQL'
mutation CreateProject($input: ProjectCreateInput!) {
  projectCreate(input: $input) {
    success
    project {
      id
      name
      url
    }
  }
}
GRAPHQL,
            [
                'input' => [
                    'name' => mb_substr($projectName, 0, 80),
                    'description' => $this->projectDescription($session),
                    'content' => $this->projectContent($session),
                    'teamIds' => [$teamId],
                ],
            ],
        );

        $project = data_get($data, 'projectCreate.project');
        if (! is_array($project) || trim((string) ($project['id'] ?? '')) === '') {
            throw new RuntimeException('Linear project creation failed: no project id returned.');
        }

        return [
            'id' => trim((string) $project['id']),
            'name' => trim((string) ($project['name'] ?? '')) ?: null,
            'url' => trim((string) ($project['url'] ?? '')) ?: null,
        ];
    }

    public function listProjects(ConnectedProvider $provider): array
    {
        $teamId = $this->teamIdFromProvider($provider);

        $data = $this->graphql(
            $this->providerAccessToken($provider),
            <<<'GRAPHQL'
query TeamProjects($teamId: String!) {
  team(id: $teamId) {
    projects(first: 250) {
      nodes {
        id
        name
        url
        state
      }
    }
  }
}
GRAPHQL,
            [
                'teamId' => $teamId,
            ],
        );

        $nodes = data_get($data, 'team.projects.nodes', []);
        if (! is_array($nodes)) {
            return [];
        }

        $projects = [];

        foreach ($nodes as $node) {
            if (! is_array($node)) {
                continue;
            }

            $id = trim((string) ($node['id'] ?? ''));
            if ($id === '') {
                continue;
            }

            $projects[] = [
                'id' => $id,
                'name' => trim((string) ($node['name'] ?? '')) ?: null,
                'url' => trim((string) ($node['url'] ?? '')) ?: null,
                'state' => trim((string) ($node['state'] ?? '')) ?: null,
            ];
        }

        usort($projects, static function (array $left, array $right): int {
            return strcasecmp((string) ($left['name'] ?? ''), (string) ($right['name'] ?? ''));
        });

        return array_values($projects);
    }

    public function listTeams(ConnectedProvider $provider): array
    {
        $data = $this->graphql(
            $this->providerAccessToken($provider),
            <<<'GRAPHQL'
query Teams {
  teams(first: 250) {
    nodes {
      id
      name
      key
    }
  }
}
GRAPHQL,
        );

        $nodes = data_get($data, 'teams.nodes', []);
        if (! is_array($nodes)) {
            return [];
        }

        $teams = [];

        foreach ($nodes as $node) {
            if (! is_array($node)) {
                continue;
            }

            $id = trim((string) ($node['id'] ?? ''));
            if ($id === '') {
                continue;
            }

            $teams[] = [
                'id' => $id,
                'name' => trim((string) ($node['name'] ?? '')) ?: null,
                'key' => trim((string) ($node['key'] ?? '')) ?: null,
            ];
        }

        usort($teams, static function (array $left, array $right): int {
            return strcasecmp((string) ($left['name'] ?? ''), (string) ($right['name'] ?? ''));
        });

        return array_values($teams);
    }

    public function listProjectMilestones(ConnectedProvider $provider, string $projectId): array
    {
        $data = $this->graphql(
            $this->providerAccessToken($provider),
            <<<'GRAPHQL'
query ProjectMilestones($projectId: String!) {
  project(id: $projectId) {
    projectMilestones(first: 250) {
      nodes {
        id
        name
      }
    }
  }
}
GRAPHQL,
            [
                'projectId' => $projectId,
            ],
        );

        $nodes = data_get($data, 'project.projectMilestones.nodes', []);
        if (! is_array($nodes)) {
            return [];
        }

        $milestones = [];

        foreach ($nodes as $node) {
            if (! is_array($node)) {
                continue;
            }

            $id = trim((string) ($node['id'] ?? ''));
            if ($id === '') {
                continue;
            }

            $milestones[] = [
                'id' => $id,
                'name' => trim((string) ($node['name'] ?? '')) ?: null,
            ];
        }

        usort($milestones, static function (array $left, array $right): int {
            return strcasecmp((string) ($left['name'] ?? ''), (string) ($right['name'] ?? ''));
        });

        return array_values($milestones);
    }

    public function createProjectMilestone(
        ConnectedProvider $provider,
        InterrogationSession $session,
        string $projectId,
        string $name,
        ?string $description = null,
    ): array {
        $data = $this->graphql(
            $this->providerAccessToken($provider),
            <<<'GRAPHQL'
mutation CreateProjectMilestone($input: ProjectMilestoneCreateInput!) {
  projectMilestoneCreate(input: $input) {
    success
    projectMilestone {
      id
      name
    }
  }
}
GRAPHQL,
            [
                'input' => array_filter([
                    'projectId' => $projectId,
                    'name' => mb_substr(trim((string) $name), 0, 80),
                    'description' => is_string($description) ? trim($description) : null,
                ], static fn ($value): bool => $value !== null && (! is_string($value) || $value !== '')),
            ],
        );

        $milestone = data_get($data, 'projectMilestoneCreate.projectMilestone');
        if (! is_array($milestone) || trim((string) ($milestone['id'] ?? '')) === '') {
            throw new RuntimeException('Linear project milestone creation failed: no milestone id returned.');
        }

        return [
            'id' => trim((string) $milestone['id']),
            'name' => trim((string) ($milestone['name'] ?? '')) ?: null,
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
        $teamId = $this->teamIdFromProvider($provider);

        $input = [
            'teamId' => $teamId,
            'projectId' => $projectId,
            'title' => mb_substr(trim((string) ($title ?? $task->title)), 0, 80),
            'description' => $description,
            'priority' => max(0, min(4, $priority)),
        ];

        if (is_string($projectMilestoneId) && trim($projectMilestoneId) !== '') {
            $input['projectMilestoneId'] = trim($projectMilestoneId);
        }

        if (is_string($parentTaskId) && trim($parentTaskId) !== '') {
            $input['parentId'] = trim($parentTaskId);
        }

        $labelIds = $this->resolveIssueLabelIds(
            $this->providerAccessToken($provider),
            $labels,
        );
        if ($labelIds !== []) {
            $input['labelIds'] = $labelIds;
        }

        $data = $this->graphql(
            $this->providerAccessToken($provider),
            <<<'GRAPHQL'
mutation CreateIssue($input: IssueCreateInput!) {
  issueCreate(input: $input) {
    success
    issue {
      id
      identifier
      url
    }
  }
}
GRAPHQL,
            [
                'input' => $input,
            ],
        );

        $issue = data_get($data, 'issueCreate.issue');
        if (! is_array($issue) || trim((string) ($issue['id'] ?? '')) === '') {
            throw new RuntimeException('Linear issue creation failed: no issue id returned.');
        }

        return [
            'id' => trim((string) $issue['id']),
            'identifier' => trim((string) ($issue['identifier'] ?? '')) ?: null,
            'url' => trim((string) ($issue['url'] ?? '')) ?: null,
        ];
    }

    public function updateTaskStatus(
        ConnectedProvider $provider,
        InterrogationSession $session,
        string $externalTaskId,
        string $status,
        ?string $note = null,
    ): void {
        $teamId = $this->teamIdFromProvider($provider);
        $stateId = $this->resolveStateIdForStatus(
            $this->providerAccessToken($provider),
            $teamId,
            $status,
        );

        if ($stateId === null) {
            return;
        }

        $input = [
            'stateId' => $stateId,
        ];

        if (is_string($note) && trim($note) !== '') {
            $input['description'] = trim($note);
        }

        $this->graphql(
            $this->providerAccessToken($provider),
            <<<'GRAPHQL'
mutation UpdateIssue($id: String!, $input: IssueUpdateInput!) {
  issueUpdate(id: $id, input: $input) {
    success
  }
}
GRAPHQL,
            [
                'id' => $externalTaskId,
                'input' => $input,
            ],
        );
    }

    /**
     * @param  array<int, string>  $labels
     * @return array<int, string>
     */
    private function resolveIssueLabelIds(string $accessToken, array $labels): array
    {
        if ($labels === []) {
            return [];
        }

        $requested = array_values(array_unique(array_map(
            static fn (string $label): string => strtolower(trim($label)),
            array_filter($labels, static fn ($label): bool => is_string($label) && trim($label) !== '')
        )));

        if ($requested === []) {
            return [];
        }

        try {
            $data = $this->graphql(
                $accessToken,
                <<<'GRAPHQL'
query IssueLabels {
  issueLabels(first: 250) {
    nodes {
      id
      name
    }
  }
}
GRAPHQL,
            );
        } catch (\Throwable) {
            return [];
        }

        $nodes = data_get($data, 'issueLabels.nodes', []);
        if (! is_array($nodes)) {
            return [];
        }

        $resolved = [];

        foreach ($nodes as $node) {
            if (! is_array($node)) {
                continue;
            }

            $name = strtolower(trim((string) ($node['name'] ?? '')));
            $id = trim((string) ($node['id'] ?? ''));

            if ($name === '' || $id === '' || ! in_array($name, $requested, true)) {
                continue;
            }

            $resolved[] = $id;
        }

        return array_values(array_unique($resolved));
    }

    private function resolveStateIdForStatus(string $accessToken, string $teamId, string $status): ?string
    {
        $data = $this->graphql(
            $accessToken,
            <<<'GRAPHQL'
query TeamWorkflowStates($teamId: String!) {
  team(id: $teamId) {
    states {
      nodes {
        id
        type
        name
      }
    }
  }
}
GRAPHQL,
            [
                'teamId' => $teamId,
            ],
        );

        $nodes = data_get($data, 'team.states.nodes', []);
        if (! is_array($nodes) || $nodes === []) {
            return null;
        }

        $targetType = match (strtolower(trim($status))) {
            InterrogationBuildTask::STATUS_COMPLETED => 'completed',
            InterrogationBuildTask::STATUS_FAILED => 'canceled',
            InterrogationBuildTask::STATUS_BLOCKED, InterrogationBuildTask::STATUS_IN_PROGRESS => 'started',
            default => 'unstarted',
        };

        $match = Arr::first($nodes, static function ($node) use ($targetType): bool {
            return is_array($node) && strtolower(trim((string) ($node['type'] ?? ''))) === $targetType;
        });

        if (! is_array($match) && $targetType === 'canceled') {
            $match = Arr::first($nodes, static function ($node): bool {
                return is_array($node) && strtolower(trim((string) ($node['type'] ?? ''))) === 'completed';
            });
        }

        if (! is_array($match)) {
            $match = Arr::first($nodes, static function ($node) use ($targetType): bool {
                if (! is_array($node)) {
                    return false;
                }

                $name = strtolower(trim((string) ($node['name'] ?? '')));

                return $targetType === 'canceled'
                    ? str_contains($name, 'cancel') || str_contains($name, 'won\'t do')
                    : false;
            });
        }

        if (! is_array($match)) {
            return null;
        }

        $id = trim((string) ($match['id'] ?? ''));

        return $id !== '' ? $id : null;
    }

    /**
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed>
     */
    private function graphql(string $accessToken, string $query, array $variables = []): array
    {
        $response = $this->request($accessToken)
            ->post($this->graphqlEndpoint(), [
                'query' => $query,
                'variables' => (object) $variables,
            ]);

        if (! $response->successful()) {
            $message = trim((string) data_get($response->json(), 'errors.0.message', ''));
            $error = $message !== '' ? $message : trim((string) $response->body());
            if ($error === '') {
                $error = 'HTTP '.$response->status();
            }

            throw new RuntimeException('Linear API request failed: '.$error);
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            throw new RuntimeException('Linear API returned an invalid payload.');
        }

        if (is_array($payload['errors'] ?? null) && $payload['errors'] !== []) {
            $firstError = Arr::first($payload['errors']);
            $message = 'Linear API returned an error.';

            if (is_array($firstError)) {
                $userMessage = trim((string) data_get($firstError, 'extensions.userPresentableMessage', ''));
                $message = $userMessage !== ''
                    ? $userMessage
                    : trim((string) ($firstError['message'] ?? $message));
            }

            throw new RuntimeException($message !== '' ? $message : 'Linear API returned an error.');
        }

        $data = $payload['data'] ?? null;
        if (! is_array($data)) {
            throw new RuntimeException('Linear API response did not include data.');
        }

        return $data;
    }

    private function request(string $accessToken): PendingRequest
    {
        return Http::withToken($accessToken)
            ->acceptJson()
            ->asJson()
            ->timeout(20);
    }

    private function providerAccessToken(ConnectedProvider $provider): string
    {
        $token = trim((string) ($provider->access_token ?? ''));

        if ($token === '') {
            throw new RuntimeException('Provider token is missing. Reconnect the provider.');
        }

        return $token;
    }

    private function teamIdFromProvider(ConnectedProvider $provider): string
    {
        $metadata = is_array($provider->metadata_json) ? $provider->metadata_json : [];
        $teamId = trim((string) ($metadata['team_id'] ?? ''));

        if ($teamId === '') {
            $teamId = trim((string) data_get($metadata, 'identity.team_id', ''));
        }

        if ($teamId === '') {
            throw new RuntimeException('No Linear team is configured for this provider connection. Reconnect Linear.');
        }

        return $teamId;
    }

    private function projectDescription(InterrogationSession $session): string
    {
        $parts = [
            'Generated by Agent requirements discovery build pipeline.',
            'Session #'.$session->id,
        ];

        $name = trim((string) ($session->name ?? ''));
        if ($name !== '') {
            $parts[] = 'Name: '.mb_substr($name, 0, 80);
        }

        $type = trim((string) ($session->interrogation_type ?? ''));
        if ($type !== '') {
            $parts[] = 'Type: '.$type;
        }

        $description = implode(' ', $parts);

        return mb_substr($description, 0, self::LINEAR_PROJECT_DESCRIPTION_MAX_LENGTH);
    }

    private function projectContent(InterrogationSession $session): string
    {
        $summary = trim((string) data_get($session->summary_json, 'summary_markdown', ''));
        $brief = trim((string) ($session->feature_brief ?? ''));

        $content = [
            '## Agent Build Context',
            '',
            '- Session ID: '.$session->id,
        ];

        $name = trim((string) ($session->name ?? ''));
        if ($name !== '') {
            $content[] = '- Session Name: '.$name;
        }

        $type = trim((string) ($session->interrogation_type ?? ''));
        if ($type !== '') {
            $content[] = '- Interrogation Type: '.$type;
        }

        if ($brief !== '') {
            $content[] = '';
            $content[] = '## Feature Brief';
            $content[] = '';
            $content[] = $brief;
        }

        if ($summary !== '') {
            $content[] = '';
            $content[] = '## Summary Context';
            $content[] = '';
            $content[] = $summary;
        }

        return trim(implode("\n", $content));
    }

    private function graphqlEndpoint(): string
    {
        return trim((string) config('services.linear.graphql_url', 'https://api.linear.app/graphql'));
    }

    private function tokenEndpoint(): string
    {
        return trim((string) config('services.linear.token_url', 'https://api.linear.app/oauth/token'));
    }
}
