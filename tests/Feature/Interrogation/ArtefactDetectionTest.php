<?php

declare(strict_types=1);

namespace Tests\Feature\Interrogation;

use App\Models\InterrogationBuildTask;
use App\Models\InterrogationSession;
use App\Models\User;
use App\Support\Interrogation\GitOperationsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ArtefactDetectionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        config(['agent.interrogation.max_active_sessions' => 50]);
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_parse_unified_diff_parses_single_file_diff(): void
    {
        $gitService = app(GitOperationsService::class);

        $diffOutput = <<<'DIFF'
diff --git a/src/Example.php b/src/Example.php
index abc123..def456 100644
--- a/src/Example.php
+++ b/src/Example.php
@@ -1,5 +1,6 @@
 <?php

+use App\Models\User;
 class Example
 {
-    public function old(): void {}
+    public function new(): void {}
DIFF;

        $files = $gitService->parseUnifiedDiff($diffOutput);

        $this->assertCount(1, $files);
        $this->assertSame('src/Example.php', $files[0]['path']);
        $this->assertSame('modified', $files[0]['status']);
        $this->assertSame(2, $files[0]['additions']);
        $this->assertSame(1, $files[0]['deletions']);
        $this->assertCount(1, $files[0]['hunks']);
        $this->assertSame('@@ -1,5 +1,6 @@', $files[0]['hunks'][0]['header']);

        // Verify line types
        $lines = $files[0]['hunks'][0]['lines'];
        $addedLines = array_filter($lines, fn ($l) => $l['type'] === 'added');
        $removedLines = array_filter($lines, fn ($l) => $l['type'] === 'removed');
        $contextLines = array_filter($lines, fn ($l) => $l['type'] === 'context');

        $this->assertCount(2, $addedLines);
        $this->assertCount(1, $removedLines);
        $this->assertGreaterThanOrEqual(3, count($contextLines));
    }

    public function test_parse_unified_diff_parses_multiple_files(): void
    {
        $gitService = app(GitOperationsService::class);

        $diffOutput = <<<'DIFF'
diff --git a/src/A.php b/src/A.php
index abc123..def456 100644
--- a/src/A.php
+++ b/src/A.php
@@ -1,3 +1,4 @@
 <?php
+// Added line
 class A {}
diff --git a/src/B.php b/src/B.php
new file mode 100644
index 0000000..abc123
--- /dev/null
+++ b/src/B.php
@@ -0,0 +1,3 @@
+<?php
+
+class B {}
DIFF;

        $files = $gitService->parseUnifiedDiff($diffOutput);

        $this->assertCount(2, $files);
        $this->assertSame('src/A.php', $files[0]['path']);
        $this->assertSame('src/B.php', $files[1]['path']);
        $this->assertSame(1, $files[0]['additions']);
        $this->assertSame(0, $files[0]['deletions']);
        $this->assertSame(3, $files[1]['additions']);
        $this->assertSame(0, $files[1]['deletions']);
    }

    public function test_parse_unified_diff_handles_empty_output(): void
    {
        $gitService = app(GitOperationsService::class);

        $files = $gitService->parseUnifiedDiff('');

        $this->assertIsArray($files);
        $this->assertEmpty($files);
    }

    public function test_parse_unified_diff_handles_new_file(): void
    {
        $gitService = app(GitOperationsService::class);

        $diffOutput = <<<'DIFF'
diff --git a/docs/README.md b/docs/README.md
new file mode 100644
index 0000000..abc123
--- /dev/null
+++ b/docs/README.md
@@ -0,0 +1,2 @@
+# Documentation
+Hello world
DIFF;

        $files = $gitService->parseUnifiedDiff($diffOutput);

        $this->assertCount(1, $files);
        $this->assertSame('docs/README.md', $files[0]['path']);
        $this->assertSame(2, $files[0]['additions']);
        $this->assertSame(0, $files[0]['deletions']);
    }

    public function test_parse_unified_diff_handles_deleted_file(): void
    {
        $gitService = app(GitOperationsService::class);

        $diffOutput = <<<'DIFF'
diff --git a/old/file.txt b/old/file.txt
deleted file mode 100644
index abc123..0000000
--- a/old/file.txt
+++ /dev/null
@@ -1,3 +0,0 @@
-Line 1
-Line 2
-Line 3
DIFF;

        $files = $gitService->parseUnifiedDiff($diffOutput);

        $this->assertCount(1, $files);
        $this->assertSame('old/file.txt', $files[0]['path']);
        $this->assertSame(0, $files[0]['additions']);
        $this->assertSame(3, $files[0]['deletions']);
    }

    public function test_parse_unified_diff_tracks_line_numbers(): void
    {
        $gitService = app(GitOperationsService::class);

        $diffOutput = <<<'DIFF'
diff --git a/src/Test.php b/src/Test.php
index abc123..def456 100644
--- a/src/Test.php
+++ b/src/Test.php
@@ -10,4 +10,5 @@
 context line
-removed line
+added line 1
+added line 2
 another context
DIFF;

        $files = $gitService->parseUnifiedDiff($diffOutput);
        $lines = $files[0]['hunks'][0]['lines'];

        // First line is context starting at old_line=10, new_line=10
        $contextLine = $lines[0];
        $this->assertSame('context', $contextLine['type']);
        $this->assertSame(10, $contextLine['old_line']);
        $this->assertSame(10, $contextLine['new_line']);

        // Removed line at old_line=11, no new_line
        $removedLine = $lines[1];
        $this->assertSame('removed', $removedLine['type']);
        $this->assertSame(11, $removedLine['old_line']);
        $this->assertNull($removedLine['new_line']);

        // Added lines at new_line=11 and 12
        $addedLine1 = $lines[2];
        $this->assertSame('added', $addedLine1['type']);
        $this->assertNull($addedLine1['old_line']);
        $this->assertSame(11, $addedLine1['new_line']);

        $addedLine2 = $lines[3];
        $this->assertSame('added', $addedLine2['type']);
        $this->assertNull($addedLine2['old_line']);
        $this->assertSame(12, $addedLine2['new_line']);
    }

    public function test_artefact_aggregation_includes_task_metadata(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->create([
                'status' => InterrogationSession::STATUS_BUILD_EXECUTING,
                'phase' => InterrogationSession::PHASE_BUILD_EXECUTION,
                'metadata_json' => [
                    'build' => ['status' => 'running'],
                ],
            ]);

        $task = InterrogationBuildTask::factory()
            ->for($session, 'session')
            ->completed()
            ->create([
                'sequence' => 1,
                'title' => 'Create documentation',
                'metadata_json' => [
                    'artefacts' => [
                        [
                            'name' => 'guide.md',
                            'type' => 'document',
                            'path' => 'docs/guide.md',
                            'detected_at' => now()->toIso8601String(),
                        ],
                        [
                            'name' => 'schema.json',
                            'type' => 'config',
                            'path' => 'output/schema.json',
                            'detected_at' => now()->toIso8601String(),
                        ],
                    ],
                ],
            ]);

        $buildService = app(\App\Services\Interrogation\InterrogationBuildService::class);
        $buildState = $buildService->transformBuildState($session, true);

        $this->assertCount(2, $buildState['artefacts']);

        // Each artefact includes task_id and task_title
        foreach ($buildState['artefacts'] as $artefact) {
            $this->assertSame($task->id, $artefact['task_id']);
            $this->assertSame('Create documentation', $artefact['task_title']);
            $this->assertArrayHasKey('name', $artefact);
            $this->assertArrayHasKey('type', $artefact);
            $this->assertArrayHasKey('path', $artefact);
            $this->assertArrayHasKey('detected_at', $artefact);
        }
    }

    public function test_artefact_type_classification(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->create([
                'status' => InterrogationSession::STATUS_BUILD_EXECUTING,
                'phase' => InterrogationSession::PHASE_BUILD_EXECUTION,
                'metadata_json' => [
                    'build' => ['status' => 'running'],
                ],
            ]);

        $artefacts = [
            ['name' => 'readme.md', 'type' => 'document', 'path' => 'docs/readme.md', 'detected_at' => now()->toIso8601String()],
            ['name' => 'notes.txt', 'type' => 'document', 'path' => 'docs/notes.txt', 'detected_at' => now()->toIso8601String()],
            ['name' => 'config.json', 'type' => 'config', 'path' => 'output/config.json', 'detected_at' => now()->toIso8601String()],
            ['name' => 'settings.yaml', 'type' => 'config', 'path' => 'output/settings.yaml', 'detected_at' => now()->toIso8601String()],
            ['name' => 'page.html', 'type' => 'markup', 'path' => 'generated/page.html', 'detected_at' => now()->toIso8601String()],
            ['name' => 'data.csv', 'type' => 'data', 'path' => 'reports/data.csv', 'detected_at' => now()->toIso8601String()],
        ];

        InterrogationBuildTask::factory()
            ->for($session, 'session')
            ->completed()
            ->create([
                'sequence' => 1,
                'title' => 'Multi-type task',
                'metadata_json' => ['artefacts' => $artefacts],
            ]);

        $buildService = app(\App\Services\Interrogation\InterrogationBuildService::class);
        $buildState = $buildService->transformBuildState($session, true);

        $this->assertCount(6, $buildState['artefacts']);

        $typeMap = [];
        foreach ($buildState['artefacts'] as $artefact) {
            $typeMap[$artefact['name']] = $artefact['type'];
        }

        $this->assertSame('document', $typeMap['readme.md']);
        $this->assertSame('document', $typeMap['notes.txt']);
        $this->assertSame('config', $typeMap['config.json']);
        $this->assertSame('config', $typeMap['settings.yaml']);
        $this->assertSame('markup', $typeMap['page.html']);
        $this->assertSame('data', $typeMap['data.csv']);
    }

    public function test_artefact_content_endpoint_works_with_known_project_file(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->create([
                'status' => InterrogationSession::STATUS_BUILD_EXECUTING,
                'phase' => InterrogationSession::PHASE_BUILD_EXECUTION,
                'project_directory' => base_path(),
            ]);

        $task = InterrogationBuildTask::factory()
            ->for($session, 'session')
            ->completed()
            ->create([
                'sequence' => 1,
                'metadata_json' => [
                    'artefacts' => [
                        ['name' => 'composer.json', 'type' => 'config', 'path' => 'composer.json'],
                    ],
                ],
            ]);

        $response = $this->getJson(
            "/agent/api/v1/interrogation/sessions/{$session->id}/build-tasks/{$task->id}/artefact-content?path=composer.json"
        )->assertOk();

        $this->assertNotEmpty($response->json('data.content'));
        $this->assertFalse($response->json('data.truncated'));
    }

    public function test_artefact_content_rejects_path_outside_project(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->create([
                'status' => InterrogationSession::STATUS_BUILD_EXECUTING,
                'phase' => InterrogationSession::PHASE_BUILD_EXECUTION,
                'project_directory' => base_path(),
            ]);

        $task = InterrogationBuildTask::factory()
            ->for($session, 'session')
            ->completed()
            ->create(['sequence' => 1]);

        // Attempt path traversal
        $response = $this->getJson(
            "/agent/api/v1/interrogation/sessions/{$session->id}/build-tasks/{$task->id}/artefact-content?path=../../../etc/hosts"
        );

        $this->assertContains($response->status(), [404, 422]);
    }

    public function test_artefact_content_rejects_overly_long_path(): void
    {
        $session = InterrogationSession::factory()
            ->for($this->user)
            ->create([
                'status' => InterrogationSession::STATUS_BUILD_EXECUTING,
                'phase' => InterrogationSession::PHASE_BUILD_EXECUTION,
                'project_directory' => base_path(),
            ]);

        $task = InterrogationBuildTask::factory()
            ->for($session, 'session')
            ->completed()
            ->create(['sequence' => 1]);

        $longPath = str_repeat('a', 1025);

        $this->getJson(
            "/agent/api/v1/interrogation/sessions/{$session->id}/build-tasks/{$task->id}/artefact-content?path={$longPath}"
        )->assertStatus(422);
    }
}
