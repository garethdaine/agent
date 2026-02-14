<?php

namespace App\Support\Interrogation\Contracts;

use App\Models\InterrogationSession;

interface InterrogationRunnerAdapter
{
    /**
     * @return array<int, string>
     */
    public function buildDiscoveryCommand(InterrogationSession $session, string $discoveryPrompt, string $systemPrompt): array;

    /**
     * @return array<int, string>
     */
    public function buildQuestionCommand(InterrogationSession $session, string $userMessage, string $systemPrompt): array;

    /**
     * @return array<int, string>
     */
    public function buildSummaryCommand(InterrogationSession $session, string $summaryPrompt, string $systemPrompt): array;

    /**
     * @return array<int, string>
     */
    public function buildPlanCommand(InterrogationSession $session, string $planningPrompt, string $systemPrompt): array;

    /**
     * @return array<int, string>
     */
    public function buildReconstructCommand(InterrogationSession $session, string $conversationHistory, string $systemPrompt): array;

    /**
     * @return array<string, mixed>|null
     */
    public function parseStreamEvent(string $line): ?array;

    /**
     * @return array<string, mixed>|null
     */
    public function parseQuestionResponse(string $output): ?array;

    /**
     * @return array<string, mixed>|null
     */
    public function parseSummaryResponse(string $output): ?array;

    /**
     * @return array<string, mixed>|null
     */
    public function parsePlanResponse(string $output): ?array;

    /**
     * @return array<string, string>
     */
    public function buildEnvironment(InterrogationSession $session): array;
}
