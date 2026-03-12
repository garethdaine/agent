<?php

declare(strict_types=1);

namespace App\Jobs\Runtime;

use App\DTOs\Messenger\OutboundPayload;
use App\Enums\Messenger\ApprovalMode;
use App\Jobs\Compliance\LessonExtractionJob;
use App\Jobs\Messenger\CompactionJob;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\ConnectorAccount;
use App\Models\CredentialVault;
use App\Models\Runtime\RuntimeSession;
use App\Services\Messenger\CompactionService;
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

        $systemPrompt = $account !== null ? $this->buildSystemPromptFromSoul($account, $session) : null;
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
                'status' => $result['status'],
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

        if ($this->chatSessionId === null || $account === null) {
            return;
        }
        $adapter = $connectorManager->resolve($account->provider);

        /** @var ChatSession|null $chatSession */
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

        if ($result['status'] === 'yielded') { // @phpstan-ignore identical.alwaysFalse
            $this->handleYielded($result, $connectorManager);
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
        if (! $adapter->supportsMessageEditing()) {
            return null;
        }

        /** @var ChatSession|null $chatSession */
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

        /** @var ChatSession|null $chatSession */
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

    private function buildSystemPromptFromSoul(ConnectorAccount $account, RuntimeSession $session): string
    {
        // Resolve soul with memory core block fallbacks (graceful when memory is unavailable)
        try {
            $soulResolver = app(\App\Support\Memory\SoulResolver::class);
            $soul = $soulResolver->resolve($account->getSoul(), $session->user_id);
        } catch (\Throwable) {
            $soul = $account->getSoul();
        }

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

        // Inject session-specific browser context
        $browserContext = $this->buildBrowserSessionContext($session);
        if ($browserContext !== null) {
            $parts[] = $browserContext;
        }

        // Inject persistent credential vault instructions
        $vaultSection = $this->buildVaultPromptSection($account);
        if ($vaultSection !== null) {
            $parts[] = $vaultSection;
        }

        // Inject memory tool awareness
        $memorySection = $this->buildMemoryPromptSection();
        if ($memorySection !== null) {
            $parts[] = $memorySection;
        }

        return implode("\n\n", $parts);
    }

    /**
     * Build memory tool instructions for the system prompt.
     *
     * Only included when the memory feature flag is enabled, giving
     * agents awareness of the memory tool and its operations.
     */
    private function buildMemoryPromptSection(): ?string
    {
        if (! app(FeatureFlagManager::class)->enabled(FeatureFlagManager::MEMORY_ENABLED)) {
            return null;
        }

        return "## Memory\n\n"
            ."You have a \"memory\" tool for persistent memory access.\n"
            .'Operations: search_memories, get_core_block, set_core_block, '
            ."list_core_blocks, get_entities, recall_conversations.\n"
            .'Use memory proactively to maintain context across conversations. '
            .'Store important task state and goals in operational core blocks.';
    }

    /**
     * Build session-specific browser context for the system prompt.
     *
     * Tells the agent what browser mode is active (persistent profile, CDP,
     * or ephemeral) so it can adapt its behavior accordingly.
     */
    private function buildBrowserSessionContext(RuntimeSession $session): ?string
    {
        if ($session->hasPersistentBrowserProfile()) {
            return implode("\n", [
                '## Active Browser Session: Persistent Profile',
                '',
                "This session uses a persistent browser profile (\"{$session->browser_profile_name}\").",
                'Cookies, localStorage, and login sessions are preserved across turns and restarts.',
                'If you have already logged into a site in a previous turn, you should still be logged in.',
                'The $AGENT_BROWSER_PROFILE environment variable is pre-configured — agent-browser will use it automatically.',
                'You do NOT need to pass --profile manually.',
                '',
                'The browser runs in headed (visible) mode so the user can see what you are doing.',
                '',
                'IMPORTANT: If a site blocks you with bot detection (X/Twitter, LinkedIn, etc.), persistent profiles',
                'still use Playwright Chromium which can be fingerprinted. Recommend the user switch to CDP mode',
                'by running `/browser cdp <ws://...>` which connects to their real Chrome browser instead.',
            ]);
        }

        if ($session->hasCdpConnection()) {
            $endpoint = $session->browser_cdp_endpoint;
            $port = $this->extractCdpPortFromEndpoint($endpoint);
            $connectTarget = $port !== null ? (string) $port : $endpoint;

            return implode("\n", [
                '## Active Browser Session: CDP Connection (Real Chrome)',
                '',
                "The user's Chrome is running with remote debugging on port {$connectTarget}.",
                'agent-browser (the browser sidecar) can connect to it via CDP to reuse existing login sessions.',
                '',
                'CRITICAL: You MUST use the `connect` command FIRST to attach to Chrome before any other command:',
                "  agent-browser connect {$connectTarget}",
                '',
                'After connecting, Chrome\'s existing tabs become available:',
                '  agent-browser tab list          # see Chrome\'s open tabs with their indices',
                '  agent-browser tab 1             # switch to an existing tab (e.g. one already logged into X)',
                '  agent-browser get url            # confirm which page you\'re on',
                '  agent-browser snapshot -i        # interact with the page',
                '',
                'To open a NEW page in Chrome (shares cookies/sessions with existing tabs):',
                '  agent-browser tab new',
                '  agent-browser open https://x.com',
                '',
                'WARNING: Do NOT use `--cdp` flag (e.g. `agent-browser --cdp 9222 open x.com`).',
                'The --cdp flag creates an ISOLATED context with NO cookies from Chrome.',
                'Always use `connect` command instead — it attaches to Chrome\'s existing context.',
                '',
                'This is the recommended mode for sites with bot detection (X/Twitter, LinkedIn, etc.)',
                'because it uses the real Chrome with genuine TLS fingerprint and existing login sessions.',
            ]);
        }

        return null;
    }

    /**
     * Extract the port number from a CDP WebSocket URL.
     *
     * E.g. "ws://localhost:9222/devtools/browser/abc123" → 9222
     */
    private function extractCdpPortFromEndpoint(string $endpoint): ?int
    {
        if (ctype_digit($endpoint)) {
            return (int) $endpoint;
        }

        // parse_url may not handle ws:// scheme, so try http:// substitution
        $httpUrl = (string) preg_replace('/^wss?:/', 'http:', $endpoint);
        $parsed = parse_url($httpUrl);

        return isset($parsed['port']) ? (int) $parsed['port'] : null;
    }

    /**
     * Build the vault section for the system prompt.
     *
     * Lists available credentials (metadata only, no secrets) and provides
     * instructions for retrieving secrets at runtime via the vault API.
     */
    private function buildVaultPromptSection(ConnectorAccount $account): ?string
    {
        try {
            $user = $account->user;
            if ($user === null) {
                return null;
            }

            $credentials = CredentialVault::query()
                ->where('user_id', $user->id)
                ->forRuntime()
                ->get();

            if ($credentials->isEmpty()) {
                return null;
            }

            $lines = [
                '## Persistent Credential Vault',
                '',
                '**VAULT-FIRST RULE: When asked to "log in" to ANY website or service, ALWAYS check this vault FIRST before attempting manual login or using browser `auth` commands.**',
                '',
                'Your user has a credential vault with stored credentials. These are PERSISTENT (not session-scoped) and survive across sessions.',
                'The browser `auth save/login/list` commands are SESSION-SCOPED and separate from this vault.',
                '',
                '### Available Credentials:',
            ];

            $grouped = $credentials->groupBy('credential_type');

            foreach ($grouped as $type => $creds) {
                $typeLabel = match ($type) {
                    'login' => 'Website Logins',
                    'api_key' => 'API Keys',
                    'ssh_key' => 'SSH Keys',
                    'oauth_token' => 'OAuth Tokens',
                    default => ucfirst((string) $type),
                };
                $lines[] = "**{$typeLabel}:**";

                foreach ($creds as $cred) {
                    $detail = "- {$cred->name} ({$cred->provider})";
                    if ($cred->url) {
                        $detail .= " — URL: {$cred->url}";
                    }
                    $modeLabel = match ($cred->approval_mode) {
                        'autonomous' => '[auto-approved]',
                        'supervised' => '[may need approval]',
                        'restricted' => '[restricted]',
                        default => '',
                    };
                    $detail .= " {$modeLabel}";
                    $lines[] = $detail;
                }
                $lines[] = '';
            }

            $lines[] = '### Retrieving Credentials:';
            $lines[] = 'Use curl via Bash to retrieve credential secrets from the vault API:';
            $lines[] = '';
            $lines[] = '```bash';
            $lines[] = '# List all available credentials (metadata only)';
            $lines[] = 'curl -s -X POST "$VAULT_API_BASE/list_credentials" -H "Authorization: Bearer $VAULT_TOKEN" -H "Content-Type: application/json"';
            $lines[] = '';
            $lines[] = '# Get login credentials for a URL (for browser automation)';
            $lines[] = 'curl -s -X POST "$VAULT_API_BASE/get_login_for_url" -H "Authorization: Bearer $VAULT_TOKEN" -H "Content-Type: application/json" -d \'{"url":"https://example.com"}\'';
            $lines[] = '';
            $lines[] = '# Get API key by provider name';
            $lines[] = 'curl -s -X POST "$VAULT_API_BASE/get_api_key" -H "Authorization: Bearer $VAULT_TOKEN" -H "Content-Type: application/json" -d \'{"provider":"stripe"}\'';
            $lines[] = '';
            $lines[] = '# Get any credential by provider/type';
            $lines[] = 'curl -s -X POST "$VAULT_API_BASE/get_credential" -H "Authorization: Bearer $VAULT_TOKEN" -H "Content-Type: application/json" -d \'{"provider":"aws","type":"ssh_key"}\'';
            $lines[] = '```';
            $lines[] = '';
            $lines[] = 'The $VAULT_API_BASE and $VAULT_TOKEN environment variables are pre-configured.';
            $lines[] = '### Login Workflow (step-by-step):';
            $lines[] = '1. **Check vault**: Call `get_login_for_url` with the target site URL';
            $lines[] = '2. **If found**: Retrieve the username + password from the vault response';
            $lines[] = '3. **Open the site**: Use `browser open <login_url>`';
            $lines[] = '4. **Fill the form**: Use `browser snapshot -i` to find form fields, then `browser fill @ref <value>` for each field';
            $lines[] = '5. **Submit**: Click the login/submit button';
            $lines[] = '6. **Verify**: Take a screenshot or snapshot to confirm login succeeded';
            $lines[] = '';
            $lines[] = 'Alternatively, use `browser auth save <name> --url <url> --username <user> --password <pass>` then `browser auth login <name>` to auto-fill credentials.';
            $lines[] = '';
            $lines[] = 'If no vault credentials match, tell the user: "I don\'t have stored credentials for this site. Would you like to add them to the vault?"';
            $lines[] = '';
            $lines[] = '### CRITICAL SECURITY RULE:';
            $lines[] = '**NEVER include credential values (passwords, API keys, tokens, secrets, or usernames) in your responses to the user.**';
            $lines[] = 'Credentials are for PROGRAMMATIC USE ONLY — pass them directly to tools (browser auth, curl, etc.).';
            $lines[] = 'If the user asks for a credential value, respond: "For security, I cannot display credentials in chat. I can use them programmatically for you."';
            $lines[] = 'NEVER echo, quote, display, or reference the actual value of any secret in any message.';

            return implode("\n", $lines);
        } catch (\Throwable $e) {
            Log::warning('ProcessRuntimeTurnJob: Failed to build vault prompt section', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $result
     */
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
        /** @var ChatSession|null $chatSession */
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
