<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Events\InterrogationPhaseChanged;
use App\Events\InterrogationSessionUpdated;
use Illuminate\Broadcasting\PrivateChannel;
use Tests\TestCase;

class InterrogationBroadcastTest extends TestCase
{
    public function test_interrogation_session_updated_event_contract(): void
    {
        $event = new InterrogationSessionUpdated(123, 'question', ['question_id' => 'q1'], 9);

        $channel = $event->broadcastOn();

        $this->assertInstanceOf(PrivateChannel::class, $channel);
        $this->assertSame('private-interrogation.123', $channel->name);
        $this->assertSame('session.updated', $event->broadcastAs());
        $this->assertSame(9, $event->broadcastWith()['sequence']);
    }

    public function test_interrogation_phase_changed_event_contract(): void
    {
        $event = new InterrogationPhaseChanged(44, 2, 3, 'summarizing');

        $channel = $event->broadcastOn();

        $this->assertInstanceOf(PrivateChannel::class, $channel);
        $this->assertSame('private-interrogation.44', $channel->name);
        $this->assertSame('phase.changed', $event->broadcastAs());
        $this->assertSame(3, $event->broadcastWith()['to_phase']);
    }
}
