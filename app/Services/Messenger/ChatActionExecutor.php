<?php

namespace App\Services\Messenger;

use App\DTOs\Messenger\ActionResult;
use App\Enums\Messenger\ChatActionType;
use App\Models\ChatAction;
use App\Models\User;
use App\Services\Messenger\ActionHandlers\ActionHandlerInterface;
use App\Services\Messenger\ActionHandlers\JobsCreateHandler;
use App\Services\Messenger\ActionHandlers\JobsDeleteHandler;
use App\Services\Messenger\ActionHandlers\JobsListHandler;
use App\Services\Messenger\ActionHandlers\JobsUpdateHandler;
use App\Services\Messenger\ActionHandlers\RunsListActiveHandler;
use App\Services\Messenger\ActionHandlers\RunsRetryHandler;
use App\Services\Messenger\ActionHandlers\RunsRunNowHandler;
use App\Services\Messenger\ActionHandlers\RunsSteerHandler;
use App\Services\Messenger\ActionHandlers\RunsStopHandler;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Log;

class ChatActionExecutor
{
    /**
     * @var array<string, class-string<ActionHandlerInterface>>
     */
    private array $handlers = [
        'jobs.list' => JobsListHandler::class,
        'jobs.create' => JobsCreateHandler::class,
        'jobs.update' => JobsUpdateHandler::class,
        'jobs.delete' => JobsDeleteHandler::class,
        'runs.list_active' => RunsListActiveHandler::class,
        'runs.stop' => RunsStopHandler::class,
        'runs.retry' => RunsRetryHandler::class,
        'runs.run_now' => RunsRunNowHandler::class,
        'runs.steer' => RunsSteerHandler::class,
    ];

    public function __construct(
        private Container $container,
        private ChatActionPolicyValidator $policyValidator,
    ) {}

    /**
     * Execute a chat action.
     */
    public function execute(ChatAction $action, User $user): ActionResult
    {
        $actionType = ChatActionType::tryFrom($action->action_type);

        if ($actionType === null) {
            Log::warning('ChatActionExecutor: Unknown action type', [
                'action_id' => $action->id,
                'action_type' => $action->action_type,
            ]);

            return ActionResult::failure("Unknown action type: {$action->action_type}");
        }

        // Validate policy
        $policyResult = $this->policyValidator->validate($action, $user);

        if (! $policyResult->allowed) {
            Log::warning('ChatActionExecutor: Policy validation failed', [
                'action_id' => $action->id,
                'user_id' => $user->id,
                'reason' => $policyResult->reason,
            ]);

            return ActionResult::failure($policyResult->reason);
        }

        // Resolve handler
        $handler = $this->resolveHandler($action->action_type);

        if ($handler === null) {
            return ActionResult::failure("No handler registered for action: {$action->action_type}");
        }

        // Validate parameters
        $validationErrors = $handler->validate($action->parameters ?? []);

        if (! empty($validationErrors)) {
            return ActionResult::failure(
                'Invalid parameters: '.implode(', ', $validationErrors)
            );
        }

        // Execute the action
        try {
            $result = $handler->handle($action->parameters ?? [], $user);

            Log::info('ChatActionExecutor: Action executed', [
                'action_id' => $action->id,
                'action_type' => $action->action_type,
                'user_id' => $user->id,
                'success' => $result->success,
            ]);

            return $result;

        } catch (\Throwable $e) {
            Log::error('ChatActionExecutor: Action execution failed', [
                'action_id' => $action->id,
                'action_type' => $action->action_type,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ActionResult::failure("Action execution failed: {$e->getMessage()}");
        }
    }

    /**
     * Resolve the handler for an action type.
     */
    public function resolveHandler(string $actionType): ?ActionHandlerInterface
    {
        $handlerClass = $this->handlers[$actionType] ?? null;

        if ($handlerClass === null) {
            return null;
        }

        return $this->container->make($handlerClass);
    }

    /**
     * Check if a handler exists for an action type.
     */
    public function hasHandler(string $actionType): bool
    {
        return isset($this->handlers[$actionType]);
    }

    /**
     * Register a custom handler for an action type.
     *
     * @param  class-string<ActionHandlerInterface>  $handlerClass
     */
    public function registerHandler(string $actionType, string $handlerClass): void
    {
        $this->handlers[$actionType] = $handlerClass;
    }
}
