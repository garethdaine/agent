<?php

namespace App\Services\Messenger;

use App\DTOs\Messenger\ActionResult;
use App\Enums\Messenger\ChatActionType;
use App\Messenger\ChatAction\ChatActionContext;
use App\Messenger\ChatAction\ChatActionResult;
use App\Messenger\ChatAction\Handlers\ChatActionHandlerInterface;
use App\Messenger\ChatAction\Handlers\JobsCreateHandler;
use App\Messenger\ChatAction\Handlers\JobsDeleteHandler;
use App\Messenger\ChatAction\Handlers\JobsListHandler;
use App\Messenger\ChatAction\Handlers\JobsUpdateHandler;
use App\Messenger\ChatAction\Handlers\RunsListActiveHandler;
use App\Messenger\ChatAction\Handlers\RunsRetryHandler;
use App\Messenger\ChatAction\Handlers\RunsRunNowHandler;
use App\Messenger\ChatAction\Handlers\RunsSteerHandler;
use App\Messenger\ChatAction\Handlers\RunsStopHandler;
use App\Models\AgentJob;
use App\Models\AgentJobRun;
use App\Models\ChatAction;
use App\Models\User;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Log;

class ChatActionExecutor
{
    /**
     * @var array<string, class-string<ChatActionHandlerInterface>>
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

        // Build context for the handler
        $context = $this->buildContext($action, $user);

        // Execute the action
        try {
            $result = $handler->handle($context);

            Log::info('ChatActionExecutor: Action executed', [
                'action_id' => $action->id,
                'action_type' => $action->action_type,
                'user_id' => $user->id,
                'success' => $result->isSuccess(),
            ]);

            return $this->convertResult($result);

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
     * Build the context for handler execution.
     */
    private function buildContext(ChatAction $action, User $user): ChatActionContext
    {
        $parameters = $action->parameters ?? [];

        return new ChatActionContext(
            user: $user,
            parameters: $parameters,
            action: $action->action_type,
            targetJob: $this->resolveTargetJob($parameters),
            confirmed: $action->confirmed_at !== null,
            targetRun: $this->resolveTargetRun($parameters),
        );
    }

    /**
     * Resolve target job from parameters if job_id is present.
     */
    private function resolveTargetJob(array $parameters): ?AgentJob
    {
        $jobId = $parameters['job_id'] ?? null;

        return $jobId !== null ? AgentJob::find($jobId) : null;
    }

    /**
     * Resolve target run from parameters if run_id is present.
     */
    private function resolveTargetRun(array $parameters): ?AgentJobRun
    {
        $runId = $parameters['run_id'] ?? null;

        return $runId !== null ? AgentJobRun::find($runId) : null;
    }

    /**
     * Convert ChatActionResult to ActionResult for backward compatibility.
     */
    private function convertResult(ChatActionResult $result): ActionResult
    {
        if ($result->isSuccess()) {
            return ActionResult::success(
                data: $result->getData(),
                message: $result->getMessage()
            );
        }

        return ActionResult::failure($result->getMessage(), $result->getData());
    }

    /**
     * Resolve the handler for an action type.
     */
    public function resolveHandler(string $actionType): ?ChatActionHandlerInterface
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
     * @param  class-string<ChatActionHandlerInterface>  $handlerClass
     */
    public function registerHandler(string $actionType, string $handlerClass): void
    {
        $this->handlers[$actionType] = $handlerClass;
    }
}
