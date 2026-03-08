<?php

declare(strict_types=1);

namespace App\Services\Skills\DTOs;

final readonly class SkillParseResult
{
    public function __construct(
        public ?string $name,
        public ?string $description,
        public ?string $version,
        public ?string $author,
        public ?string $license,
        public string $riskLevel,
        public array $industries,
        public array $memoryBlocks,
        public array $mcpDependencies,
        public array $toolsRequired,
        public array $triggerKeywords,
        public array $runAfter,
        public bool $requiresApproval,
        public ?string $compatibility,
        public string $markdownBody,
        public array $warnings,
        public array $errors,
        public array $rawFrontmatter,
    ) {}

    public function hasErrors(): bool
    {
        return count($this->errors) > 0;
    }

    public function isValid(): bool
    {
        return ! $this->hasErrors();
    }
}
