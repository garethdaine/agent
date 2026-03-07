<?php

declare(strict_types=1);

namespace App\Jobs\Skills;

use App\Services\Skills\SkillInstaller;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ValidateSkillJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public array $backoff = [10, 30, 60];

    public function __construct(
        public readonly string $filePath,
        public readonly int $teamId,
        public readonly int $userId,
        public readonly string $source,
    ) {
        $this->onQueue('skill-validation');
    }

    public function handle(SkillInstaller $installer): void
    {
        $installer->installFromFile(
            filePath: $this->filePath,
            teamId: $this->teamId,
            userId: $this->userId,
            source: $this->source,
        );
    }
}
