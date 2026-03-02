<?php

declare(strict_types=1);

namespace App\Support\Documentation\Ingestion;

use App\Support\Documentation\Schemas\DocumentationValidationException;
use App\Support\Documentation\Schemas\TooltipFragmentSchema;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class TooltipYamlParser
{
    public function __construct(
        private readonly TooltipFragmentSchema $schema
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function parse(string $yamlContent, string $sourcePath): array
    {
        try {
            $parsed = Yaml::parse($yamlContent, Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE);
        } catch (ParseException $exception) {
            throw DocumentationValidationException::fromErrors(
                ['Malformed tooltip YAML: '.$exception->getMessage()],
                "Tooltip schema validation failed for {$sourcePath}"
            );
        }

        if (! is_array($parsed) || ! array_is_list($parsed)) {
            throw DocumentationValidationException::fromErrors(
                ['Tooltip YAML must be a list of fragment objects.'],
                "Tooltip schema validation failed for {$sourcePath}"
            );
        }

        $errors = [];
        $validated = [];

        foreach ($parsed as $index => $fragment) {
            if (! is_array($fragment) || array_is_list($fragment)) {
                $errors[] = "Tooltip fragment #{$index} must be an object.";

                continue;
            }

            try {
                $validated[] = $this->schema->validate($fragment, $sourcePath, $index);
            } catch (DocumentationValidationException $exception) {
                $errors = [...$errors, ...$exception->errors()];
            }
        }

        if ($errors !== []) {
            throw DocumentationValidationException::fromErrors(
                $errors,
                "Tooltip schema validation failed for {$sourcePath}"
            );
        }

        return $validated;
    }
}
