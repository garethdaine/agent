<?php

namespace Tests\Unit;

use App\Models\InterrogationEvent;
use App\Models\InterrogationSession;
use App\Models\User;
use App\Support\Interrogation\ConversationReconstructor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversationReconstructorTest extends TestCase
{
    use RefreshDatabase;

    public function test_reconstruct_includes_question_ids_and_choice_answers(): void
    {
        $user = User::factory()->create();
        $session = InterrogationSession::query()->create([
            'user_id' => $user->id,
            'name' => 'Transcript reconstruction',
            'runner_type' => 'claude',
            'project_directory' => base_path(),
            'interrogation_type' => InterrogationSession::TYPE_FEATURE,
            'status' => InterrogationSession::STATUS_INTERROGATING,
            'phase' => InterrogationSession::PHASE_INTERROGATION,
        ]);

        InterrogationEvent::query()->create([
            'interrogation_session_id' => $session->id,
            'event_type' => InterrogationEvent::TYPE_QUESTION,
            'sequence' => 1,
            'payload' => [
                'question_id' => 'oq2-neo4j-edition',
                'question_text' => 'Which Neo4j edition should we use?',
            ],
            'event_ts' => now('UTC'),
        ]);

        InterrogationEvent::query()->create([
            'interrogation_session_id' => $session->id,
            'event_type' => InterrogationEvent::TYPE_ANSWER,
            'sequence' => 2,
            'payload' => [
                'question_id' => 'oq2-neo4j-edition',
                'answer_type' => 'choice',
                'selected_option' => 'Community',
            ],
            'event_ts' => now('UTC'),
        ]);

        InterrogationEvent::query()->create([
            'interrogation_session_id' => $session->id,
            'event_type' => InterrogationEvent::TYPE_QUESTION,
            'sequence' => 3,
            'payload' => [
                'question_id' => 'q-multi-1',
                'question_text' => 'Which deployment environments matter?',
            ],
            'event_ts' => now('UTC'),
        ]);

        InterrogationEvent::query()->create([
            'interrogation_session_id' => $session->id,
            'event_type' => InterrogationEvent::TYPE_ANSWER,
            'sequence' => 4,
            'payload' => [
                'question_id' => 'q-multi-1',
                'answer_type' => 'choice',
                'selected_options' => ['Staging', 'Production'],
            ],
            'event_ts' => now('UTC'),
        ]);

        $reconstructor = app(ConversationReconstructor::class);
        $transcript = $reconstructor->reconstruct($session);

        $this->assertStringContainsString('Assistant Question [oq2-neo4j-edition]: Which Neo4j edition should we use?', $transcript);
        $this->assertStringContainsString('User Answer [oq2-neo4j-edition] (choice): Community', $transcript);
        $this->assertStringContainsString('User Answer [q-multi-1] (choice): Staging; Production', $transcript);
    }
}
