<?php

declare(strict_types=1);

namespace App\Support\Interrogation;

use App\Models\InterrogationBuildTask;
use App\Models\InterrogationSession;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

class GitOperationsService
{
    /**
     * Prepare the git environment before a build task starts executing.
     *
     * @return array{working_directory: string, env: array<string, string>, branch: ?string, worktree_path: ?string}
     */
    public function prepareForTask(
        InterrogationSession $session,
        InterrogationBuildTask $task,
    ): array {
        $git = $session->gitSettings();
        $projectDir = (string) $session->project_directory;
        $workingDir = $projectDir;
        $worktreePath = null;
        $branch = null;
        $env = [];

        if (! $this->isGitRepo($projectDir)) {
            return [
                'working_directory' => $workingDir,
                'env' => $env,
                'branch' => null,
                'worktree_path' => null,
            ];
        }

        // Branching logic
        if ($git['branching_enabled']) {
            $branch = $this->createFeatureBranch($projectDir, $session, $task, $git['branch_prefix']);
            $env['GIT_BRANCH'] = $branch;
        } elseif ($git['target_branch'] !== null) {
            $branch = $git['target_branch'];
            $this->checkoutBranch($projectDir, $branch);
            $env['GIT_BRANCH'] = $branch;
        }

        // Worktree logic
        if ($git['worktree_enabled']) {
            $worktreePath = $this->createWorktree($projectDir, $session, $task, $branch);
            $workingDir = $worktreePath;
        }

        // Commit settings as env vars for the AI runner
        if ($git['commit_enabled']) {
            $env['GIT_COMMIT_ENABLED'] = '1';
            if ($git['conventional_commits']) {
                $env['GIT_CONVENTIONAL_COMMITS'] = '1';
            }
        }

        return [
            'working_directory' => $workingDir,
            'env' => $env,
            'branch' => $branch,
            'worktree_path' => $worktreePath,
        ];
    }

    /**
     * Cleanup after a build task completes (remove worktree if needed).
     */
    public function cleanupAfterTask(
        InterrogationSession $session,
        InterrogationBuildTask $task,
    ): void {
        $git = $session->gitSettings();
        if (! $git['worktree_enabled']) {
            return;
        }

        $worktreePath = $this->resolveWorktreePath((string) $session->project_directory, $session, $task);
        if ($worktreePath !== null && is_dir($worktreePath)) {
            $this->removeWorktree((string) $session->project_directory, $worktreePath);
        }
    }

    public function isGitRepo(string $directory): bool
    {
        if ($directory === '' || ! is_dir($directory)) {
            return false;
        }

        $gitDir = rtrim($directory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'.git';

        return is_dir($gitDir) || is_file($gitDir);
    }

    private function createFeatureBranch(
        string $projectDir,
        InterrogationSession $session,
        InterrogationBuildTask $task,
        ?string $prefix,
    ): string {
        $slug = Str::slug(Str::limit(trim((string) $task->title), 40, ''), '-');
        $branchName = sprintf('s%d-t%02d-%s', (int) $session->id, (int) $task->sequence, $slug);

        if ($prefix !== null && $prefix !== '') {
            $prefix = rtrim($prefix, '/').'/';
            $branchName = $prefix.$branchName;
        }

        // If branch already exists, reuse it
        $checkProcess = $this->runGit($projectDir, ['git', 'rev-parse', '--verify', '--quiet', $branchName], 5);
        if ($checkProcess->isSuccessful()) {
            $this->runGit($projectDir, ['git', 'checkout', $branchName]);

            return $branchName;
        }

        $this->runGit($projectDir, ['git', 'checkout', '-b', $branchName]);

        return $branchName;
    }

    private function checkoutBranch(string $projectDir, string $branch): void
    {
        $process = $this->runGit($projectDir, ['git', 'checkout', $branch]);

        if (! $process->isSuccessful()) {
            throw new RuntimeException(sprintf(
                'Failed to checkout branch [%s]: %s',
                $branch,
                trim($process->getErrorOutput())
            ));
        }
    }

    private function createWorktree(
        string $projectDir,
        InterrogationSession $session,
        InterrogationBuildTask $task,
        ?string $branch,
    ): string {
        $worktreePath = $this->resolveWorktreePath($projectDir, $session, $task);
        if ($worktreePath === null) {
            throw new RuntimeException('Failed to resolve worktree path.');
        }

        // If worktree already exists, reuse it
        if (is_dir($worktreePath)) {
            return $worktreePath;
        }

        $worktreeDir = dirname($worktreePath);
        if (! is_dir($worktreeDir)) {
            @mkdir($worktreeDir, 0777, true);
        }

        $args = ['git', 'worktree', 'add', $worktreePath];
        if ($branch !== null) {
            $args[] = $branch;
        }

        $process = $this->runGit($projectDir, $args, 60);
        if (! $process->isSuccessful()) {
            throw new RuntimeException(sprintf(
                'Failed to create worktree at [%s]: %s',
                $worktreePath,
                trim($process->getErrorOutput())
            ));
        }

        return $worktreePath;
    }

    private function resolveWorktreePath(
        string $projectDir,
        InterrogationSession $session,
        InterrogationBuildTask $task,
    ): ?string {
        if ($projectDir === '') {
            return null;
        }

        return rtrim($projectDir, DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR.'.agent-worktrees'
            .DIRECTORY_SEPARATOR.sprintf('s%d-t%02d', (int) $session->id, (int) $task->sequence);
    }

    private function removeWorktree(string $projectDir, string $worktreePath): void
    {
        $this->runGit($projectDir, ['git', 'worktree', 'remove', '--force', $worktreePath], 30);
    }

    private function runGit(string $cwd, array $args, int $timeout = 30): Process
    {
        $process = new Process($args, $cwd);
        $process->setTimeout($timeout);
        $process->run();

        return $process;
    }
}
