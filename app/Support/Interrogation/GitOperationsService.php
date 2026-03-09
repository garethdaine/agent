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
     * @return array{working_directory: string, env: array<string, string>, branch: ?string, worktree_path: ?string, baseline_head: ?string}
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
                'baseline_head' => null,
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

        // Record HEAD after branch/worktree setup — this is the baseline for post-task enforcement
        $baselineHead = $this->getCurrentHead($workingDir);

        return [
            'working_directory' => $workingDir,
            'env' => $env,
            'branch' => $branch,
            'worktree_path' => $worktreePath,
            'baseline_head' => $baselineHead,
        ];
    }

    /**
     * Comprehensive server-side enforcement of git operation settings after
     * a build task completes.
     *
     * Enforcement steps (in order):
     * 1. Branch enforcement — verify AI stayed on the expected branch, restore if drifted
     * 2. Conventional commit enforcement — squash non-conventional commits since baseline
     * 3. Uncommitted changes — commit any work left uncommitted by the AI runner
     * 4. Worktree boundary — warn if changes detected outside the worktree
     *
     * @return array{actions: list<array{type: string, detail: string}>, errors: list<string>}
     */
    public function enforceAfterTask(
        InterrogationSession $session,
        InterrogationBuildTask $task,
        ?string $baselineHead = null,
    ): array {
        $git = $session->gitSettings();
        $projectDir = (string) $session->project_directory;
        /** @var list<array{type: string, detail: string}> $actions */
        $actions = [];
        /** @var list<string> $errors */
        $errors = [];

        if (! $this->isGitRepo($projectDir)) {
            return ['actions' => $actions, 'errors' => $errors];
        }

        // Resolve working directory
        $workingDir = $projectDir;
        if ($git['worktree_enabled']) {
            $worktreePath = $this->resolveWorktreePath($projectDir, $session, $task);
            if ($worktreePath !== null && is_dir($worktreePath)) {
                $workingDir = $worktreePath;
            }
        }

        // 1. Branch enforcement
        $branchResult = $this->enforceBranch($session, $task, $workingDir, $git);
        $actions = array_merge($actions, $branchResult['actions']);
        $errors = array_merge($errors, $branchResult['errors']);

        // 2–3. Commit enforcement (only when commit_enabled)
        if ($git['commit_enabled']) {
            $squashed = false;

            // 2. Conventional commit enforcement: squash non-conventional commits
            if ($git['conventional_commits'] && $baselineHead !== null) {
                $conventionalResult = $this->enforceConventionalCommits($session, $task, $workingDir, $baselineHead);
                $actions = array_merge($actions, $conventionalResult['actions']);
                $errors = array_merge($errors, $conventionalResult['errors']);
                $squashed = $conventionalResult['squashed'];
            }

            // 3. Uncommitted changes (skip if squash already handled everything)
            if (! $squashed) {
                $uncommittedResult = $this->enforceUncommittedChanges($session, $task, $workingDir, $git['conventional_commits']);
                $actions = array_merge($actions, $uncommittedResult['actions']);
                $errors = array_merge($errors, $uncommittedResult['errors']);
            }
        }

        // 4. Worktree boundary check
        if ($git['worktree_enabled'] && $workingDir !== $projectDir) {
            $mainStatus = $this->runGit($projectDir, ['git', 'status', '--porcelain']);
            if ($mainStatus->isSuccessful() && trim($mainStatus->getOutput()) !== '') {
                $errors[] = 'Changes detected in main repository while worktree was active. AI may have escaped worktree boundary.';
            }
        }

        return ['actions' => $actions, 'errors' => $errors];
    }

    /**
     * Commit any uncommitted changes after a build task completes.
     *
     * This is server-side enforcement — the AI runner *should* commit per the
     * task markdown instructions, but if it doesn't, this method ensures no
     * work is left uncommitted between tasks.
     *
     * @return array{committed: bool, message: ?string, error: ?string}
     */
    public function commitAfterTask(
        InterrogationSession $session,
        InterrogationBuildTask $task,
    ): array {
        $git = $session->gitSettings();

        if (! $git['commit_enabled']) {
            return ['committed' => false, 'message' => null, 'error' => null];
        }

        $projectDir = (string) $session->project_directory;

        if (! $this->isGitRepo($projectDir)) {
            return ['committed' => false, 'message' => null, 'error' => null];
        }

        // Resolve the actual working directory (worktree or main)
        $workingDir = $projectDir;
        if ($git['worktree_enabled']) {
            $worktreePath = $this->resolveWorktreePath($projectDir, $session, $task);
            if ($worktreePath !== null && is_dir($worktreePath)) {
                $workingDir = $worktreePath;
            }
        }

        // Check for uncommitted changes (staged + unstaged + untracked)
        $statusProcess = $this->runGit($workingDir, ['git', 'status', '--porcelain']);
        if (! $statusProcess->isSuccessful() || trim($statusProcess->getOutput()) === '') {
            return ['committed' => false, 'message' => null, 'error' => null];
        }

        // Stage all changes
        $addProcess = $this->runGit($workingDir, ['git', 'add', '-A'], 60);
        if (! $addProcess->isSuccessful()) {
            return [
                'committed' => false,
                'message' => null,
                'error' => 'git add failed: '.trim($addProcess->getErrorOutput()),
            ];
        }

        // Verify there are staged changes (git add -A may have resolved everything)
        $diffProcess = $this->runGit($workingDir, ['git', 'diff', '--cached', '--quiet']);
        if ($diffProcess->isSuccessful()) {
            // Exit code 0 means no staged differences
            return ['committed' => false, 'message' => null, 'error' => null];
        }

        // Build commit message
        $message = $this->buildPostTaskCommitMessage($session, $task, $git['conventional_commits']);

        $commitProcess = $this->runGit($workingDir, [
            'git', 'commit', '-m', $message, '--no-verify',
        ], 60);

        if (! $commitProcess->isSuccessful()) {
            return [
                'committed' => false,
                'message' => null,
                'error' => 'git commit failed: '.trim($commitProcess->getErrorOutput()),
            ];
        }

        return ['committed' => true, 'message' => $message, 'error' => null];
    }

    /**
     * Build the expected feature branch name for a given session/task.
     *
     * Extracted so both createFeatureBranch() and enforcement can share
     * the same naming logic.
     */
    public function buildFeatureBranchName(
        InterrogationSession $session,
        InterrogationBuildTask $task,
        ?string $prefix = null,
    ): string {
        $slug = Str::slug(Str::limit(trim((string) $task->title), 40, ''), '-');
        $branchName = sprintf('s%d-t%02d-%s', (int) $session->id, (int) $task->sequence, $slug);

        if ($prefix !== null && $prefix !== '') {
            $prefix = rtrim($prefix, '/').'/';
            $branchName = $prefix.$branchName;
        }

        return $branchName;
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

    // ── Enforcement helpers ─────────────────────────────────────────────────

    /**
     * Enforce branch: verify AI stayed on the expected branch, restore if drifted.
     *
     * @param  array<string, mixed>  $git
     * @return array{actions: list<array{type: string, detail: string}>, errors: list<string>}
     */
    private function enforceBranch(
        InterrogationSession $session,
        InterrogationBuildTask $task,
        string $workingDir,
        array $git,
    ): array {
        /** @var list<array{type: string, detail: string}> $actions */
        $actions = [];
        /** @var list<string> $errors */
        $errors = [];

        $expectedBranch = $this->resolveExpectedBranch($session, $task, $git);
        if ($expectedBranch === null) {
            return ['actions' => $actions, 'errors' => $errors];
        }

        $currentBranch = $this->getCurrentBranch($workingDir);
        if ($currentBranch === null) {
            $errors[] = 'Could not determine current branch for enforcement check.';

            return ['actions' => $actions, 'errors' => $errors];
        }

        if ($currentBranch === $expectedBranch) {
            return ['actions' => $actions, 'errors' => $errors];
        }

        // AI drifted to a different branch — attempt to stash, restore, and pop
        $this->runGit($workingDir, ['git', 'stash', '--include-untracked'], 30);
        $checkoutProcess = $this->runGit($workingDir, ['git', 'checkout', $expectedBranch], 10);

        if ($checkoutProcess->isSuccessful()) {
            $this->runGit($workingDir, ['git', 'stash', 'pop'], 30);
            $actions[] = [
                'type' => 'branch_restored',
                'detail' => sprintf('Restored from [%s] to expected branch [%s].', $currentBranch, $expectedBranch),
            ];
        } else {
            // Restore to the drifted branch if checkout failed
            $this->runGit($workingDir, ['git', 'checkout', $currentBranch], 10);
            $this->runGit($workingDir, ['git', 'stash', 'pop'], 30);
            $errors[] = sprintf(
                'Branch drift detected: expected [%s], found [%s]. Restore failed: %s',
                $expectedBranch,
                $currentBranch,
                trim($checkoutProcess->getErrorOutput())
            );
        }

        return ['actions' => $actions, 'errors' => $errors];
    }

    /**
     * Enforce conventional commit format: squash non-conventional commits since baseline HEAD.
     *
     * When squashing, also stages and includes any uncommitted changes so the caller
     * can skip the separate uncommitted-changes step.
     *
     * @return array{actions: list<array{type: string, detail: string}>, errors: list<string>, squashed: bool}
     */
    private function enforceConventionalCommits(
        InterrogationSession $session,
        InterrogationBuildTask $task,
        string $workingDir,
        string $baselineHead,
    ): array {
        /** @var list<array{type: string, detail: string}> $actions */
        $actions = [];
        /** @var list<string> $errors */
        $errors = [];

        // Safety: verify baseline is an ancestor of HEAD
        if (! $this->isAncestor($workingDir, $baselineHead)) {
            $errors[] = sprintf(
                'Baseline HEAD [%.8s] is not an ancestor of current HEAD — skipping conventional commit enforcement.',
                $baselineHead
            );

            return ['actions' => $actions, 'errors' => $errors, 'squashed' => false];
        }

        // Get commits since baseline
        $commits = $this->getCommitsSince($workingDir, $baselineHead);

        // Check for non-conventional commit messages
        $nonConventional = array_filter(
            $commits,
            fn (array $c): bool => ! $this->isConventionalCommitMessage($c['message'])
        );

        if ($nonConventional === []) {
            // All existing commits are already conventional — no squash needed
            return ['actions' => $actions, 'errors' => $errors, 'squashed' => false];
        }

        // Stage all uncommitted changes so they're included in the squash
        $this->runGit($workingDir, ['git', 'add', '-A'], 60);

        // Squash all commits since baseline into one conventional commit
        $resetProcess = $this->runGit($workingDir, ['git', 'reset', '--soft', $baselineHead], 30);
        if (! $resetProcess->isSuccessful()) {
            $errors[] = 'Conventional commit squash failed (reset): '.trim($resetProcess->getErrorOutput());

            return ['actions' => $actions, 'errors' => $errors, 'squashed' => false];
        }

        // Check if there's anything to commit after the reset
        $diffProcess = $this->runGit($workingDir, ['git', 'diff', '--cached', '--quiet']);
        if ($diffProcess->isSuccessful()) {
            // Nothing to commit — edge case where reset left nothing staged
            return ['actions' => $actions, 'errors' => $errors, 'squashed' => true];
        }

        $message = $this->buildPostTaskCommitMessage($session, $task, true);

        $commitProcess = $this->runGit($workingDir, ['git', 'commit', '-m', $message, '--no-verify'], 60);
        if (! $commitProcess->isSuccessful()) {
            $errors[] = 'Conventional commit squash failed (commit): '.trim($commitProcess->getErrorOutput());

            return ['actions' => $actions, 'errors' => $errors, 'squashed' => false];
        }

        $actions[] = [
            'type' => 'conventional_commits_squashed',
            'detail' => sprintf(
                'Squashed %d commit(s) (%d non-conventional) into conventional format.',
                count($commits),
                count($nonConventional)
            ),
        ];

        return ['actions' => $actions, 'errors' => $errors, 'squashed' => true];
    }

    /**
     * Enforce uncommitted changes: stage and commit any work left uncommitted.
     *
     * @return array{actions: list<array{type: string, detail: string}>, errors: list<string>}
     */
    private function enforceUncommittedChanges(
        InterrogationSession $session,
        InterrogationBuildTask $task,
        string $workingDir,
        bool $conventionalCommits,
    ): array {
        /** @var list<array{type: string, detail: string}> $actions */
        $actions = [];
        /** @var list<string> $errors */
        $errors = [];

        // Check for uncommitted changes (staged + unstaged + untracked)
        $statusProcess = $this->runGit($workingDir, ['git', 'status', '--porcelain']);
        if (! $statusProcess->isSuccessful() || trim($statusProcess->getOutput()) === '') {
            return ['actions' => $actions, 'errors' => $errors];
        }

        // Stage all changes
        $addProcess = $this->runGit($workingDir, ['git', 'add', '-A'], 60);
        if (! $addProcess->isSuccessful()) {
            $errors[] = 'git add failed: '.trim($addProcess->getErrorOutput());

            return ['actions' => $actions, 'errors' => $errors];
        }

        // Verify there are staged changes
        $diffProcess = $this->runGit($workingDir, ['git', 'diff', '--cached', '--quiet']);
        if ($diffProcess->isSuccessful()) {
            return ['actions' => $actions, 'errors' => $errors];
        }

        $message = $this->buildPostTaskCommitMessage($session, $task, $conventionalCommits);

        $commitProcess = $this->runGit($workingDir, ['git', 'commit', '-m', $message, '--no-verify'], 60);
        if (! $commitProcess->isSuccessful()) {
            $errors[] = 'git commit failed: '.trim($commitProcess->getErrorOutput());

            return ['actions' => $actions, 'errors' => $errors];
        }

        $actions[] = [
            'type' => 'uncommitted_changes_committed',
            'detail' => $message,
        ];

        return ['actions' => $actions, 'errors' => $errors];
    }

    /**
     * Resolve the expected branch name based on git settings.
     *
     * @param  array<string, mixed>  $git
     */
    private function resolveExpectedBranch(
        InterrogationSession $session,
        InterrogationBuildTask $task,
        array $git,
    ): ?string {
        if ($git['branching_enabled']) {
            return $this->buildFeatureBranchName($session, $task, $git['branch_prefix']);
        }

        return $git['target_branch'];
    }

    // ── Git inspection helpers ──────────────────────────────────────────────

    /**
     * Get the current HEAD SHA.
     */
    private function getCurrentHead(string $workingDir): ?string
    {
        $process = $this->runGit($workingDir, ['git', 'rev-parse', 'HEAD'], 5);
        if (! $process->isSuccessful()) {
            return null;
        }

        return trim($process->getOutput());
    }

    /**
     * Get the current branch name (null if detached HEAD).
     */
    private function getCurrentBranch(string $workingDir): ?string
    {
        $process = $this->runGit($workingDir, ['git', 'rev-parse', '--abbrev-ref', 'HEAD'], 5);
        if (! $process->isSuccessful()) {
            return null;
        }

        $branch = trim($process->getOutput());

        return $branch !== 'HEAD' ? $branch : null;
    }

    /**
     * Check if a commit is an ancestor of the current HEAD.
     */
    private function isAncestor(string $workingDir, string $ancestor): bool
    {
        $process = $this->runGit($workingDir, ['git', 'merge-base', '--is-ancestor', $ancestor, 'HEAD'], 5);

        return $process->isSuccessful();
    }

    /**
     * Get list of commits since a baseline HEAD.
     *
     * @return list<array{hash: string, message: string}>
     */
    private function getCommitsSince(string $workingDir, string $baselineHead): array
    {
        $process = $this->runGit($workingDir, [
            'git', 'log', '--format=%H|||%s', $baselineHead.'..HEAD',
        ], 10);

        if (! $process->isSuccessful()) {
            return [];
        }

        $output = trim($process->getOutput());
        if ($output === '') {
            return [];
        }

        $commits = [];
        foreach (explode("\n", $output) as $line) {
            $parts = explode('|||', $line, 2);
            if (count($parts) === 2) {
                $commits[] = [
                    'hash' => $parts[0],
                    'message' => $parts[1],
                ];
            }
        }

        return $commits;
    }

    /**
     * Check if a commit message follows conventional commit format.
     */
    private function isConventionalCommitMessage(string $message): bool
    {
        return (bool) preg_match(
            '/^(feat|fix|refactor|test|docs|style|chore|perf|ci|build)(\(.*?\))?!?:/',
            $message
        );
    }

    // ── Commit message helpers ──────────────────────────────────────────────

    /**
     * Build a commit message for the post-task server-side commit.
     */
    private function buildPostTaskCommitMessage(
        InterrogationSession $session,
        InterrogationBuildTask $task,
        bool $conventionalCommits,
    ): string {
        $title = trim((string) $task->title);
        $sequence = (int) $task->sequence;
        $sessionId = (int) $session->id;

        if ($conventionalCommits) {
            // Infer type from task category or title
            $type = $this->inferConventionalType($task);

            return sprintf(
                "%s(s%d-t%02d): %s\n\nServer-side commit of uncommitted changes from build task %d.",
                $type,
                $sessionId,
                $sequence,
                $this->truncateCommitSubject($title, 72 - strlen($type) - strlen(sprintf('(s%d-t%02d): ', $sessionId, $sequence))),
                $sequence,
            );
        }

        return sprintf(
            "Build S%d T%02d: %s\n\nServer-side commit of uncommitted changes from build task %d.",
            $sessionId,
            $sequence,
            $this->truncateCommitSubject($title, 72 - strlen(sprintf('Build S%d T%02d: ', $sessionId, $sequence))),
            $sequence,
        );
    }

    /**
     * Infer the conventional commit type from task category or title.
     */
    private function inferConventionalType(InterrogationBuildTask $task): string
    {
        $category = strtolower(trim((string) ($task->task_category?->value ?? ''))); // @phpstan-ignore nullCoalesce.expr
        $title = strtolower(trim((string) $task->title));

        return match (true) {
            str_contains($category, 'test'), str_contains($title, 'test') => 'test',
            str_contains($category, 'doc'), str_contains($title, 'doc') => 'docs',
            str_contains($category, 'style'), str_contains($title, 'lint'), str_contains($title, 'pint') => 'style',
            str_contains($category, 'refactor'), str_contains($title, 'refactor'), str_contains($title, 'decompos') => 'refactor',
            str_contains($category, 'fix'), str_contains($title, 'fix'), str_contains($title, 'bug') => 'fix',
            str_contains($category, 'ci'), str_contains($title, 'ci'), str_contains($title, 'workflow') => 'ci',
            str_contains($category, 'perf'), str_contains($title, 'perf'), str_contains($title, 'optimi') => 'perf',
            str_contains($category, 'chore'), str_contains($title, 'chore'), str_contains($title, 'clean') => 'chore',
            default => 'feat',
        };
    }

    /**
     * Truncate a commit subject line to fit within the max length.
     */
    private function truncateCommitSubject(string $subject, int $maxLength): string
    {
        if ($maxLength < 10) {
            $maxLength = 10;
        }

        if (mb_strlen($subject) <= $maxLength) {
            return $subject;
        }

        return mb_substr($subject, 0, $maxLength - 3).'...';
    }

    // ── Git operation primitives ─────────────────────────────────────────────

    private function createFeatureBranch(
        string $projectDir,
        InterrogationSession $session,
        InterrogationBuildTask $task,
        ?string $prefix,
    ): string {
        $branchName = $this->buildFeatureBranchName($session, $task, $prefix);

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

    /**
     * Get a structured diff of changes between a baseline commit and HEAD.
     *
     * @return array{available: bool, files: list<array{path: string, status: string, additions: int, deletions: int, hunks: list<array{header: string, lines: list<array{type: string, content: string, old_line: ?int, new_line: ?int}>}>}>}
     */
    public function getTaskDiff(string $projectDir, string $baselineHead, ?string $worktreePath = null): array
    {
        $cwd = is_string($worktreePath) && $worktreePath !== '' ? $worktreePath : $projectDir;

        if (! is_dir($cwd.'/.git') && ! is_file($cwd.'/.git')) {
            return ['available' => false, 'files' => []];
        }

        $process = $this->runGit($cwd, [
            'git', 'diff', '--unified=3', $baselineHead.'..HEAD',
        ], 30);

        if (! $process->isSuccessful()) {
            return ['available' => false, 'files' => []];
        }

        $output = $process->getOutput();

        if (trim($output) === '') {
            return ['available' => true, 'files' => []];
        }

        return [
            'available' => true,
            'files' => $this->parseUnifiedDiff($output),
        ];
    }

    /**
     * Parse unified diff output into structured array.
     *
     * @return list<array{path: string, status: string, additions: int, deletions: int, hunks: list<array{header: string, lines: list<array{type: string, content: string, old_line: ?int, new_line: ?int}>}>}>
     */
    public function parseUnifiedDiff(string $output): array
    {
        $files = [];
        $currentFile = null;
        $currentHunk = null;
        $oldLine = 0;
        $newLine = 0;

        $lines = explode("\n", $output);

        foreach ($lines as $line) {
            // New file diff header
            if (str_starts_with($line, 'diff --git')) {
                if ($currentFile !== null) {
                    if ($currentHunk !== null) {
                        $currentFile['hunks'][] = $currentHunk;
                    }
                    $files[] = $currentFile;
                }

                $path = $this->extractPathFromDiffHeader($line);
                $currentFile = [
                    'path' => $path,
                    'status' => 'modified',
                    'additions' => 0,
                    'deletions' => 0,
                    'hunks' => [],
                ];
                $currentHunk = null;

                continue;
            }

            if ($currentFile === null) {
                continue;
            }

            // Detect file status
            if (str_starts_with($line, 'new file mode')) {
                $currentFile['status'] = 'added';

                continue;
            }
            if (str_starts_with($line, 'deleted file mode')) {
                $currentFile['status'] = 'deleted';

                continue;
            }
            if (str_starts_with($line, 'rename from')) {
                $currentFile['status'] = 'renamed';

                continue;
            }

            // Hunk header
            if (str_starts_with($line, '@@')) {
                if ($currentHunk !== null) {
                    $currentFile['hunks'][] = $currentHunk;
                }

                $currentHunk = [
                    'header' => $line,
                    'lines' => [],
                ];

                // Parse @@ -old,count +new,count @@
                if (preg_match('/^@@ -(\d+)(?:,\d+)? \+(\d+)(?:,\d+)? @@/', $line, $matches)) {
                    $oldLine = (int) $matches[1];
                    $newLine = (int) $matches[2];
                }

                continue;
            }

            if ($currentHunk === null) {
                continue;
            }

            // Skip binary file markers and index lines
            if (str_starts_with($line, 'index ') || str_starts_with($line, '---') || str_starts_with($line, '+++') || str_starts_with($line, 'Binary files')) {
                continue;
            }

            // Diff content lines
            if (str_starts_with($line, '+')) {
                $currentHunk['lines'][] = [
                    'type' => 'added',
                    'content' => substr($line, 1),
                    'old_line' => null,
                    'new_line' => $newLine,
                ];
                $currentFile['additions']++;
                $newLine++;
            } elseif (str_starts_with($line, '-')) {
                $currentHunk['lines'][] = [
                    'type' => 'removed',
                    'content' => substr($line, 1),
                    'old_line' => $oldLine,
                    'new_line' => null,
                ];
                $currentFile['deletions']++;
                $oldLine++;
            } elseif (str_starts_with($line, ' ') || $line === '') {
                $content = $line === '' ? '' : substr($line, 1);
                $currentHunk['lines'][] = [
                    'type' => 'context',
                    'content' => $content,
                    'old_line' => $oldLine,
                    'new_line' => $newLine,
                ];
                $oldLine++;
                $newLine++;
            }
        }

        // Flush last file
        if ($currentFile !== null) {
            if ($currentHunk !== null) {
                $currentFile['hunks'][] = $currentHunk;
            }
            $files[] = $currentFile;
        }

        return $files;
    }

    /**
     * Get list of files changed since a baseline commit.
     *
     * @return list<string>
     */
    public function getChangedFilesSince(string $projectDir, string $baselineHead, ?string $worktreePath = null): array
    {
        $cwd = is_string($worktreePath) && $worktreePath !== '' ? $worktreePath : $projectDir;

        if (! is_dir($cwd.'/.git') && ! is_file($cwd.'/.git')) {
            return [];
        }

        $process = $this->runGit($cwd, [
            'git', 'diff', '--name-only', $baselineHead.'..HEAD',
        ], 15);

        if (! $process->isSuccessful()) {
            return [];
        }

        $output = trim($process->getOutput());

        if ($output === '') {
            return [];
        }

        return array_values(array_filter(explode("\n", $output), fn (string $line): bool => trim($line) !== ''));
    }

    private function extractPathFromDiffHeader(string $header): string
    {
        // "diff --git a/path/to/file b/path/to/file"
        if (preg_match('#^diff --git a/(.+?) b/(.+)$#', $header, $matches)) {
            return $matches[2];
        }

        return 'unknown';
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
