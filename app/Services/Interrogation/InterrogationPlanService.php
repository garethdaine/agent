<?php

declare(strict_types=1);

namespace App\Services\Interrogation;

use App\Jobs\ExecuteInterrogationPlanJob;
use App\Models\InterrogationBuildTask;
use App\Models\InterrogationEvent;
use App\Models\InterrogationSession;
use App\Support\Interrogation\InterrogationEventWriter;
use App\Support\Interrogation\PlanPayloadGuard;
use App\Support\Interrogation\PlanPayloadNormalizer;
use App\Support\Interrogation\SessionStateTransitionService;
use App\Support\Interrogation\SummaryPayloadNormalizer;
use Carbon\CarbonImmutable;

class InterrogationPlanService
{
    public function generatePlan(InterrogationSession $session): void
    {
        $this->markPlanGenerationState($session, 'queued');
        ExecuteInterrogationPlanJob::dispatch((int) $session->id);
    }

    public function regeneratePlan(
        InterrogationSession $session,
        int $userId,
    ): void {
        $prompt = 'Regenerate the implementation plan from the confirmed summary and metadata context only. '
            .'Ignore any prior plan drafts and rewrite the plan from scratch.';

        $now = CarbonImmutable::now('UTC')->toIso8601String();
        $metadata = is_array($session->metadata_json) ? $session->metadata_json : [];
        $metadata['plan'] = array_merge(
            is_array($metadata['plan'] ?? null) ? $metadata['plan'] : [],
            [
                'revision_status' => 'queued',
                'revision_requested_at' => $now,
                'revision_updated_at' => $now,
                'revision_error' => null,
                'regenerated_at' => $now,
                'regenerated_by_user_id' => $userId,
            ],
        );
        $session->status = InterrogationSession::STATUS_PLANNING;
        $session->phase = InterrogationSession::PHASE_PLANNING;
        $session->finished_at = null;
        $session->error_code = null;
        $session->error_summary = null;
        $session->metadata_json = $metadata;
        $session->save();

        (new InterrogationEventWriter($session))->appendSystem([
            'notice' => 'plan_regeneration_queued',
            'message' => 'Plan regeneration queued.',
            'at' => $now,
        ]);

        ExecuteInterrogationPlanJob::dispatch((int) $session->id, $prompt);
    }

    public function requestRevision(
        InterrogationSession $session,
        array $validated,
    ): void {
        $selectedSections = array_values(array_filter(array_map(
            static fn ($value): string => trim((string) $value),
            (array) ($validated['sections'] ?? [])
        ), static fn (string $value): bool => $value !== ''));
        $sectionValue = trim((string) ($validated['section'] ?? ''));
        $sectionScope = $selectedSections !== []
            ? implode(', ', $selectedSections)
            : ($sectionValue !== '' ? $sectionValue : 'entire_plan');
        $notes = (string) ($validated['notes'] ?? 'none');

        $prompt = 'Revise the implementation plan.'
            ."\nAction: ".(string) $validated['action']
            ."\nSection: ".$sectionScope
            ."\nSections: ".($selectedSections !== [] ? json_encode($selectedSections, JSON_UNESCAPED_SLASHES) : '[]')
            ."\nNotes (Markdown):\n".$notes;

        $now = CarbonImmutable::now('UTC')->toIso8601String();
        $metadata = is_array($session->metadata_json) ? $session->metadata_json : [];
        $metadata['plan'] = array_merge(
            is_array($metadata['plan'] ?? null) ? $metadata['plan'] : [],
            [
                'revision_status' => 'queued',
                'revision_requested_at' => $now,
                'revision_updated_at' => $now,
                'revision_error' => null,
            ],
        );
        $session->status = InterrogationSession::STATUS_PLANNING;
        $session->phase = InterrogationSession::PHASE_PLANNING;
        $session->finished_at = null;
        $session->error_code = null;
        $session->error_summary = null;
        $session->metadata_json = $metadata;
        $session->save();

        (new InterrogationEventWriter($session))->appendSystem([
            'notice' => 'plan_revision_queued',
            'message' => 'Plan revision queued.',
            'at' => $now,
        ]);

        ExecuteInterrogationPlanJob::dispatch((int) $session->id, $prompt);
    }

    /**
     * @return array{approved:bool,approved_at:string,phase:int,status:string}|null Returns null if already approved or on conflict
     */
    public function approvePlan(
        InterrogationSession $session,
        SessionStateTransitionService $transitions,
        int $userId,
    ): ?array {
        $approvedAt = CarbonImmutable::now('UTC');
        $session->approved_at = $approvedAt;

        $metadata = is_array($session->metadata_json) ? $session->metadata_json : [];
        $metadata['plan'] = array_merge(
            is_array($metadata['plan'] ?? null) ? $metadata['plan'] : [],
            [
                'approved_at' => $approvedAt->toIso8601String(),
                'approved_by_user_id' => $userId,
            ],
        );
        $session->metadata_json = $metadata;
        $session->save();

        $moved = $transitions->transitionPhase(
            (int) $session->id,
            InterrogationSession::PHASE_PLANNING,
            InterrogationSession::PHASE_BUILD_RULES,
            InterrogationSession::STATUS_BUILD_RULES,
            [
                InterrogationSession::STATUS_PLANNING,
                InterrogationSession::STATUS_COMPLETED,
                InterrogationSession::STATUS_PAUSED,
            ],
        );

        if (! $moved) {
            return null;
        }

        $session->refresh();
        $writer = new InterrogationEventWriter($session);
        $writer->appendPhaseTransition(
            InterrogationSession::PHASE_PLANNING,
            InterrogationSession::PHASE_BUILD_RULES,
            (string) $session->status,
            ['at' => CarbonImmutable::now('UTC')->toIso8601String(), 'approved_by_user_id' => $userId],
        );

        return [
            'approved' => true,
            'approved_at' => $approvedAt->toIso8601String(),
            'phase' => (int) $session->phase,
            'status' => (string) $session->status,
        ];
    }

    public function confirmSummary(
        InterrogationSession $session,
        SessionStateTransitionService $transitions,
    ): bool {
        $moved = $transitions->transitionPhase(
            (int) $session->id,
            InterrogationSession::PHASE_SUMMARY,
            InterrogationSession::PHASE_PLANNING,
            InterrogationSession::STATUS_PLANNING,
            [InterrogationSession::STATUS_SUMMARIZING],
        );

        if (! $moved) {
            return false;
        }

        $session->refresh();
        $writer = new InterrogationEventWriter($session);
        $writer->appendPhaseTransition(
            InterrogationSession::PHASE_SUMMARY,
            InterrogationSession::PHASE_PLANNING,
            (string) $session->status,
            ['at' => CarbonImmutable::now('UTC')->toIso8601String()],
        );

        $this->markPlanGenerationState($session, 'queued');
        ExecuteInterrogationPlanJob::dispatch((int) $session->id);

        return true;
    }

    public function markPlanGenerationState(
        InterrogationSession $session,
        string $status,
        ?string $error = null,
    ): void {
        $now = CarbonImmutable::now('UTC')->toIso8601String();
        $metadata = is_array($session->metadata_json) ? $session->metadata_json : [];
        $plan = array_merge(
            is_array($metadata['plan'] ?? null) ? $metadata['plan'] : [],
            [
                'generation_status' => $status,
                'generation_updated_at' => $now,
            ],
        );

        if ($status === 'queued') {
            $plan['generation_queued_at'] = $now;
            $plan['generation_error'] = null;
        }

        if ($status === 'running') {
            $plan['generation_started_at'] = $now;
            $plan['generation_error'] = null;
        }

        if ($status === 'idle') {
            $plan['generation_completed_at'] = $now;
            $plan['generation_error'] = null;
        }

        if ($status === 'failed') {
            $plan['generation_error'] = $error ?: 'Plan generation failed.';
        }

        $metadata['plan'] = $plan;
        $session->metadata_json = $metadata;
        $session->save();
    }

    public function hasMeaningfulPlan(InterrogationSession $session): bool
    {
        if (! is_array($session->plan_json)) {
            return false;
        }

        $guard = new PlanPayloadGuard;
        $validation = $guard->validate($session->plan_json);

        return (bool) $validation['valid'];
    }

    /**
     * @return array<string, mixed>
     */
    public function normalizedPlanJson(InterrogationSession $session, bool $persist): array
    {
        if (! is_array($session->plan_json) || $session->plan_json === []) {
            return [];
        }

        $normalizer = new PlanPayloadNormalizer;
        $normalized = $normalizer->normalize($session->plan_json);

        if ($persist && $normalized !== $session->plan_json) {
            $session->plan_json = $normalized;
            $session->save();
        }

        return $normalized;
    }

    /**
     * @return array<string, mixed>
     */
    public function normalizedSummaryJson(InterrogationSession $session, bool $persist): array
    {
        if (! is_array($session->summary_json) || $session->summary_json === []) {
            return [];
        }

        $normalizer = new SummaryPayloadNormalizer;
        $normalized = $normalizer->normalize($session->summary_json);
        $normalized = $this->reconcileOpenQuestionsWithAnsweredEvents($session, $normalized, $persist);

        if ($persist && $normalized !== $session->summary_json) {
            $session->summary_json = $normalized;
            $session->save();
        }

        return $normalized;
    }

    public function invalidateDerivedArtifactsForReinterrogation(InterrogationSession $session): void
    {
        $metadata = is_array($session->metadata_json) ? $session->metadata_json : [];

        $metadata['summary_ready'] = false;
        $metadata['summary_generated_at'] = null;

        if (is_array($metadata['exports'] ?? null)) {
            unset($metadata['exports']['summary'], $metadata['exports']['plan']);
            if ($metadata['exports'] === []) {
                unset($metadata['exports']);
            }
        }

        $metadata['build'] = [
            'status' => 'idle',
            'task_count' => 0,
            'active_task_id' => null,
            'active_run_id' => null,
            'tasks_approved_at' => null,
            'tasks_approved_by_user_id' => null,
            'task_provider_sync' => [
                'status' => 'idle',
                'driver' => null,
                'project_mode' => 'create_new',
                'project_id' => null,
                'project_name' => null,
                'project_url' => null,
                'synced_task_count' => 0,
                'error' => null,
                'updated_at' => CarbonImmutable::now('UTC')->toIso8601String(),
            ],
            'updated_at' => CarbonImmutable::now('UTC')->toIso8601String(),
        ];

        $session->summary_json = [];
        $session->plan_json = [];
        $session->approved_at = null;
        $session->metadata_json = $metadata;
        $session->save();

        InterrogationBuildTask::query()
            ->where('interrogation_session_id', $session->id)
            ->delete();
    }

    /**
     * @param  array<string, mixed>  $summary
     * @return array<string, mixed>
     */
    private function reconcileOpenQuestionsWithAnsweredEvents(
        InterrogationSession $session,
        array $summary,
        bool $eager,
    ): array {
        $openQuestions = is_array($summary['open_questions'] ?? null) ? array_values($summary['open_questions']) : [];
        if ($openQuestions === []) {
            return $summary;
        }

        if (! $eager) {
            return $summary;
        }

        $events = $session->events()->orderBy('sequence')->get();
        if ($events->isEmpty()) {
            return $summary;
        }

        $questionsById = [];
        /** @var InterrogationEvent $event */
        foreach ($events as $event) {
            if ($event->event_type !== InterrogationEvent::TYPE_QUESTION) {
                continue;
            }

            $payload = (array) $event->payload;
            $questionId = trim((string) ($payload['question_id'] ?? ''));
            if ($questionId === '') {
                continue;
            }

            $questionText = trim((string) ($payload['question_text'] ?? ''));
            if ($questionText === '') {
                continue;
            }

            $questionsById[$questionId] = $questionText;
        }

        if ($questionsById === []) {
            return $summary;
        }

        $resolvedFingerprints = [];
        $resolvedOpenQuestionIndexes = [];
        $resolvedOpenQuestionOrdinals = [];
        /** @var InterrogationEvent $event */
        foreach ($events as $event) {
            if ($event->event_type !== InterrogationEvent::TYPE_ANSWER) {
                continue;
            }

            $payload = (array) $event->payload;
            $answerType = strtolower(trim((string) ($payload['answer_type'] ?? '')));
            if ($answerType === 'skip') {
                continue;
            }

            $questionId = trim((string) ($payload['question_id'] ?? ''));
            if ($questionId === '') {
                continue;
            }

            $questionText = (string) ($questionsById[$questionId] ?? '');
            $fingerprint = $this->questionFingerprint($questionText);
            if ($fingerprint !== '') {
                $resolvedFingerprints[] = $fingerprint;
            }

            $ordinal = $this->extractOpenQuestionOrdinal($questionText);
            if ($ordinal !== null) {
                $resolvedOpenQuestionIndexes[] = $ordinal - 1;
            }

            $openQuestionOrdinal = $this->extractOpenQuestionMarkerOrdinal($questionText)
                ?? $this->extractOpenQuestionMarkerOrdinalFromQuestionId($questionId);
            if ($openQuestionOrdinal !== null) {
                $resolvedOpenQuestionOrdinals[] = $openQuestionOrdinal;
            }
        }

        if ($resolvedFingerprints === [] && $resolvedOpenQuestionIndexes === [] && $resolvedOpenQuestionOrdinals === []) {
            return $summary;
        }

        $resolvedFingerprints = array_values(array_unique($resolvedFingerprints));
        $resolvedOpenQuestionIndexes = array_values(array_unique(array_filter(
            $resolvedOpenQuestionIndexes,
            static fn (int $index): bool => $index >= 0
        )));
        $resolvedOpenQuestionIndexLookup = array_fill_keys($resolvedOpenQuestionIndexes, true);
        $resolvedOpenQuestionOrdinals = array_values(array_unique(array_filter(
            $resolvedOpenQuestionOrdinals,
            static fn (int $ordinal): bool => $ordinal > 0
        )));
        $resolvedOpenQuestionOrdinalLookup = array_fill_keys($resolvedOpenQuestionOrdinals, true);

        $remaining = [];
        foreach ($openQuestions as $openIndex => $openQuestion) {
            if (! is_string($openQuestion) || trim($openQuestion) === '') {
                continue;
            }

            if (isset($resolvedOpenQuestionIndexLookup[$openIndex])) {
                continue;
            }

            $openQuestionOrdinal = $this->extractOpenQuestionMarkerOrdinal($openQuestion);
            if ($openQuestionOrdinal !== null && isset($resolvedOpenQuestionOrdinalLookup[$openQuestionOrdinal])) {
                continue;
            }

            $openFingerprint = $this->questionFingerprint($openQuestion);
            if ($openFingerprint === '') {
                $remaining[] = $openQuestion;

                continue;
            }

            $isResolved = false;
            foreach ($resolvedFingerprints as $resolved) {
                if ($resolved === $openFingerprint || str_contains($resolved, $openFingerprint) || str_contains($openFingerprint, $resolved)) {
                    $isResolved = true;
                    break;
                }
            }

            if (! $isResolved) {
                $remaining[] = $openQuestion;
            }
        }

        $summary['open_questions'] = array_values(array_unique($remaining));

        return $summary;
    }

    private function questionFingerprint(string $value): string
    {
        $normalized = strtolower(trim($value));
        if ($normalized === '') {
            return '';
        }

        $normalized = preg_replace('/\r\n|\r/', "\n", $normalized) ?? $normalized;
        $normalized = preg_replace('/^\*+\s*/m', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/^open\s+question\s+\d+\s+of\s+\d+\s*:\s*/i', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/^open\s+question\s+\d+\s*:\s*/i', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/^oq\s*[-_ ]?\d+\s*:\s*/i', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/[^a-z0-9 ]/', '', $normalized) ?? $normalized;
        $normalized = trim($normalized);

        if ($normalized === '') {
            return '';
        }

        $parts = explode('?', $normalized, 2);

        return trim($parts[0]);
    }

    private function extractOpenQuestionOrdinal(string $value): ?int
    {
        $normalized = strtolower(trim($value));
        if ($normalized === '') {
            return null;
        }

        if (preg_match('/open\s+question\s+(\d+)\b/i', $normalized, $matches) !== 1) {
            return null;
        }

        $ordinal = (int) $matches[1];

        return $ordinal > 0 ? $ordinal : null;
    }

    private function extractOpenQuestionMarkerOrdinal(string $value): ?int
    {
        $normalized = trim($value);
        if ($normalized === '') {
            return null;
        }

        if (preg_match('/^OQ\s*[-_ ]?(\d+)\s*:/i', $normalized, $matches) !== 1) {
            return null;
        }

        $ordinal = (int) $matches[1];

        return $ordinal > 0 ? $ordinal : null;
    }

    private function extractOpenQuestionMarkerOrdinalFromQuestionId(string $questionId): ?int
    {
        $normalized = trim($questionId);
        if ($normalized === '') {
            return null;
        }

        if (preg_match('/(?:^|[^a-z0-9])oq[-_ ]?(\d+)(?:[^a-z0-9]|$)/i', $normalized, $matches) !== 1) {
            return null;
        }

        $ordinal = (int) $matches[1];

        return $ordinal > 0 ? $ordinal : null;
    }
}
