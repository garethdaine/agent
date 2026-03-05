<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\MessengerConnectorResource;
use App\Models\ConnectorAccount;
use App\Support\Agent\AuditLogger;
use App\Support\Agent\ErrorEnvelope;
use App\Support\Messenger\ConnectorCredentialManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class MessengerConnectorController extends Controller
{
    public function schema(ConnectorCredentialManager $credentialManager): JsonResponse
    {
        return response()->json([
            'data' => $credentialManager->schema(array_keys(config('messenger.adapters', []))),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $query = ConnectorAccount::query();

        $provider = $request->string('provider')->toString();
        $validProviders = [
            ConnectorAccount::PROVIDER_SLACK,
            ConnectorAccount::PROVIDER_TELEGRAM,
            ConnectorAccount::PROVIDER_DISCORD,
            ConnectorAccount::PROVIDER_WHATSAPP,
        ];
        if (in_array($provider, $validProviders, true)) {
            $query->where('provider', $provider);
        }

        $status = $request->string('status')->toString();
        $validStatuses = [
            ConnectorAccount::STATUS_CONNECTED,
            ConnectorAccount::STATUS_DISCONNECTED,
            ConnectorAccount::STATUS_ERROR,
        ];
        if (in_array($status, $validStatuses, true)) {
            $query->where('status', $status);
        }

        $connectionMode = $request->string('connection_mode')->toString();
        if (in_array($connectionMode, [ConnectorAccount::MODE_LOCAL, ConnectorAccount::MODE_WEBHOOK], true)) {
            $query->where('connection_mode', $connectionMode);
        }

        $sort = $request->string('sort', 'created_at')->toString();
        $dir = strtolower($request->string('dir', 'desc')->toString()) === 'asc' ? 'asc' : 'desc';

        if (! in_array($sort, ['name', 'created_at', 'updated_at', 'provider'], true)) {
            $sort = 'created_at';
        }

        $query->orderBy($sort, $dir);

        if ($request->boolean('with_session_count')) {
            $query->withCount('sessions');
        }

        $perPage = min(100, max(1, $request->integer('per_page', 25)));
        $connectors = $query->paginate($perPage)->withQueryString();

        return response()->json([
            'data' => MessengerConnectorResource::collection($connectors->items()),
            'meta' => [
                'current_page' => $connectors->currentPage(),
                'per_page' => $connectors->perPage(),
                'total' => $connectors->total(),
                'last_page' => $connectors->lastPage(),
            ],
            'links' => [
                'first' => $connectors->url(1),
                'last' => $connectors->url($connectors->lastPage()),
                'prev' => $connectors->previousPageUrl(),
                'next' => $connectors->nextPageUrl(),
            ],
            'filters' => [
                'provider' => $request->input('provider'),
                'status' => $request->input('status'),
                'connection_mode' => $request->input('connection_mode'),
            ],
            'sort' => [
                'sort' => $sort,
                'dir' => $dir,
            ],
        ]);
    }

    public function store(Request $request, AuditLogger $auditLogger): JsonResponse
    {
        $credentialManager = app(ConnectorCredentialManager::class);
        $validProviders = $credentialManager->supportedProviders();

        $validator = Validator::make($request->all(), [
            'provider' => ['required', 'string', Rule::in($validProviders)],
            'name' => ['required', 'string', 'min:1', 'max:255'],
            'credentials' => ['required', 'array'],
            'webhook_secret' => ['nullable', 'string', 'max:255'],
            'connection_mode' => ['nullable', 'string', Rule::in([ConnectorAccount::MODE_LOCAL, ConnectorAccount::MODE_WEBHOOK])],
            'config' => ['nullable', 'array'],
            'account_key' => ['nullable', 'string', 'max:64'],
        ]);

        if ($validator->fails()) {
            return ErrorEnvelope::make('VALIDATION_ERROR', 'The given data was invalid.', 422, $validator->errors()->toArray());
        }

        $validated = $validator->validated();
        $provider = strtolower(trim((string) $validated['provider']));
        $connectionMode = $credentialManager->normalizeConnectionMode($provider, $validated['connection_mode'] ?? null);
        $normalizedCredentials = $credentialManager->normalizeCredentials($provider, $validated['credentials'] ?? []);

        $missingRequired = $credentialManager->missingRequiredCredentials($provider, $normalizedCredentials, $connectionMode);
        if ($missingRequired !== []) {
            $errors = [];
            foreach ($missingRequired as $missingKey) {
                $errors['credentials.'.$missingKey] = ['This credential is required for the selected provider.'];
            }

            return ErrorEnvelope::make('VALIDATION_ERROR', 'Missing required credentials.', 422, $errors);
        }

        $accountKey = trim((string) ($validated['account_key'] ?? ''));
        if ($accountKey === '') {
            $accountKey = $credentialManager->deriveAccountKey($provider, $normalizedCredentials);
        }

        $existing = ConnectorAccount::query()
            ->where('provider', $provider)
            ->where('account_key', $accountKey)
            ->exists();

        if ($existing) {
            return ErrorEnvelope::make('DUPLICATE_CONNECTOR', 'A connector with this provider and account key already exists.', 409, [
                'provider' => $provider,
                'account_key' => $accountKey,
            ]);
        }

        $customConfig = is_array($validated['config'] ?? null) ? $validated['config'] : [];
        $connectorConfig = $credentialManager->buildConfig($provider, $normalizedCredentials, $customConfig, $connectionMode);
        $webhookSecret = trim((string) ($validated['webhook_secret'] ?? ''));
        if ($webhookSecret === '') {
            $webhookSecret = (string) ($credentialManager->inferWebhookSecret($provider, $normalizedCredentials) ?? '');
        }

        $connector = ConnectorAccount::create([
            'provider' => $provider,
            'name' => $validated['name'],
            'credentials' => $normalizedCredentials,
            'webhook_secret' => $webhookSecret !== '' ? $webhookSecret : null,
            'connection_mode' => $connectionMode,
            'status' => ConnectorAccount::STATUS_DISCONNECTED,
            'config' => $connectorConfig,
            'account_key' => $accountKey,
        ]);

        $auditLogger->recordUserAction(
            request: $request,
            action: 'connector.create',
            targetType: 'connector_account',
            targetId: $connector->id,
            ownerUserId: $request->user()->id,
            changedFields: array_keys($connector->getAttributes()),
            before: null,
            after: $connector->only(['id', 'provider', 'name', 'connection_mode', 'status', 'account_key']),
        );

        return response()->json([
            'data' => new MessengerConnectorResource($connector),
        ], 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $query = ConnectorAccount::query();

        if ($request->boolean('with_session_count')) {
            $query->withCount('sessions');
        }

        $connector = $query->findOrFail($id);

        return response()->json([
            'data' => new MessengerConnectorResource($connector),
        ]);
    }

    public function update(Request $request, string $id, AuditLogger $auditLogger): JsonResponse
    {
        $connector = ConnectorAccount::findOrFail($id);
        $credentialManager = app(ConnectorCredentialManager::class);

        $validator = Validator::make($request->all(), [
            'name' => ['sometimes', 'string', 'min:1', 'max:255'],
            'credentials' => ['sometimes', 'array'],
            'webhook_secret' => ['nullable', 'string', 'max:255'],
            'connection_mode' => ['sometimes', 'string', Rule::in([ConnectorAccount::MODE_LOCAL, ConnectorAccount::MODE_WEBHOOK])],
            'config' => ['sometimes', 'array'],
            'account_key' => ['sometimes', 'string', 'max:64'],
            'status' => ['sometimes', 'string', Rule::in([ConnectorAccount::STATUS_CONNECTED, ConnectorAccount::STATUS_DISCONNECTED, ConnectorAccount::STATUS_ERROR])],
        ]);

        if ($validator->fails()) {
            return ErrorEnvelope::make('VALIDATION_ERROR', 'The given data was invalid.', 422, $validator->errors()->toArray());
        }

        $validated = $validator->validated();
        $provider = strtolower(trim((string) $connector->provider));

        $normalizedCredentials = $connector->credentials ?? [];
        if (array_key_exists('credentials', $validated)) {
            $incoming = $credentialManager->normalizeCredentials($provider, $validated['credentials'] ?? []);
            $normalizedCredentials = array_replace($normalizedCredentials, $incoming);
            $validated['credentials'] = $normalizedCredentials;
        }

        if (array_key_exists('connection_mode', $validated)) {
            $validated['connection_mode'] = $credentialManager->normalizeConnectionMode($provider, (string) ($validated['connection_mode'] ?? null));
        }
        $connectionMode = $validated['connection_mode'] ?? $connector->connection_mode;

        $missingRequired = $credentialManager->missingRequiredCredentials($provider, $normalizedCredentials, $connectionMode);
        if ($missingRequired !== []) {
            $errors = [];
            foreach ($missingRequired as $missingKey) {
                $errors['credentials.'.$missingKey] = ['This credential is required for the selected provider.'];
            }

            return ErrorEnvelope::make('VALIDATION_ERROR', 'Missing required credentials.', 422, $errors);
        }

        if (array_key_exists('account_key', $validated)) {
            $accountKey = trim((string) ($validated['account_key'] ?? ''));
            if ($accountKey === '') {
                $accountKey = $credentialManager->deriveAccountKey($provider, $normalizedCredentials);
            }

            $duplicate = ConnectorAccount::query()
                ->where('provider', $provider)
                ->where('account_key', $accountKey)
                ->where('id', '!=', $connector->id)
                ->exists();

            if ($duplicate) {
                return ErrorEnvelope::make('DUPLICATE_CONNECTOR', 'A connector with this provider and account key already exists.', 409, [
                    'provider' => $provider,
                    'account_key' => $accountKey,
                ]);
            }

            $validated['account_key'] = $accountKey;
        }

        if (array_key_exists('config', $validated) || array_key_exists('credentials', $validated) || array_key_exists('connection_mode', $validated)) {
            $baseConfig = is_array($connector->config) ? $connector->config : [];
            $incomingConfig = is_array($validated['config'] ?? null) ? $validated['config'] : [];
            $mergedConfig = array_replace_recursive($baseConfig, $incomingConfig);
            $validated['config'] = $credentialManager->buildConfig($provider, $normalizedCredentials, $mergedConfig, $connectionMode);
        }

        if (array_key_exists('webhook_secret', $validated)) {
            $webhookSecret = trim((string) ($validated['webhook_secret'] ?? ''));
            $validated['webhook_secret'] = $webhookSecret !== '' ? $webhookSecret : null;
        } elseif (array_key_exists('credentials', $validated)) {
            $inferred = $credentialManager->inferWebhookSecret($provider, $normalizedCredentials);
            if (is_string($inferred) && trim($inferred) !== '') {
                $validated['webhook_secret'] = trim($inferred);
            }
        }

        $before = $connector->only(['name', 'connection_mode', 'status', 'account_key']);
        $changedFields = [];

        foreach ($validated as $key => $value) {
            if ($value !== $connector->$key) {
                $changedFields[] = $key;
            }
        }

        $connector->fill($validated);
        $connector->save();

        if (count($changedFields) > 0) {
            $auditLogger->recordUserAction(
                request: $request,
                action: 'connector.update',
                targetType: 'connector_account',
                targetId: $connector->id,
                ownerUserId: $request->user()->id,
                changedFields: $changedFields,
                before: $before,
                after: $connector->only(['name', 'connection_mode', 'status', 'account_key']),
            );
        }

        return response()->json([
            'data' => new MessengerConnectorResource($connector->fresh()),
        ]);
    }

    public function destroy(Request $request, string $id, AuditLogger $auditLogger): JsonResponse
    {
        $connector = ConnectorAccount::findOrFail($id);

        $before = $connector->only(['id', 'provider', 'name', 'status']);

        $connector->update(['status' => ConnectorAccount::STATUS_DISCONNECTED]);
        $connector->delete();

        $auditLogger->recordUserAction(
            request: $request,
            action: 'connector.delete',
            targetType: 'connector_account',
            targetId: $connector->id,
            ownerUserId: $request->user()->id,
            changedFields: ['deleted_at'],
            before: $before,
            after: null,
        );

        return response()->json([
            'data' => [
                'id' => $connector->id,
                'deleted' => true,
            ],
        ]);
    }

    public function soul(Request $request, string $id, AuditLogger $auditLogger): JsonResponse
    {
        $connector = ConnectorAccount::findOrFail($id);

        if ($request->isMethod('GET')) {
            return response()->json([
                'data' => $connector->getSoul(),
            ]);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['nullable', 'string', 'max:100'],
            'personality' => ['nullable', 'string', 'max:2000'],
            'system_prompt' => ['nullable', 'string', 'max:5000'],
            'user_context' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($validator->fails()) {
            return ErrorEnvelope::make('VALIDATION_ERROR', 'The given data was invalid.', 422, $validator->errors()->toArray());
        }

        $before = $connector->getSoul();
        $connector->setSoul($validator->validated());

        $auditLogger->recordUserAction(
            request: $request,
            action: 'connector.soul.update',
            targetType: 'connector_account',
            targetId: $connector->id,
            ownerUserId: $request->user()->id,
            changedFields: ['config.soul'],
            before: $before,
            after: $connector->getSoul(),
        );

        return response()->json([
            'data' => $connector->getSoul(),
        ]);
    }

    public function test(Request $request, string $id): JsonResponse
    {
        $connector = ConnectorAccount::findOrFail($id);
        $provider = strtolower(trim((string) $connector->provider));
        $credentials = is_array($connector->credentials) ? $connector->credentials : [];
        $credentialManager = app(ConnectorCredentialManager::class);

        try {
            $result = match ($provider) {
                ConnectorAccount::PROVIDER_SLACK => $this->testSlackCredentials($credentials),
                ConnectorAccount::PROVIDER_TELEGRAM => $this->testTelegramCredentials($credentials),
                ConnectorAccount::PROVIDER_DISCORD => $this->testDiscordCredentials($credentials),
                ConnectorAccount::PROVIDER_WHATSAPP => $this->testWhatsAppCredentials($credentials),
                default => [
                    'ok' => false,
                    'message' => 'Connectivity testing is not yet implemented for this provider.',
                    'details' => [],
                    'derived_account_key' => null,
                ],
            };
        } catch (\Throwable $throwable) {
            $connector->update(['status' => ConnectorAccount::STATUS_ERROR]);

            return response()->json([
                'data' => [
                    'id' => $connector->id,
                    'provider' => $provider,
                    'connection_mode' => $connector->connection_mode,
                    'test_status' => 'failed',
                    'message' => $throwable->getMessage(),
                    'details' => [],
                ],
            ], 422);
        }

        if (($result['ok'] ?? false) === true) {
            $updates = [
                'status' => ConnectorAccount::STATUS_CONNECTED,
            ];

            $derivedAccountKey = trim((string) ($result['derived_account_key'] ?? ''));
            if ($derivedAccountKey !== '') {
                $duplicate = ConnectorAccount::query()
                    ->where('provider', $provider)
                    ->where('account_key', $derivedAccountKey)
                    ->where('id', '!=', $connector->id)
                    ->exists();

                if (! $duplicate) {
                    $updates['account_key'] = $derivedAccountKey;
                }
            }

            $connector->update($updates);
            $connector = $connector->fresh();
        } else {
            $connector->update(['status' => ConnectorAccount::STATUS_ERROR]);
            $connector = $connector->fresh();
        }

        return response()->json([
            'data' => [
                ...(new MessengerConnectorResource($connector))->resolve(),
                'id' => $connector->id,
                'provider' => $connector->provider,
                'connection_mode' => $connector->connection_mode,
                'test_status' => ($result['ok'] ?? false) === true ? 'connected' : 'failed',
                'message' => (string) ($result['message'] ?? 'Connectivity test complete.'),
                'details' => is_array($result['details'] ?? null) ? $result['details'] : [],
                'setup' => [
                    'webhook_url' => $credentialManager->webhookUrl($connector),
                ],
            ],
        ]);
    }

    /**
     * @param  array<string, string>  $credentials
     * @return array{ok:bool,message:string,details:array<string,mixed>,derived_account_key:?string}
     */
    private function testSlackCredentials(array $credentials): array
    {
        $botToken = trim((string) ($credentials['bot_token'] ?? ''));
        if ($botToken === '') {
            return [
                'ok' => false,
                'message' => 'Missing Slack bot token.',
                'details' => [],
                'derived_account_key' => null,
            ];
        }

        $response = Http::withToken($botToken)
            ->timeout(10)
            ->get('https://slack.com/api/auth.test');

        if (! $response->successful()) {
            return [
                'ok' => false,
                'message' => 'Failed to reach Slack API.',
                'details' => ['http_status' => $response->status()],
                'derived_account_key' => null,
            ];
        }

        $payload = $response->json();
        if (! ($payload['ok'] ?? false)) {
            return [
                'ok' => false,
                'message' => (string) ($payload['error'] ?? 'Slack API rejected credentials.'),
                'details' => $payload,
                'derived_account_key' => null,
            ];
        }

        $teamId = trim((string) ($payload['team_id'] ?? ''));

        return [
            'ok' => true,
            'message' => 'Slack credentials verified and connector activated.',
            'details' => [
                'team' => $payload['team'] ?? null,
                'user' => $payload['user'] ?? null,
            ],
            'derived_account_key' => $teamId !== '' ? $teamId : null,
        ];
    }

    /**
     * @param  array<string, string>  $credentials
     * @return array{ok:bool,message:string,details:array<string,mixed>,derived_account_key:?string}
     */
    private function testTelegramCredentials(array $credentials): array
    {
        $botToken = trim((string) ($credentials['bot_token'] ?? ''));
        if ($botToken === '') {
            return [
                'ok' => false,
                'message' => 'Missing Telegram bot token.',
                'details' => [],
                'derived_account_key' => null,
            ];
        }

        $response = Http::timeout(10)
            ->get(sprintf('https://api.telegram.org/bot%s/getMe', $botToken));

        if (! $response->successful()) {
            return [
                'ok' => false,
                'message' => 'Failed to reach Telegram API.',
                'details' => ['http_status' => $response->status()],
                'derived_account_key' => null,
            ];
        }

        $payload = $response->json();
        if (! ($payload['ok'] ?? false)) {
            return [
                'ok' => false,
                'message' => (string) ($payload['description'] ?? 'Telegram API rejected credentials.'),
                'details' => $payload,
                'derived_account_key' => null,
            ];
        }

        $botId = trim((string) ($payload['result']['id'] ?? ''));

        return [
            'ok' => true,
            'message' => 'Telegram credentials verified and connector activated.',
            'details' => [
                'username' => $payload['result']['username'] ?? null,
                'first_name' => $payload['result']['first_name'] ?? null,
            ],
            'derived_account_key' => $botId !== '' ? $botId : null,
        ];
    }

    /**
     * @param  array<string, string>  $credentials
     * @return array{ok:bool,message:string,details:array<string,mixed>,derived_account_key:?string}
     */
    private function testDiscordCredentials(array $credentials): array
    {
        $botToken = trim((string) ($credentials['bot_token'] ?? ''));
        $applicationId = trim((string) ($credentials['application_id'] ?? ''));

        if ($botToken === '') {
            return [
                'ok' => false,
                'message' => 'Missing Discord bot token.',
                'details' => [],
                'derived_account_key' => null,
            ];
        }

        if ($applicationId === '') {
            return [
                'ok' => false,
                'message' => 'Missing Discord application ID.',
                'details' => [],
                'derived_account_key' => null,
            ];
        }

        // Validate bot token via Discord API
        $response = Http::withHeaders([
            'Authorization' => 'Bot '.$botToken,
        ])->timeout(10)->get('https://discord.com/api/v10/users/@me');

        if (! $response->successful()) {
            return [
                'ok' => false,
                'message' => $response->status() === 401
                    ? 'Invalid Discord bot token.'
                    : 'Failed to reach Discord API.',
                'details' => ['http_status' => $response->status()],
                'derived_account_key' => null,
            ];
        }

        $payload = $response->json();
        $botUsername = $payload['username'] ?? null;

        // Register slash commands
        $registrar = app(\App\Services\Messenger\SlashCommandRegistrar::class);
        $registrationResult = $registrar->register($credentials);

        return [
            'ok' => true,
            'message' => 'Discord credentials verified and connector activated.',
            'details' => [
                'bot_username' => $botUsername,
                'bot_id' => $payload['id'] ?? null,
                'slash_commands' => [
                    'registered' => $registrationResult->isSuccessful(),
                    'message' => $registrationResult->getMessage(),
                    'count' => $registrationResult->getCommandCount(),
                ],
            ],
            'derived_account_key' => $applicationId,
        ];
    }

    /**
     * @param  array<string, string>  $credentials
     * @return array{ok:bool,message:string,details:array<string,mixed>,derived_account_key:?string}
     */
    private function testWhatsAppCredentials(array $credentials): array
    {
        $accessToken = trim((string) ($credentials['access_token'] ?? ''));
        $phoneNumberId = trim((string) ($credentials['phone_number_id'] ?? ''));

        if ($accessToken === '') {
            return [
                'ok' => false,
                'message' => 'Missing WhatsApp access token.',
                'details' => [],
                'derived_account_key' => null,
            ];
        }

        if ($phoneNumberId === '') {
            return [
                'ok' => false,
                'message' => 'Missing WhatsApp phone number ID.',
                'details' => [],
                'derived_account_key' => null,
            ];
        }

        // Validate access token via Graph API
        $response = Http::withToken($accessToken)
            ->timeout(10)
            ->get("https://graph.facebook.com/v18.0/{$phoneNumberId}");

        if (! $response->successful()) {
            $error = $response->json('error.message') ?? 'WhatsApp API rejected credentials.';

            return [
                'ok' => false,
                'message' => $response->status() === 401
                    ? 'Invalid WhatsApp access token.'
                    : $error,
                'details' => ['http_status' => $response->status()],
                'derived_account_key' => null,
            ];
        }

        $payload = $response->json();

        return [
            'ok' => true,
            'message' => 'WhatsApp credentials verified and connector activated.',
            'details' => [
                'phone_number_id' => $payload['id'] ?? $phoneNumberId,
                'display_phone_number' => $payload['display_phone_number'] ?? null,
                'verified_name' => $payload['verified_name'] ?? null,
            ],
            'derived_account_key' => $phoneNumberId,
        ];
    }
}
