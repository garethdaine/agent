<?php

declare(strict_types=1);

namespace App\Services\Skills\DTOs;

final readonly class SkillLibraryEntry
{
    public function __construct(
        public string $slug,
        public string $name,
        public string $description,
        public string $version,
        public string $author,
        public string $category,
        public array $industries,
        public string $riskLevel,
        public string $filePath,
        public string $checksum,
    ) {}
}
