<?php

declare(strict_types=1);

namespace App\Support\Interrogation;

use App\Models\InterrogationEvent;
use App\Models\InterrogationSession;

/**
 * Builds context packages for adversarial reviewer invocation.
 *
 * Assembles all required context including session metadata, brief,
 * Q&A transcript, discovery findings, and candidate artifacts for
 * summary and plan review.
 */
final class ReviewerContextBuilder
{
    public function __construct(
        private readonly ConversationReconstructor $conversationReconstructor = new ConversationReconstructor
    ) {}

    /**
     * Build context package for summary review.
     *
     * @param  array<string, mixed>  $summaryCandidate  The summary candidate to review
     * @return array<string, mixed> Context package with session_metadata, brief, qa_transcript, discovery_findings, candidate
     */
    public function buildForSummaryReview(
        InterrogationSession $session,
        array $summaryCandidate
    ): array {
        return [
            'session_metadata' => $this->buildSessionMetadata($session),
            'brief' => $this->extractBrief($session),
            'qa_transcript' => $this->buildQaTranscript($session),
            'discovery_findings' => $this->extractDiscoveryFindings($session),
            'candidate' => $summaryCandidate,
        ];
    }

    /**
     * Build context package for plan review.
     *
     * @param  array<string, mixed>  $planCandidate  The plan candidate to review
     * @param  array<string, mixed>  $lockedSummary  The approved summary that the plan must align with
     * @return array<string, mixed> Context package with session_metadata, brief, qa_transcript, discovery_findings, locked_summary, candidate
     */
    public function buildForPlanReview(
        InterrogationSession $session,
        array $planCandidate,
        array $lockedSummary
    ): array {
        return [
            'session_metadata' => $this->buildSessionMetadata($session),
            'brief' => $this->extractBrief($session),
            'qa_transcript' => $this->buildQaTranscript($session),
            'discovery_findings' => $this->extractDiscoveryFindings($session),
            'locked_summary' => $lockedSummary,
            'candidate' => $planCandidate,
        ];
    }

    /**
     * Build session metadata snapshot.
     *
     * @return array<string, mixed>
     */
    private function buildSessionMetadata(InterrogationSession $session): array
    {
        return [
            'id' => $session->id,
            'type' => $session->interrogation_type ?? null,
            'status' => $session->status ?? null,
            'project_directory' => $session->project_directory ?? null,
            'created_at' => $session->created_at?->toIso8601String(),
        ];
    }

    /**
     * Extract brief from session, checking multiple possible locations.
     *
     * Priority: feature_brief field > metadata_json.brief > metadata_json.feature_brief > empty string
     */
    private function extractBrief(InterrogationSession $session): string
    {
        // First check the dedicated feature_brief field
        $featureBrief = $session->feature_brief ?? null;
        if (is_string($featureBrief) && $featureBrief !== '') {
            return $featureBrief;
        }

        // Fall back to metadata_json
        $metadata = $session->metadata_json ?? [];
        if (! is_array($metadata)) { // @phpstan-ignore function.alreadyNarrowedType
            return '';
        }

        // Check brief key
        $brief = $metadata['brief'] ?? null;
        if (is_string($brief) && $brief !== '') {
            return $brief;
        }

        // Check feature_brief key in metadata
        $featureBrief = $metadata['feature_brief'] ?? null;
        if (is_string($featureBrief) && $featureBrief !== '') {
            return $featureBrief;
        }

        return '';
    }

    /**
     * Build Q&A transcript using ConversationReconstructor.
     *
     * Returns the reconstructed conversation as a formatted string.
     */
    private function buildQaTranscript(InterrogationSession $session): string
    {
        return $this->conversationReconstructor->reconstruct($session);
    }

    /**
     * Extract discovery findings from session events.
     *
     * Returns discovery activity and system events, limited to 50 most recent.
     *
     * @return array<int, array{type: string, payload: array<string, mixed>}>
     */
    private function extractDiscoveryFindings(InterrogationSession $session): array
    {
        $events = $session->events()
            ->whereIn('event_type', [
                InterrogationEvent::TYPE_DISCOVERY_ACTIVITY,
                InterrogationEvent::TYPE_SYSTEM,
            ])
            ->orderBy('sequence')
            ->limit(50)
            ->get();

        return $events->map(fn (InterrogationEvent $event): array => [
            'type' => $event->event_type,
            'payload' => $event->payload,
        ])->toArray();
    }
}
