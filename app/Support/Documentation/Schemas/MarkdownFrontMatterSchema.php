<?php

declare(strict_types=1);

namespace App\Support\Documentation\Schemas;

use Carbon\Carbon;
use DateTimeInterface;

class MarkdownFrontMatterSchema
{
    /**
     * @param  array<string, mixed>  $frontMatter
     */
    public function validate(array $frontMatter, string $sourcePath): void
    {
        $errors = [];

        /** @var array<int, string> $required */
        $required = config('documentation.required_front_matter', []);
        foreach ($required as $field) {
            if (! array_key_exists($field, $frontMatter)) {
                $errors[] = "Missing required front matter field: {$field}.";

                continue;
            }

            $value = $frontMatter[$field];
            if (is_string($value) && trim($value) === '') {
                $errors[] = "Front matter field '{$field}' must not be empty.";
            }
        }

        $this->validateStringField($frontMatter, 'slug', $errors);
        $this->validateStringField($frontMatter, 'title', $errors);
        $this->validateStringField($frontMatter, 'summary', $errors);
        $this->validateStringField($frontMatter, 'section', $errors);
        $this->validateStringField($frontMatter, 'audience', $errors);
        $this->validateStringField($frontMatter, 'status', $errors);
        $this->validateStringField($frontMatter, 'version', $errors);
        $this->validateStringField($frontMatter, 'owner', $errors);
        $this->validateStringField($frontMatter, 'locale', $errors);

        $slugPattern = (string) config('documentation.front_matter.slug_pattern', '/^[a-z0-9]+(?:-[a-z0-9]+)*$/');
        if (is_string($frontMatter['slug'] ?? null) && preg_match($slugPattern, $frontMatter['slug']) !== 1) {
            $errors[] = "Front matter field 'slug' must be kebab-case.";
        }

        /** @var array<int, string> $allowedStatuses */
        $allowedStatuses = config('documentation.allowed_statuses', []);
        if (is_string($frontMatter['status'] ?? null) && ! in_array($frontMatter['status'], $allowedStatuses, true)) {
            $errors[] = "Front matter field 'status' contains an unknown value.";
        }

        /** @var array<int, string> $allowedLocales */
        $allowedLocales = config('documentation.locale.allowed', ['en']);
        if (is_string($frontMatter['locale'] ?? null) && ! in_array($frontMatter['locale'], $allowedLocales, true)) {
            $errors[] = "Front matter field 'locale' must be one of: ".implode(', ', $allowedLocales).'.';
        }

        $reviewedAt = $frontMatter['reviewed_at'] ?? null;
        if ($reviewedAt instanceof DateTimeInterface) {
            $reviewedAt = $reviewedAt->format('Y-m-d');
        } elseif (is_int($reviewedAt)) {
            $reviewedAt = gmdate('Y-m-d', $reviewedAt);
        }

        if (! is_string($reviewedAt) || ! Carbon::hasFormat($reviewedAt, 'Y-m-d')) {
            $errors[] = "Front matter field 'reviewed_at' must use YYYY-MM-DD format.";
        }

        /** @var array<int, string> $arrayFields */
        $arrayFields = config('documentation.front_matter.array_fields', []);
        foreach ($arrayFields as $field) {
            if (! array_key_exists($field, $frontMatter)) {
                continue;
            }

            if (! is_array($frontMatter[$field])) {
                $errors[] = "Front matter field '{$field}' must be an array of strings.";

                continue;
            }

            foreach ($frontMatter[$field] as $index => $value) {
                if (! is_string($value) || trim($value) === '') {
                    $errors[] = "Front matter field '{$field}' entry {$index} must be a non-empty string.";
                }
            }
        }

        if ($errors !== []) {
            throw DocumentationValidationException::fromErrors(
                $errors,
                "Markdown front matter validation failed for {$sourcePath}"
            );
        }
    }

    /**
     * @param  array<string, mixed>  $frontMatter
     * @param  array<int, string>  $errors
     */
    private function validateStringField(array $frontMatter, string $field, array &$errors): void
    {
        if (! array_key_exists($field, $frontMatter)) {
            return;
        }

        if (! is_string($frontMatter[$field]) || trim($frontMatter[$field]) === '') {
            $errors[] = "Front matter field '{$field}' must be a non-empty string.";
        }
    }
}
