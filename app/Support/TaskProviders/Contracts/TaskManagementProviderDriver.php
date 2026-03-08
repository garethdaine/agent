<?php

declare(strict_types=1);

namespace App\Support\TaskProviders\Contracts;

use App\Models\ConnectedProvider;
use App\Models\InterrogationBuildTask;
use App\Models\InterrogationSession;

interface TaskManagementProviderDriver
{
    public function key(): string;

    public function authorizationUrl(string $state, string $redirectUri): string;

    /**
     * @return array<string, mixed>
     */
    public function exchangeAuthorizationCode(string $code, string $redirectUri): array;

    /**
     * @return array<string, mixed>
     */
    public function fetchIdentity(string $accessToken): array;

    /**
     * @return array{id:string,name:?string,url:?string}
     */
    public function createProject(ConnectedProvider $provider, InterrogationSession $session, string $projectName): array;

    /**
     * @return array<int, array{id:string,name:?string,url:?string,state:?string}>
     */
    public function listProjects(ConnectedProvider $provider): array;

    /**
     * @return array<int, array{id:string,name:?string,key:?string}>
     */
    public function listTeams(ConnectedProvider $provider): array;

    /**
     * @return array<int, array{id:string,name:?string}>
     */
    public function listProjectMilestones(ConnectedProvider $provider, string $projectId): array;

    /**
     * @return array{id:string,name:?string}
     */
    public function createProjectMilestone(
        ConnectedProvider $provider,
        InterrogationSession $session,
        string $projectId,
        string $name,
        ?string $description = null,
    ): array;

    /**
     * @param  array<int, string>  $labels
     * @return array{id:string,identifier:?string,url:?string}
     */
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
    ): array;

    public function updateTaskStatus(
        ConnectedProvider $provider,
        InterrogationSession $session,
        string $externalTaskId,
        string $status,
        ?string $note = null,
    ): void;
}
