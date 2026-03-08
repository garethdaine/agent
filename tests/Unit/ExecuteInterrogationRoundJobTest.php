<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Jobs\ExecuteInterrogationRoundJob;
use App\Jobs\ExecuteInterrogationSummaryJob;
use App\Models\InterrogationEvent;
use App\Models\InterrogationSession;
use App\Models\User;
use App\Support\Interrogation\AdapterFactory;
use App\Support\Interrogation\Contracts\InterrogationRunnerAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ExecuteInterrogationRoundJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_round_retries_without_resume_after_initial_resume_failure(): void
    {
        $session = $this->interrogatingSession();
        $session->cli_session_id = '019c4d5e-7f04-7231-85e5-9c4f3e00c5f3';
        $session->save();

        $adapter = $this->mock(InterrogationRunnerAdapter::class);
        $adapter->shouldReceive('buildQuestionCommand')
            ->once()
            ->withArgs(function (InterrogationSession $sessionArg): bool {
                return (string) $sessionArg->cli_session_id === '019c4d5e-7f04-7231-85e5-9c4f3e00c5f3';
            })
            ->andReturn(['php', '-r', 'fwrite(STDERR, "state db missing rollout path for thread"); exit(1);']);
        $adapter->shouldReceive('buildQuestionCommand')
            ->once()
            ->withArgs(function (InterrogationSession $sessionArg): bool {
                return (string) $sessionArg->cli_session_id === '';
            })
            ->andReturn(['php', '-r', 'echo json_encode(["question_text" => "What auth mode should MCP use first?", "answer_type" => "freetext", "progress_estimate" => 15]);']);
        $adapter->shouldReceive('buildEnvironment')
            ->twice()
            ->andReturn([]);
        $adapter->shouldReceive('parseQuestionResponse')
            ->twice()
            ->andReturnUsing(function (string $output): ?array {
                if (trim($output) === '') {
                    return null;
                }

                return [
                    'question_id' => 'q-auth-mode',
                    'question_text' => 'What auth mode should MCP use first?',
                    'answer_type' => 'freetext',
                    'options' => [],
                    'reasoning' => '',
                    'category' => 'security',
                    'progress_estimate' => 15,
                    'is_complete' => false,
                    'cli_session_id' => 'fresh-session-id',
                ];
            });
        $adapter->shouldReceive('buildReconstructCommand')->never();

        $factory = $this->mock(AdapterFactory::class);
        $factory->shouldReceive('make')
            ->once()
            ->andReturn($adapter);

        $job = new ExecuteInterrogationRoundJob(
            (int) $session->id,
            'Start requirements interrogation. Ask the first question.',
            true,
        );
        $this->app->call([$job, 'handle']);

        $session->refresh();

        $this->assertSame(InterrogationSession::STATUS_INTERROGATING, (string) $session->status);
        $this->assertSame('fresh-session-id', (string) $session->cli_session_id);
        $this->assertNull($session->error_code);

        $this->assertTrue(
            InterrogationEvent::query()
                ->where('interrogation_session_id', $session->id)
                ->where('event_type', InterrogationEvent::TYPE_QUESTION)
                ->where('payload->question_id', 'q-auth-mode')
                ->exists()
        );
    }

    public function test_round_rejects_premature_completion_after_skipping_invalid_question(): void
    {
        Queue::fake();

        $session = $this->interrogatingSession();

        InterrogationEvent::query()->create([
            'interrogation_session_id' => $session->id,
            'event_type' => InterrogationEvent::TYPE_QUESTION,
            'sequence' => 1,
            'payload' => [
                'question_id' => 'q-operational',
                'question_text' => 'Resuming interrogation phase by locating the latest saved unanswered question in this workspace/session state.',
                'answer_type' => 'freetext',
                'options' => [],
                'reasoning' => '',
                'category' => 'progress',
                'progress_estimate' => 5,
                'is_complete' => false,
            ],
            'event_ts' => now('UTC'),
        ]);

        $adapter = $this->mock(InterrogationRunnerAdapter::class);
        $buildQuestionCommandCalls = 0;
        $adapter->shouldReceive('buildQuestionCommand')
            ->andReturnUsing(function () use (&$buildQuestionCommandCalls): array {
                $buildQuestionCommandCalls++;

                if ($buildQuestionCommandCalls === 1) {
                    return ['php', '-r', 'echo json_encode(["question_text" => "Interrogation complete.", "answer_type" => "freetext", "progress_estimate" => 100, "is_complete" => true]);'];
                }

                return ['php', '-r', 'echo json_encode(["question_text" => "Which agent capabilities must be in MVP scope?", "answer_type" => "freetext", "progress_estimate" => 15, "is_complete" => false]);'];
            });
        $adapter->shouldReceive('buildEnvironment')
            ->andReturn([]);
        $parseQuestionResponseCalls = 0;
        $adapter->shouldReceive('parseQuestionResponse')
            ->andReturnUsing(function () use (&$parseQuestionResponseCalls): array {
                $parseQuestionResponseCalls++;

                if ($parseQuestionResponseCalls === 1) {
                    return [
                        'question_id' => 'q-complete',
                        'question_text' => 'Interrogation complete.',
                        'answer_type' => 'freetext',
                        'options' => [],
                        'reasoning' => '',
                        'category' => 'completion',
                        'progress_estimate' => 100,
                        'is_complete' => true,
                        'cli_session_id' => '',
                    ];
                }

                return [
                    'question_id' => 'q-follow-up',
                    'question_text' => 'Which agent capabilities must be in MVP scope?',
                    'answer_type' => 'freetext',
                    'options' => [],
                    'reasoning' => '',
                    'category' => 'scope',
                    'progress_estimate' => 15,
                    'is_complete' => false,
                    'cli_session_id' => '',
                ];
            });
        $adapter->shouldReceive('buildReconstructCommand')->never();

        $factory = $this->mock(AdapterFactory::class);
        $factory->shouldReceive('make')
            ->once()
            ->andReturn($adapter);

        $job = new ExecuteInterrogationRoundJob(
            (int) $session->id,
            'Question q-operational skipped. Reason: not_applicable',
            false,
            [
                'question_id' => 'q-operational',
                'answer_type' => 'skip',
                'skip_reason' => 'not_applicable',
            ],
        );
        $this->app->call([$job, 'handle']);

        $session->refresh();

        $this->assertSame(InterrogationSession::PHASE_INTERROGATION, (int) $session->phase);
        $this->assertSame(InterrogationSession::STATUS_INTERROGATING, (string) $session->status);
        $this->assertNull($session->error_code);

        $this->assertTrue(
            InterrogationEvent::query()
                ->where('interrogation_session_id', $session->id)
                ->where('event_type', InterrogationEvent::TYPE_QUESTION)
                ->where('payload->question_id', 'q-follow-up')
                ->exists()
        );

        $this->assertFalse(
            InterrogationEvent::query()
                ->where('interrogation_session_id', $session->id)
                ->where('event_type', InterrogationEvent::TYPE_SYSTEM)
                ->where('payload->notice', 'interrogation_complete')
                ->exists()
        );

        Queue::assertNotPushed(ExecuteInterrogationSummaryJob::class);
    }

    public function test_codex_feature_completion_requires_minimum_substantive_answers(): void
    {
        Queue::fake();
        config()->set('agent.interrogation.codex_min_feature_answers', 2);

        $session = $this->interrogatingSession();

        InterrogationEvent::query()->create([
            'interrogation_session_id' => $session->id,
            'event_type' => InterrogationEvent::TYPE_QUESTION,
            'sequence' => 1,
            'payload' => [
                'question_id' => 'q-auth',
                'question_text' => 'Which authentication mode should the MVP support first?',
                'answer_type' => 'choice',
                'options' => ['JWT', 'API key'],
                'reasoning' => '',
                'category' => 'auth',
                'progress_estimate' => 20,
                'is_complete' => false,
            ],
            'event_ts' => now('UTC'),
        ]);

        InterrogationEvent::query()->create([
            'interrogation_session_id' => $session->id,
            'event_type' => InterrogationEvent::TYPE_ANSWER,
            'sequence' => 2,
            'payload' => [
                'question_id' => 'q-auth',
                'answer_type' => 'choice',
                'selected_option' => 'JWT',
            ],
            'event_ts' => now('UTC'),
        ]);

        $adapter = $this->mock(InterrogationRunnerAdapter::class);
        $buildQuestionCommandCalls = 0;
        $adapter->shouldReceive('buildQuestionCommand')
            ->andReturnUsing(function () use (&$buildQuestionCommandCalls): array {
                $buildQuestionCommandCalls++;

                if ($buildQuestionCommandCalls === 1) {
                    return ['php', '-r', 'echo json_encode(["question_text" => "Interrogation complete.", "answer_type" => "freetext", "progress_estimate" => 100, "is_complete" => true]);'];
                }

                return ['php', '-r', 'echo json_encode(["question_text" => "Which event delivery contract should be canonical for v1?", "answer_type" => "choice", "options" => ["SSE", "WebSocket"], "progress_estimate" => 45, "is_complete" => false]);'];
            });
        $adapter->shouldReceive('buildEnvironment')
            ->andReturn([]);
        $parseQuestionResponseCalls = 0;
        $adapter->shouldReceive('parseQuestionResponse')
            ->andReturnUsing(function () use (&$parseQuestionResponseCalls): array {
                $parseQuestionResponseCalls++;

                if ($parseQuestionResponseCalls === 1) {
                    return [
                        'question_id' => 'q-complete',
                        'question_text' => 'Interrogation complete.',
                        'answer_type' => 'freetext',
                        'options' => [],
                        'reasoning' => '',
                        'category' => 'completion',
                        'progress_estimate' => 100,
                        'is_complete' => true,
                        'cli_session_id' => '',
                    ];
                }

                return [
                    'question_id' => 'q-events',
                    'question_text' => 'Which event delivery contract should be canonical for v1?',
                    'answer_type' => 'choice',
                    'options' => ['SSE', 'WebSocket'],
                    'reasoning' => '',
                    'category' => 'contracts',
                    'progress_estimate' => 45,
                    'is_complete' => false,
                    'cli_session_id' => '',
                ];
            });
        $adapter->shouldReceive('buildReconstructCommand')->never();

        $factory = $this->mock(AdapterFactory::class);
        $factory->shouldReceive('make')
            ->once()
            ->andReturn($adapter);

        $job = new ExecuteInterrogationRoundJob((int) $session->id, 'Answered q-auth with JWT.', true);
        $this->app->call([$job, 'handle']);

        $session->refresh();

        $this->assertSame(InterrogationSession::PHASE_INTERROGATION, (int) $session->phase);
        $this->assertSame(InterrogationSession::STATUS_INTERROGATING, (string) $session->status);
        $this->assertTrue(
            InterrogationEvent::query()
                ->where('interrogation_session_id', $session->id)
                ->where('event_type', InterrogationEvent::TYPE_QUESTION)
                ->where('payload->question_id', 'q-events')
                ->exists()
        );
        Queue::assertNotPushed(ExecuteInterrogationSummaryJob::class);
    }

    public function test_round_repairs_duplicate_question_against_answered_history(): void
    {
        $session = $this->interrogatingSession();

        InterrogationEvent::query()->create([
            'interrogation_session_id' => $session->id,
            'event_type' => InterrogationEvent::TYPE_QUESTION,
            'sequence' => 1,
            'payload' => [
                'question_id' => 'q-visibility',
                'question_text' => 'For the first release, what visibility model should documentation use?',
                'answer_type' => 'choice',
                'options' => [
                    'Private-only in-app for authenticated users',
                    'Public docs by default',
                ],
                'reasoning' => '',
                'category' => 'policy',
                'progress_estimate' => 20,
                'is_complete' => false,
            ],
            'event_ts' => now('UTC'),
        ]);

        InterrogationEvent::query()->create([
            'interrogation_session_id' => $session->id,
            'event_type' => InterrogationEvent::TYPE_ANSWER,
            'sequence' => 2,
            'payload' => [
                'question_id' => 'q-visibility',
                'answer_type' => 'choice',
                'selected_option' => 'Private-only in-app for authenticated users',
            ],
            'event_ts' => now('UTC'),
        ]);

        $adapter = $this->mock(InterrogationRunnerAdapter::class);
        $adapter->shouldReceive('buildQuestionCommand')
            ->twice()
            ->andReturn(
                ['php', '-r', 'echo json_encode(["question_text" => "For Phase 1, what visibility model should published documentation and tooltip content use?", "answer_type" => "choice", "options" => ["Authenticated users only","Public by default"], "progress_estimate" => 45, "is_complete" => false]);'],
                ['php', '-r', 'echo json_encode(["question_text" => "Which search engine should back documentation search for Phase 1?", "answer_type" => "choice", "options" => ["Typesense","Meilisearch"], "progress_estimate" => 55, "is_complete" => false]);'],
            );
        $adapter->shouldReceive('buildEnvironment')
            ->twice()
            ->andReturn([]);
        $parseQuestionResponseCalls = 0;
        $adapter->shouldReceive('parseQuestionResponse')
            ->twice()
            ->andReturnUsing(function () use (&$parseQuestionResponseCalls): array {
                $parseQuestionResponseCalls++;

                if ($parseQuestionResponseCalls === 1) {
                    return [
                        'question_id' => 'q-visibility-repeat',
                        'question_text' => 'For Phase 1, what visibility model should published documentation and tooltip content use?',
                        'answer_type' => 'choice',
                        'options' => ['Authenticated users only', 'Public by default'],
                        'reasoning' => '',
                        'category' => 'policy',
                        'progress_estimate' => 45,
                        'is_complete' => false,
                        'cli_session_id' => '',
                    ];
                }

                return [
                    'question_id' => 'q-search-engine',
                    'question_text' => 'Which search engine should back documentation search for Phase 1?',
                    'answer_type' => 'choice',
                    'options' => ['Typesense', 'Meilisearch'],
                    'reasoning' => '',
                    'category' => 'search',
                    'progress_estimate' => 55,
                    'is_complete' => false,
                    'cli_session_id' => '',
                ];
            });
        $adapter->shouldReceive('buildReconstructCommand')->never();

        $factory = $this->mock(AdapterFactory::class);
        $factory->shouldReceive('make')
            ->once()
            ->andReturn($adapter);

        $job = new ExecuteInterrogationRoundJob((int) $session->id, 'Answered q-visibility.', true);
        $this->app->call([$job, 'handle']);

        $session->refresh();

        $this->assertSame(InterrogationSession::STATUS_INTERROGATING, (string) $session->status);
        $this->assertNull($session->error_code);
        $this->assertTrue(
            InterrogationEvent::query()
                ->where('interrogation_session_id', $session->id)
                ->where('event_type', InterrogationEvent::TYPE_QUESTION)
                ->where('payload->question_id', 'q-search-engine')
                ->exists()
        );
        $this->assertFalse(
            InterrogationEvent::query()
                ->where('interrogation_session_id', $session->id)
                ->where('event_type', InterrogationEvent::TYPE_QUESTION)
                ->where('payload->question_id', 'q-visibility-repeat')
                ->exists()
        );
    }

    public function test_round_retries_duplicate_repair_without_resume_for_codex_session(): void
    {
        $session = $this->interrogatingSession();
        $session->cli_session_id = 'codex-session-123';
        $session->save();

        InterrogationEvent::query()->create([
            'interrogation_session_id' => $session->id,
            'event_type' => InterrogationEvent::TYPE_QUESTION,
            'sequence' => 1,
            'payload' => [
                'question_id' => 'q-versioning',
                'question_text' => 'For this automated docs system, what versioning policy should we enforce?',
                'answer_type' => 'choice',
                'options' => [
                    'Keep only a single live version',
                    'Snapshot each release',
                ],
                'reasoning' => '',
                'category' => 'policy',
                'progress_estimate' => 30,
                'is_complete' => false,
            ],
            'event_ts' => now('UTC'),
        ]);

        InterrogationEvent::query()->create([
            'interrogation_session_id' => $session->id,
            'event_type' => InterrogationEvent::TYPE_ANSWER,
            'sequence' => 2,
            'payload' => [
                'question_id' => 'q-versioning',
                'answer_type' => 'choice',
                'selected_option' => 'Keep only a single live version',
            ],
            'event_ts' => now('UTC'),
        ]);

        $commandCalls = 0;
        $adapter = $this->mock(InterrogationRunnerAdapter::class);
        $adapter->shouldReceive('buildQuestionCommand')
            ->times(3)
            ->andReturnUsing(function (InterrogationSession $sessionArg) use (&$commandCalls): array {
                $commandCalls++;

                if ($commandCalls < 3) {
                    $this->assertSame('codex-session-123', (string) $sessionArg->cli_session_id);

                    return ['php', '-r', 'echo json_encode(["question_text" => "How should published product docs and tooltip content be versioned in phase 1?", "answer_type" => "choice", "options" => ["Single live version only","Snapshot per release"], "progress_estimate" => 62, "is_complete" => false]);'];
                }

                $this->assertSame('', (string) $sessionArg->cli_session_id);

                return ['php', '-r', 'echo json_encode(["question_text" => "Should generated docs include code examples in every article?", "answer_type" => "choice", "options" => ["Required by default","Optional per page"], "progress_estimate" => 70, "is_complete" => false]);'];
            });
        $adapter->shouldReceive('buildEnvironment')
            ->times(3)
            ->andReturn([]);
        $parseQuestionResponseCalls = 0;
        $adapter->shouldReceive('parseQuestionResponse')
            ->times(3)
            ->andReturnUsing(function () use (&$parseQuestionResponseCalls): array {
                $parseQuestionResponseCalls++;

                if ($parseQuestionResponseCalls < 3) {
                    return [
                        'question_id' => 'q-versioning-repeat-'.$parseQuestionResponseCalls,
                        'question_text' => 'How should published product docs and tooltip content be versioned in phase 1?',
                        'answer_type' => 'choice',
                        'options' => ['Single live version only', 'Snapshot per release'],
                        'reasoning' => '',
                        'category' => 'policy',
                        'progress_estimate' => 62,
                        'is_complete' => false,
                        'cli_session_id' => 'codex-session-123',
                    ];
                }

                return [
                    'question_id' => 'q-code-examples',
                    'question_text' => 'Should generated docs include code examples in every article?',
                    'answer_type' => 'choice',
                    'options' => ['Required by default', 'Optional per page'],
                    'reasoning' => '',
                    'category' => 'content',
                    'progress_estimate' => 70,
                    'is_complete' => false,
                    'cli_session_id' => '',
                ];
            });
        $adapter->shouldReceive('buildReconstructCommand')->never();

        $factory = $this->mock(AdapterFactory::class);
        $factory->shouldReceive('make')
            ->once()
            ->andReturn($adapter);

        $job = new ExecuteInterrogationRoundJob((int) $session->id, 'Answered q-versioning.', true);
        $this->app->call([$job, 'handle']);

        $session->refresh();

        $this->assertSame(InterrogationSession::STATUS_INTERROGATING, (string) $session->status);
        $this->assertSame('', (string) $session->cli_session_id);
        $this->assertNull($session->error_code);
        $this->assertTrue(
            InterrogationEvent::query()
                ->where('interrogation_session_id', $session->id)
                ->where('event_type', InterrogationEvent::TYPE_QUESTION)
                ->where('payload->question_id', 'q-code-examples')
                ->exists()
        );
    }

    public function test_round_repairs_semantic_duplicate_when_prior_answer_selected_multiple_options(): void
    {
        $session = $this->interrogatingSession();

        InterrogationEvent::query()->create([
            'interrogation_session_id' => $session->id,
            'event_type' => InterrogationEvent::TYPE_QUESTION,
            'sequence' => 1,
            'payload' => [
                'question_id' => 'q-delivery',
                'question_text' => 'Which delivery channels are required for launch communications?',
                'answer_type' => 'choice',
                'options' => ['In-app', 'Email', 'Slack'],
                'reasoning' => '',
                'category' => 'delivery',
                'progress_estimate' => 34,
                'is_complete' => false,
            ],
            'event_ts' => now('UTC'),
        ]);

        InterrogationEvent::query()->create([
            'interrogation_session_id' => $session->id,
            'event_type' => InterrogationEvent::TYPE_ANSWER,
            'sequence' => 2,
            'payload' => [
                'question_id' => 'q-delivery',
                'answer_type' => 'choice',
                'selected_options' => ['In-app', 'Email'],
            ],
            'event_ts' => now('UTC'),
        ]);

        $adapter = $this->mock(InterrogationRunnerAdapter::class);
        $adapter->shouldReceive('buildQuestionCommand')
            ->twice()
            ->andReturn(
                ['php', '-r', 'echo json_encode(["question_text" => "Which delivery channels should launch communications use by default?", "answer_type" => "choice", "options" => ["In-app","Email","Slack"], "progress_estimate" => 44, "is_complete" => false]);'],
                ['php', '-r', 'echo json_encode(["question_text" => "Should launch comms include digest cadence controls in settings?", "answer_type" => "choice", "options" => ["Required","Optional"], "progress_estimate" => 52, "is_complete" => false]);'],
            );
        $adapter->shouldReceive('buildEnvironment')
            ->twice()
            ->andReturn([]);
        $parseQuestionResponseCalls = 0;
        $adapter->shouldReceive('parseQuestionResponse')
            ->twice()
            ->andReturnUsing(function () use (&$parseQuestionResponseCalls): array {
                $parseQuestionResponseCalls++;

                if ($parseQuestionResponseCalls === 1) {
                    return [
                        'question_id' => 'q-delivery-repeat',
                        'question_text' => 'Which delivery channels should launch communications use by default?',
                        'answer_type' => 'choice',
                        'options' => ['In-app', 'Email', 'Slack'],
                        'reasoning' => '',
                        'category' => 'delivery',
                        'progress_estimate' => 44,
                        'is_complete' => false,
                        'cli_session_id' => '',
                    ];
                }

                return [
                    'question_id' => 'q-digest-controls',
                    'question_text' => 'Should launch comms include digest cadence controls in settings?',
                    'answer_type' => 'choice',
                    'options' => ['Required', 'Optional'],
                    'reasoning' => '',
                    'category' => 'delivery',
                    'progress_estimate' => 52,
                    'is_complete' => false,
                    'cli_session_id' => '',
                ];
            });
        $adapter->shouldReceive('buildReconstructCommand')->never();

        $factory = $this->mock(AdapterFactory::class);
        $factory->shouldReceive('make')
            ->once()
            ->andReturn($adapter);

        $job = new ExecuteInterrogationRoundJob((int) $session->id, 'Answered q-delivery.', true);
        $this->app->call([$job, 'handle']);

        $this->assertTrue(
            InterrogationEvent::query()
                ->where('interrogation_session_id', $session->id)
                ->where('event_type', InterrogationEvent::TYPE_QUESTION)
                ->where('payload->question_id', 'q-digest-controls')
                ->exists()
        );
        $this->assertFalse(
            InterrogationEvent::query()
                ->where('interrogation_session_id', $session->id)
                ->where('event_type', InterrogationEvent::TYPE_QUESTION)
                ->where('payload->question_id', 'q-delivery-repeat')
                ->exists()
        );
    }

    public function test_round_does_not_fail_when_duplicate_question_persists_after_repairs(): void
    {
        Queue::fake();

        $session = $this->interrogatingSession();
        $session->cli_session_id = 'codex-session-dup';
        $session->save();

        InterrogationEvent::query()->create([
            'interrogation_session_id' => $session->id,
            'event_type' => InterrogationEvent::TYPE_QUESTION,
            'sequence' => 1,
            'payload' => [
                'question_id' => 'q-visibility',
                'question_text' => 'For the first release, what visibility model should documentation use?',
                'answer_type' => 'choice',
                'options' => ['Private-only in-app for authenticated users', 'Public docs by default'],
                'reasoning' => '',
                'category' => 'policy',
                'progress_estimate' => 20,
                'is_complete' => false,
            ],
            'event_ts' => now('UTC'),
        ]);

        InterrogationEvent::query()->create([
            'interrogation_session_id' => $session->id,
            'event_type' => InterrogationEvent::TYPE_ANSWER,
            'sequence' => 2,
            'payload' => [
                'question_id' => 'q-visibility',
                'answer_type' => 'choice',
                'selected_option' => 'Private-only in-app for authenticated users',
            ],
            'event_ts' => now('UTC'),
        ]);

        $commandCalls = 0;
        $adapter = $this->mock(InterrogationRunnerAdapter::class);
        $adapter->shouldReceive('buildQuestionCommand')
            ->times(4)
            ->andReturnUsing(function (InterrogationSession $sessionArg) use (&$commandCalls): array {
                $commandCalls++;

                if ($commandCalls <= 2) {
                    $this->assertSame('codex-session-dup', (string) $sessionArg->cli_session_id);
                }

                if ($commandCalls >= 3) {
                    $this->assertSame('', (string) $sessionArg->cli_session_id);
                }

                return ['php', '-r', 'echo json_encode(["question_text" => "For Phase 1, what should the default visibility/access policy be for published documentation and tooltip content?", "answer_type" => "choice", "options" => ["Private internal","Public read-only"], "progress_estimate" => 68, "is_complete" => false]);'];
            });
        $adapter->shouldReceive('buildEnvironment')
            ->times(4)
            ->andReturn([]);
        $adapter->shouldReceive('parseQuestionResponse')
            ->times(4)
            ->andReturn([
                'question_id' => 'q-visibility-repeat',
                'question_text' => 'For Phase 1, what should the default visibility/access policy be for published documentation and tooltip content?',
                'answer_type' => 'choice',
                'options' => ['Private internal', 'Public read-only'],
                'reasoning' => '',
                'category' => 'policy',
                'progress_estimate' => 68,
                'is_complete' => false,
                'cli_session_id' => 'codex-session-dup',
            ]);
        $adapter->shouldReceive('buildReconstructCommand')->never();

        $factory = $this->mock(AdapterFactory::class);
        $factory->shouldReceive('make')
            ->once()
            ->andReturn($adapter);

        $job = new ExecuteInterrogationRoundJob((int) $session->id, 'Answered q-visibility.', true);
        $this->app->call([$job, 'handle']);

        $session->refresh();

        $this->assertSame(InterrogationSession::STATUS_INTERROGATING, (string) $session->status);
        $this->assertNull($session->error_code);
        $this->assertSame('', (string) $session->cli_session_id);
        $this->assertTrue(
            InterrogationEvent::query()
                ->where('interrogation_session_id', $session->id)
                ->where('event_type', InterrogationEvent::TYPE_SYSTEM)
                ->where('payload->notice', 'duplicate_question_auto_resolved')
                ->exists()
        );
        $this->assertFalse(
            InterrogationEvent::query()
                ->where('interrogation_session_id', $session->id)
                ->where('event_type', InterrogationEvent::TYPE_QUESTION)
                ->where('payload->question_id', 'q-visibility-repeat')
                ->exists()
        );
        $this->assertTrue(
            InterrogationEvent::query()
                ->where('interrogation_session_id', $session->id)
                ->where('event_type', InterrogationEvent::TYPE_ANSWER)
                ->where('payload->question_id', 'q-visibility-repeat')
                ->where('payload->auto_resolved_duplicate', true)
                ->exists()
        );

        Queue::assertPushed(ExecuteInterrogationRoundJob::class, function (ExecuteInterrogationRoundJob $job) use ($session): bool {
            return (int) $job->sessionId === (int) $session->id
                && $job->isSystemMessage === true
                && $job->duplicateRecoveryDepth === 1;
        });
    }

    public function test_dynamic_question_bank_does_not_repeat_canonical_key_within_run(): void
    {
        Queue::fake();

        $session = $this->interrogatingSession([
            'dynamic_question_bank_mode' => true,
            'dynamic_question_bank' => [
                [
                    'canonical_key' => 'interrogation.reliability.success_contract',
                    'question_id' => 'q-interrogation-reliability-success-contract',
                    'prompt' => 'Which outcome policy defines what counts as a successful run?',
                    'answer_type' => 'choice',
                    'options' => ['Strict terminal success', 'Assisted counts as success'],
                    'depends_on' => [],
                    'category' => 'reliability',
                    'decision_axis' => 'success_contract',
                    'rationale' => 'Needed for reliability scoring.',
                    'priority' => 1,
                ],
            ],
        ]);

        $job = new ExecuteInterrogationRoundJob((int) $session->id, 'Start interrogation.', true);
        $this->app->call([$job, 'handle']);

        $firstQuestion = InterrogationEvent::query()
            ->where('interrogation_session_id', $session->id)
            ->where('event_type', InterrogationEvent::TYPE_QUESTION)
            ->orderByDesc('sequence')
            ->first();

        $this->assertNotNull($firstQuestion);
        $this->assertSame(
            'interrogation.reliability.success_contract',
            data_get($firstQuestion?->payload, 'canonical_key')
        );

        $answerJob = new ExecuteInterrogationRoundJob(
            (int) $session->id,
            'Strict terminal success.',
            false,
            [
                'question_id' => 'q-interrogation-reliability-success-contract',
                'answer_type' => 'choice',
                'selected_option' => 'Strict terminal success',
            ],
        );
        $this->app->call([$answerJob, 'handle']);

        $this->assertSame(
            1,
            InterrogationEvent::query()
                ->where('interrogation_session_id', $session->id)
                ->where('event_type', InterrogationEvent::TYPE_QUESTION)
                ->where('payload->canonical_key', 'interrogation.reliability.success_contract')
                ->count()
        );
    }

    public function test_dynamic_question_bank_respects_dependency_gating(): void
    {
        $session = $this->interrogatingSession([
            'dynamic_question_bank_mode' => true,
            'dynamic_question_bank' => [
                [
                    'canonical_key' => 'interrogation.reliability.scoring_model',
                    'question_id' => 'q-interrogation-reliability-scoring-model',
                    'prompt' => 'Which reliability scoring model should be used?',
                    'answer_type' => 'choice',
                    'options' => ['Pass-rate', 'Weighted severity'],
                    'depends_on' => ['interrogation.reliability.success_contract'],
                    'category' => 'reliability',
                    'decision_axis' => 'scoring_model',
                    'rationale' => 'Dependent on success contract.',
                    'priority' => 1,
                ],
                [
                    'canonical_key' => 'interrogation.reliability.success_contract',
                    'question_id' => 'q-interrogation-reliability-success-contract',
                    'prompt' => 'Which run-success contract should be canonical?',
                    'answer_type' => 'choice',
                    'options' => ['Strict', 'Assisted'],
                    'depends_on' => [],
                    'category' => 'reliability',
                    'decision_axis' => 'success_contract',
                    'rationale' => 'Prerequisite for scoring.',
                    'priority' => 2,
                ],
            ],
        ]);

        $job = new ExecuteInterrogationRoundJob((int) $session->id, 'Start interrogation.', true);
        $this->app->call([$job, 'handle']);

        $askedQuestionIds = InterrogationEvent::query()
            ->where('interrogation_session_id', $session->id)
            ->where('event_type', InterrogationEvent::TYPE_QUESTION)
            ->pluck('payload')
            ->map(static fn (array $payload): string => (string) ($payload['question_id'] ?? ''))
            ->all();

        $this->assertContains('q-interrogation-reliability-success-contract', $askedQuestionIds);
        $this->assertNotContains('q-interrogation-reliability-scoring-model', $askedQuestionIds);
    }

    public function test_dynamic_question_bank_rejects_near_duplicate_phrasing_via_semantic_dedupe(): void
    {
        Queue::fake();

        $session = $this->interrogatingSession([
            'dynamic_question_bank_mode' => true,
            'dynamic_question_bank' => [
                [
                    'canonical_key' => 'interrogation.reliability.success_contract',
                    'question_id' => 'q-interrogation-reliability-success-contract',
                    'prompt' => 'Which outcome policy defines what counts as a successful run?',
                    'answer_type' => 'choice',
                    'options' => ['Strict terminal success', 'Assisted success'],
                    'depends_on' => [],
                    'category' => 'reliability',
                    'decision_axis' => 'success_contract',
                    'rationale' => 'First success policy axis.',
                    'priority' => 1,
                ],
                [
                    'canonical_key' => 'interrogation.reliability.outcome_policy_reporting',
                    'question_id' => 'q-interrogation-reliability-outcome-policy-reporting',
                    'prompt' => 'Which outcome policy defines what counts as successful run?',
                    'answer_type' => 'choice',
                    'options' => ['Strict terminal success', 'Assisted success'],
                    'depends_on' => [],
                    'category' => 'reliability',
                    'decision_axis' => 'outcome_policy_reporting',
                    'rationale' => 'Near-duplicate phrasing that should be suppressed.',
                    'priority' => 2,
                ],
            ],
        ]);

        $firstRound = new ExecuteInterrogationRoundJob((int) $session->id, 'Start interrogation.', true);
        $this->app->call([$firstRound, 'handle']);

        $answerRound = new ExecuteInterrogationRoundJob(
            (int) $session->id,
            'Strict terminal success.',
            false,
            [
                'question_id' => 'q-interrogation-reliability-success-contract',
                'answer_type' => 'choice',
                'selected_option' => 'Strict terminal success',
            ],
        );
        $this->app->call([$answerRound, 'handle']);

        $this->assertFalse(
            InterrogationEvent::query()
                ->where('interrogation_session_id', $session->id)
                ->where('event_type', InterrogationEvent::TYPE_QUESTION)
                ->where('payload->question_id', 'q-interrogation-reliability-outcome-policy-reporting')
                ->exists()
        );

        $session->refresh();
        $metadata = is_array($session->metadata_json) ? $session->metadata_json : [];
        $suppressed = is_array($metadata['suppressed_canonical_keys'] ?? null) ? $metadata['suppressed_canonical_keys'] : [];

        $this->assertContains('interrogation.reliability.outcome_policy_reporting', $suppressed);
    }

    public function test_dynamic_question_bank_ends_when_exhausted(): void
    {
        Queue::fake();

        $session = $this->interrogatingSession([
            'dynamic_question_bank_mode' => true,
            'dynamic_question_bank' => [
                [
                    'canonical_key' => 'interrogation.scope.pilot_wedge',
                    'question_id' => 'q-interrogation-scope-pilot-wedge',
                    'prompt' => 'Which pilot wedge should be prioritized?',
                    'answer_type' => 'choice',
                    'options' => ['Engineering workflows', 'Internal automation'],
                    'depends_on' => [],
                    'category' => 'scope',
                    'decision_axis' => 'pilot_wedge',
                    'rationale' => 'Top-level prioritization.',
                    'priority' => 1,
                ],
            ],
        ]);

        $firstRound = new ExecuteInterrogationRoundJob((int) $session->id, 'Start interrogation.', true);
        $this->app->call([$firstRound, 'handle']);

        $answerRound = new ExecuteInterrogationRoundJob(
            (int) $session->id,
            'Engineering workflows.',
            false,
            [
                'question_id' => 'q-interrogation-scope-pilot-wedge',
                'answer_type' => 'choice',
                'selected_option' => 'Engineering workflows',
            ],
        );
        $this->app->call([$answerRound, 'handle']);

        $session->refresh();

        $this->assertSame(InterrogationSession::PHASE_SUMMARY, (int) $session->phase);
        $this->assertSame(InterrogationSession::STATUS_SUMMARIZING, (string) $session->status);

        $this->assertTrue(
            InterrogationEvent::query()
                ->where('interrogation_session_id', $session->id)
                ->where('event_type', InterrogationEvent::TYPE_SYSTEM)
                ->where('payload->notice', 'interrogation_complete')
                ->exists()
        );

        Queue::assertPushed(ExecuteInterrogationSummaryJob::class);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function interrogatingSession(array $metadata = []): InterrogationSession
    {
        $user = User::factory()->create();

        return InterrogationSession::query()->create([
            'user_id' => $user->id,
            'name' => 'Round job test session',
            'runner_type' => 'codex',
            'project_directory' => base_path(),
            'interrogation_type' => InterrogationSession::TYPE_FEATURE,
            'status' => InterrogationSession::STATUS_INTERROGATING,
            'phase' => InterrogationSession::PHASE_INTERROGATION,
            'metadata_json' => $metadata,
        ]);
    }
}
