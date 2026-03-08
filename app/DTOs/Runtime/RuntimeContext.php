<?php

declare(strict_types=1);

namespace App\DTOs\Runtime;

use App\Enums\Runtime\RuntimeMode;
use App\Models\Runtime\RuntimeSession;
use App\Models\Runtime\RuntimeTurn;
use App\Models\User;

readonly class RuntimeContext
{
    public function __construct(
        public RuntimeSession $session,
        public RuntimeTurn $turn,
        public User $user,
        public RuntimeMode $mode,
        public array $policy,
        public ?string $workspaceRoot = null,
    ) {}
}
