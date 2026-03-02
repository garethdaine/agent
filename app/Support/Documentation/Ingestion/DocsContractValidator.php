<?php

declare(strict_types=1);

namespace App\Support\Documentation\Ingestion;

use App\Support\Documentation\Schemas\DocumentationValidationException;
use Illuminate\Support\Facades\File;

class DocsContractValidator
{
    public function __construct(
        private readonly MarkdownFrontMatterParser $markdownParser,
        private readonly TooltipYamlParser $tooltipParser
    ) {}

    /**
     * @return array{front_matter: array<string, mixed>, body: string}
     */
    public function validateMarkdownString(string $markdown, string $sourcePath): array
    {
        return $this->markdownParser->parse($markdown, $sourcePath);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function validateTooltipYamlString(string $yaml, string $sourcePath): array
    {
        return $this->tooltipParser->parse($yaml, $sourcePath);
    }

    /**
     * @return array{
     *   markdown_files: int,
     *   tooltip_files: int,
     *   tooltip_fragments: int
     * }
     */
    public function validateRepository(bool $failFast = false): array
    {
        $errors = [];

        $productPath = (string) config('documentation.paths.product');
        $apiPath = (string) config('documentation.paths.api');
        $tooltipsPath = (string) config('documentation.paths.tooltips');

        $this->assertDirectory($productPath, 'product docs', $errors);
        $this->assertDirectory($apiPath, 'api docs', $errors);
        $this->assertDirectory($tooltipsPath, 'tooltip docs', $errors);

        if ($errors !== []) {
            throw DocumentationValidationException::fromErrors($errors, 'Repository docs contract validation failed');
        }

        $markdownFiles = [
            ...$this->findFilesByExtensions($productPath, ['md', 'markdown']),
            ...$this->findFilesByExtensions($apiPath, ['md', 'markdown']),
        ];
        $tooltipFiles = $this->findFilesByExtensions($tooltipsPath, ['yaml', 'yml']);

        $validatedTooltipFragments = 0;

        foreach ($markdownFiles as $filePath) {
            try {
                $this->validateMarkdownString(File::get($filePath), $this->toRepoRelativePath($filePath));
            } catch (DocumentationValidationException $exception) {
                $errors = [...$errors, ...$exception->errors()];
                if ($failFast) {
                    break;
                }
            }
        }

        if (! $failFast || $errors === []) {
            foreach ($tooltipFiles as $filePath) {
                try {
                    $validated = $this->validateTooltipYamlString(File::get($filePath), $this->toRepoRelativePath($filePath));
                    $validatedTooltipFragments += count($validated);
                } catch (DocumentationValidationException $exception) {
                    $errors = [...$errors, ...$exception->errors()];
                    if ($failFast) {
                        break;
                    }
                }
            }
        }

        if ($errors !== []) {
            throw DocumentationValidationException::fromErrors($errors, 'Repository docs contract validation failed');
        }

        return [
            'markdown_files' => count($markdownFiles),
            'tooltip_files' => count($tooltipFiles),
            'tooltip_fragments' => $validatedTooltipFragments,
        ];
    }

    /**
     * @param  array<int, string>  $extensions
     * @return array<int, string>
     */
    private function findFilesByExtensions(string $directory, array $extensions): array
    {
        $normalizedExtensions = array_map(static fn (string $extension): string => strtolower(ltrim($extension, '.')), $extensions);

        $files = [];
        foreach (File::allFiles($directory) as $file) {
            $extension = strtolower($file->getExtension());
            if (in_array($extension, $normalizedExtensions, true)) {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    /**
     * @param  array<int, string>  $errors
     */
    private function assertDirectory(string $path, string $label, array &$errors): void
    {
        if ($path === '' || ! File::isDirectory($path)) {
            $errors[] = "Configured {$label} directory does not exist: {$path}.";
        }
    }

    private function toRepoRelativePath(string $absolutePath): string
    {
        return ltrim(str_replace(base_path(), '', $absolutePath), DIRECTORY_SEPARATOR);
    }
}
