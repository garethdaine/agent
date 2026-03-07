<?php

namespace App\Jobs\Runtime;

use App\DTOs\Messenger\OutboundPayload;
use App\Enums\Messenger\ApprovalMode;
use App\Jobs\Messenger\CompactionJob;
use App\Models\ChatMessage;
use App\Models\ConnectorAccount;
use App\Models\Runtime\RuntimeSession;
use App\Services\Messenger\CompactionService;
use App\Jobs\Compliance\LessonExtractionJob;
use App\Services\Runtime\MessengerRuntimeOrchestrator;
use App\Support\Agent\FeatureFlagManager;
use App\Support\Messenger\ConnectorManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProcessRuntimeTurnJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout;

    public function __construct(
        public string $runtimeSessionId,
        public string $userMessage,
        public string|int|null $chatSessionId = null,
        public string|int|null $connectorAccountId = null,
        public ?string $placeholderMessageId = null,
    ) {
        $this->onQueue(config('runtime.queue', 'agent'));
        $this->timeout = (int) config('runtime.cli.timeout_seconds', 1800);
    }

    public function handle(
        MessengerRuntimeOrchestrator $orchestrator,
        ConnectorManager $connectorManager,
        CompactionService $compactionService,
    ): void {
        // Acquire a per-session lock so turns are processed sequentially.
        // Without this, concurrent workers can start Turn N+1 before Turn N
        // stores its runner_session_id, breaking --resume context continuity.
        $lockKey = 'runtime:turn_lock:'.$this->runtimeSessionId;
        $lockTimeout = $this->timeout + 30; // slightly longer than the turn timeout

        $lock = Cache::lock($lockKey, $lockTimeout);

        Log::info('ProcessRuntimeTurnJob: Waiting for session lock', [
            'runtime_session_id' => $this->runtimeSessionId,
        ]);

        // Block up to lockTimeout seconds waiting for any prior turn to finish.
        if (! $lock->block($lockTimeout)) {
            Log::warning('ProcessRuntimeTurnJob: Could not acquire session lock', [
                'runtime_session_id' => $this->runtimeSessionId,
            ]);
            $this->sendErrorToChat('Still processing a previous request. Please wait and try again.', $connectorManager);

            return;
        }

        try {
            $this->executeWithinLock($orchestrator, $connectorManager, $compactionService);
        } finally {
            $lock->release();
        }
    }

    private function executeWithinLock(
        MessengerRuntimeOrchestrator $orchestrator,
        ConnectorManager $connectorManager,
        CompactionService $compactionService,
    ): void {
        $session = RuntimeSession::query()
            ->with(['user'])
            ->whereKey($this->runtimeSessionId)
            ->first();

        if ($session === null) {
            Log::warning('ProcessRuntimeTurnJob: Runtime session not found', [
                'runtime_session_id' => $this->runtimeSessionId,
            ]);

            return;
        }

        $account = $this->connectorAccountId !== null
            ? ConnectorAccount::find($this->connectorAccountId)
            : null;
        $runnerTypeOverride = null;
        if ($account !== null && isset($account->config['runner_type']) && (string) $account->config['runner_type'] !== '') {
            $runnerTypeOverride = (string) $account->config['runner_type'];
        }

        $systemPrompt = $account !== null ? $this->buildSystemPromptFromSoul($account) : null;
        $approvalMode = $account?->getApprovalMode() ?? ApprovalMode::Autonomous;

        Log::info('ProcessRuntimeTurnJob: Starting turn', [
            'runtime_session_id' => $this->runtimeSessionId,
            'message_length' => strlen($this->userMessage),
            'runner_type' => $runnerTypeOverride,
            'has_system_prompt' => $systemPrompt !== null,
            'approval_mode' => $approvalMode->value,
        ]);

        $progressCallback = $this->buildProgressCallback($connectorManager);

        try {
            $result = $orchestrator->executeTurn($session, $this->userMessage, $runnerTypeOverride, $systemPrompt, $approvalMode, $progressCallback);

            Log::info('ProcessRuntimeTurnJob: Turn finished', [
                'runtime_session_id' => $this->runtimeSessionId,
                'status' => $result['status'] ?? 'unknown',
                'has_runner_session_id' => isset($result['runner_session_id']),
            ]);
        } catch (\Throwable $e) {
            Log::error('ProcessRuntimeTurnJob: Turn execution failed', [
                'runtime_session_id' => $this->runtimeSessionId,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->sendErrorToChat('Something went wrong processing your request. Please try again.', $connectorManager);

            throw $e;
        }

        if (($result['status'] ?? '') === 'yielded') {
            $this->handleYielded($result, $connectorManager);

            return;
        }

        if ($this->chatSessionId === null || $account === null) {
            return;
        }
        $adapter = $account !== null ? $connectorManager->resolve($account->provider) : null;

        if ($adapter === null) {
            return;
        }

        $chatSession = $account->sessions()->whereKey($this->chatSessionId)->first();
        if ($chatSession === null) {
            return;
        }

        if ($result['status'] === 'completed' && isset($result['text'])) {
            try {
                $adapter->sendMessage($chatSession, new OutboundPayload(
                    content: $result['text'],
                    channelId: $chatSession->channel_id,
                    threadId: $chatSession->thread_id,
                ));
                $this->persistOutboundMessage($chatSession->id, $account->id, $result['text']);

                if ($this->placeholderMessageId !== null && $adapter->supportsMessageEditing()) {
                    $adapter->editMessage($chatSession, $this->placeholderMessageId, '✅ Done');
                }
            } catch (\Throwable $e) {
                Log::error('ProcessRuntimeTurnJob: Failed to send response', [
                    'session_id' => $this->chatSessionId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($result['status'] === 'pending_approval') {
            try {
                $adapter->sendMessage($chatSession, new OutboundPayload(
                    content: 'A tool call is waiting for your approval. Use `/approve <id>` or `/deny <id>`.',
                    channelId: $chatSession->channel_id,
                    threadId: $chatSession->thread_id,
                ));
            } catch (\Throwable $e) {
                Log::warning('ProcessRuntimeTurnJob: Failed to send approval prompt', ['error' => $e->getMessage()]);
            }
        }

        if ($result['status'] === 'failed') {
            $errorText = $result['error'] ?? 'The turn failed. Please try again.';
            $partialText = isset($result['text']) && $result['text'] !== '' ? $result['text'] : null;

            $content = $partialText !== null
                ? "Error: {$errorText}\n\n**Partial progress before failure:**\n{$partialText}"
                : "Error: {$errorText}";

            try {
                $adapter->sendMessage($chatSession, new OutboundPayload(
                    content: $content,
                    channelId: $chatSession->channel_id,
                    threadId: $chatSession->thread_id,
                ));
            } catch (\Throwable $e) {
                Log::error('ProcessRuntimeTurnJob: Failed to send error', ['error' => $e->getMessage()]);
            }

            $this->dispatchLessonIfEnabled($session, $result);
        }

        if ($compactionService->isCompactionNeeded($chatSession)) {
            CompactionJob::dispatch($chatSession->id, null);
        }
    }

    private function buildProgressCallback(ConnectorManager $connectorManager): ?\Closure
    {
        if ($this->placeholderMessageId === null || $this->chatSessionId === null || $this->connectorAccountId === null) {
            return null;
        }

        $account = ConnectorAccount::find($this->connectorAccountId);
        if ($account === null) {
            return null;
        }

        $adapter = $connectorManager->resolve($account->provider);
        if ($adapter === null || ! $adapter->supportsMessageEditing()) {
            return null;
        }

        $chatSession = $account->sessions()->whereKey($this->chatSessionId)->first();
        if ($chatSession === null) {
            return null;
        }

        $placeholderMessageId = $this->placeholderMessageId;

        return function (array $state) use ($adapter, $chatSession, $placeholderMessageId) {
            $elapsed = $state['elapsed_seconds'] ?? 0;
            $minutes = intdiv($elapsed, 60);
            $seconds = $elapsed % 60;
            $timeStr = $minutes > 0 ? "{$minutes}m {$seconds}s" : "{$seconds}s";

            try {
                $adapter->editMessage($chatSession, $placeholderMessageId, "⏳ Working on it… ({$timeStr} elapsed)");
            } catch (\Throwable $e) {
                Log::debug('ProcessRuntimeTurnJob: progress update failed', ['error' => $e->getMessage()]);
            }
        };
    }

    private function sendErrorToChat(string $errorMessage, ConnectorManager $connectorManager): void
    {
        if ($this->chatSessionId === null || $this->connectorAccountId === null) {
            return;
        }

        $account = ConnectorAccount::find($this->connectorAccountId);
        if ($account === null) {
            return;
        }

        $adapter = $connectorManager->resolve($account->provider);
        if ($adapter === null) {
            return;
        }

        $chatSession = $account->sessions()->whereKey($this->chatSessionId)->first();
        if ($chatSession === null) {
            return;
        }

        $text = strlen($errorMessage) > 500 ? substr($errorMessage, 0, 497).'…' : $errorMessage;
        try {
            $adapter->sendMessage($chatSession, new OutboundPayload(
                content: 'Error: '.$text,
                channelId: $chatSession->channel_id,
                threadId: $chatSession->thread_id,
            ));
        } catch (\Throwable $e) {
            Log::warning('ProcessRuntimeTurnJob: Could not send error to chat', ['error' => $e->getMessage()]);
        }
    }

    private function persistOutboundMessage(string|int $chatSessionId, string|int $connectorAccountId, string $content): void
    {
        try {
            ChatMessage::create([
                'chat_session_id' => $chatSessionId,
                'connector_account_id' => $connectorAccountId,
                'direction' => ChatMessage::DIRECTION_OUTBOUND,
                'content' => $content,
                'idempotency_key' => hash('sha256', Str::uuid()->toString()),
                'provider_timestamp' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('ProcessRuntimeTurnJob: Failed to persist outbound ChatMessage', [
                'chat_session_id' => $chatSessionId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function buildSystemPromptFromSoul(ConnectorAccount $account): ?string
    {
        $soul = $account->getSoul();
        $parts = [];

        $name = $soul['name'] ?? null;
        if ($name !== null && $name !== '') {
            $parts[] = "Your name is {$name}. Always identify yourself as {$name}, never as Claude or any other name.";
        }

        $personality = $soul['personality'] ?? null;
        if ($personality !== null && $personality !== '') {
            $parts[] = "Personality: {$personality}";
        }

        $systemPrompt = $soul['system_prompt'] ?? null;
        if ($systemPrompt !== null && $systemPrompt !== '') {
            $parts[] = $systemPrompt;
        }

        $userContext = $soul['user_context'] ?? null;
        if ($userContext !== null && $userContext !== '') {
            $parts[] = "User context: {$userContext}";
        }

        $parts[] = "You have access to agent-browser for browser automation. Run it via Bash.\n\n".MessengerRuntimeOrchestrator::browserInstructions();

        return $parts !== [] ? implode("\n\n", $parts) : null;
    }

    private function handleYielded(array $result, ConnectorManager $connectorManager): void
    {
        $elapsed = $result['elapsed_seconds'] ?? 0;
        $totalTimeout = (int) config('runtime.cli.timeout_seconds', 1800);
        $remaining = max(60, $totalTimeout - $elapsed);

        $this->sendProgressToChat($connectorManager, $elapsed);

        ResumeRuntimeTurnJob::dispatch(
            runtimeSessionId: $this->runtimeSessionId,
            remainingTimeout: $remaining,
            chatSessionId: $this->chatSessionId,
            connectorAccountId: $this->connectorAccountId,
            placeholderMessageId: $this->placeholderMessageId,
        )->delay(now()->addSeconds(5));

        Log::info('ProcessRuntimeTurnJob: Turn yielded, resume scheduled', [
            'runtime_session_id' => $this->runtimeSessionId,
            'elapsed_seconds' => $elapsed,
            'remaining_timeout' => $remaining,
        ]);
    }

    private function sendProgressToChat(ConnectorManager $connectorManager, int $elapsed): void
    {
        if ($this->chatSessionId === null || $this->connectorAccountId === null) {
            return;
        }

        $account = ConnectorAccount::find($this->connectorAccountId);
        if ($account === null) {
            return;
        }

        $adapter = $connectorManager->resolve($account->provider);
        $chatSession = $account->sessions()->whereKey($this->chatSessionId)->first();
        if ($chatSession === null) {
            return;
        }

        $minutes = intdiv($elapsed, 60);
        $seconds = $elapsed % 60;
        $timeStr = $minutes > 0 ? "{$minutes}m {$seconds}s" : "{$seconds}s";

        try {
            if ($this->placeholderMessageId !== null && $adapter->supportsMessageEditing()) {
                $adapter->editMessage($chatSession, $this->placeholderMessageId, "⏳ Still working on this… ({$timeStr} elapsed)");
            } else {
                $adapter->sendMessage($chatSession, new OutboundPayload(
                    content: "Still working on this — I'll report back when done. ({$timeStr} elapsed)",
                    channelId: $chatSession->channel_id,
                    threadId: $chatSession->thread_id,
                ));
            }
        } catch (\Throwable $e) {
            Log::debug('ProcessRuntimeTurnJob: yield progress message failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function dispatchLessonIfEnabled(RuntimeSession $session, array $result): void
    {
        if (! app(FeatureFlagManager::class)->enabled(FeatureFlagManager::COMPLIANCE_LESSONS)) {
            return;
        }

        $errorText = $result['error'] ?? 'Unknown messenger turn failure';

        try {
            LessonExtractionJob::dispatch(
                lessonContent: sprintf('Messenger turn failed: %s', substr($errorText, 0, 300)),
                source: 'messenger_turn_failure',
                projectDirectory: $session->workspace_root ?? base_path(),
                context: [
                    'task_title' => 'Messenger Turn',
                    'task_category' => 'chat',
                    'runner_type' => 'messenger',
                    'session_id' => $session->id,
                ],
            );
        } catch (\Throwable $e) {
            Log::warning('ProcessRuntimeTurnJob: Failed to dispatch lesson extraction', [
                'session_id' => $session->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function tags(): array
    {
        return [
            'runtime',
            'turn',
            'session:'.$this->runtimeSessionId,
        ];
    }
}
