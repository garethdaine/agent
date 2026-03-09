<?php

declare(strict_types=1);

namespace App\Support\Agent;

use App\Events\Office\AgentActivityChanged;
use App\Events\RunEventsAvailable;
use App\Jobs\Memory\MemoryWorkingBufferJob;
use App\Models\AgentJobRun;
use App\Models\AgentRunEvent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class EventBroadcaster
{
    private EventPatternMatcher $patternMatcher;

    public function __construct(
        private AgentJobRun $run,
        ?EventPatternMatcher $patternMatcher = null,
    ) {
        $this->patternMatcher = $patternMatcher ?? new EventPatternMatcher;
    }

    public function broadcastEventsAvailable(int $sequence): void
    {
        $cacheKey = 'run_events_broadcast:'.$this->run->id;

        if (Cache::has($cacheKey)) {
            return;
        }

        Cache::put($cacheKey, true, 2);

        try {
            RunEventsAvailable::dispatch($this->run->id, $sequence);
        } catch (\Throwable) {
            // Never block the event write loop
        }

        $this->broadcastOutputSnippetToOffice($sequence);
    }

    public function broadcastEscalation(string $reason, string $summary): void
    {
        try {
            AgentActivityChanged::dispatch(
                (int) $this->run->user_id,
                'agent.escalation',
                [
                    'run_id' => $this->run->id,
                    'job_name' => $this->run->job->name ?? 'Unknown',
                    'reason' => $reason,
                    'summary' => $summary,
                ],
            );
        } catch (\Throwable) {
            // Best-effort
        }
    }

    public function dispatchMemoryBuffer(string $eventType, string $rawPayload): void
    {
        if (app(FeatureFlagManager::class)->enabled(FeatureFlagManager::MEMORY_ENABLED)) {
            try {
                MemoryWorkingBufferJob::dispatch(
                    $this->run->id,
                    $eventType,
                    $rawPayload
                )->onQueue('memory-working');
            } catch (\Throwable) {
                // Silent failure - never block the 250ms poll loop
            }
        }
    }

    private function broadcastOutputSnippetToOffice(int $sequence): void
    {
        $officeCacheKey = 'office_output_broadcast:'.$this->run->id;
        if (Cache::has($officeCacheKey)) {
            return;
        }

        Cache::put($officeCacheKey, true, 4);

        $snippet = $this->extractMeaningfulSnippet($sequence);
        if ($snippet === null) {
            return;
        }

        try {
            AgentActivityChanged::dispatch(
                (int) $this->run->user_id,
                'agent.output',
                [
                    'run_id' => $this->run->id,
                    'text' => $snippet,
                    'sequence' => $sequence,
                ],
            );
        } catch (\Throwable) {
            // Best-effort
        }
    }

    private function extractMeaningfulSnippet(int $upToSequence): ?string
    {
        $event = AgentRunEvent::query()
            ->where('agent_job_run_id', $this->run->id)
            ->where('event_type', 'stdout')
            ->where('sequence', '<=', $upToSequence)
            ->orderByDesc('sequence')
            ->first();

        if ($event === null) {
            return null;
        }

        $raw = $event->payload ?? '';

        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $raw = $this->patternMatcher->extractReadableText($decoded);
        }

        $raw = trim(preg_replace('/\s+/', ' ', $raw) ?? $raw);

        if (str_starts_with($raw, '{') || str_starts_with($raw, '[') || str_starts_with($raw, '"type"')) {
            return null;
        }

        if (strlen($raw) < 5 || $this->patternMatcher->isLikelyNonRuntimeSnippet($raw)) {
            return null;
        }

        return Str::limit($raw, 60);
    }
}
