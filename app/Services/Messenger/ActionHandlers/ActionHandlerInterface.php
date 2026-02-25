<?php

namespace App\Services\Messenger\ActionHandlers;

use App\DTOs\Messenger\ActionResult;
use App\Models\User;

interface ActionHandlerInterface
{
    /**
     * Execute the action with the given parameters.
     *
     * @param  array<string, mixed>  $parameters
     */
    public function handle(array $parameters, User $user): ActionResult;

    /**
     * Validate the parameters for this action.
     *
     * @param  array<string, mixed>  $parameters
     * @return array<string, string>  Validation errors (empty if valid)
     */
    public function validate(array $parameters): array;
}
