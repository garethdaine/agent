<?php

declare(strict_types=1);

namespace App\Services\Skills\DTOs;

use App\Services\Skills\Validation\SkillValidationResult;

final readonly class SkillInstallResult
{
    public function __construct(
        public bool $success,
        public ?string $skillId,
        public ?SkillValidationResult $validationResult,
        public bool $requiresAdminConfirmation,
        public bool $blocked,
        public array $errors,
        public array $warnings,
    ) {}

    public static function success(string $skillId, SkillValidationResult $validationResult, array $warnings = []): self
    {
        return new self(
            success: true,
            skillId: $skillId,
            validationResult: $validationResult,
            requiresAdminConfirmation: false,
            blocked: false,
            errors: [],
            warnings: $warnings,
        );
    }

    public static function failure(array $errors, ?SkillValidationResult $validationResult = null, array $warnings = []): self
    {
        return new self(
            success: false,
            skillId: null,
            validationResult: $validationResult,
            requiresAdminConfirmation: false,
            blocked: false,
            errors: $errors,
            warnings: $warnings,
        );
    }

    public static function blocked(SkillValidationResult $validationResult, array $warnings = []): self
    {
        return new self(
            success: false,
            skillId: null,
            validationResult: $validationResult,
            requiresAdminConfirmation: false,
            blocked: true,
            errors: ['Skill blocked: risk score exceeds maximum threshold.'],
            warnings: $warnings,
        );
    }

    public static function requiresConfirmation(SkillValidationResult $validationResult, array $warnings = []): self
    {
        return new self(
            success: false,
            skillId: null,
            validationResult: $validationResult,
            requiresAdminConfirmation: true,
            blocked: false,
            errors: [],
            warnings: $warnings,
        );
    }
}
