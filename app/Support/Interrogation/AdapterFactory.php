<?php

declare(strict_types=1);

namespace App\Support\Interrogation;

use App\Support\Interrogation\Adapters\ClaudeAdapter;
use App\Support\Interrogation\Adapters\CodexAdapter;
use App\Support\Interrogation\Contracts\InterrogationRunnerAdapter;
use InvalidArgumentException;

class AdapterFactory
{
    public function make(string $runnerType, ?string $model = null): InterrogationRunnerAdapter
    {
        $adapter = match ($runnerType) {
            'claude' => app(ClaudeAdapter::class),
            'codex' => app(CodexAdapter::class),
            default => throw new InvalidArgumentException(sprintf('Unsupported interrogation runner type: %s', $runnerType)),
        };

        if ($model !== null && $model !== '') {
            $adapter->setModelOverride($model);
        }

        return $adapter;
    }
}
