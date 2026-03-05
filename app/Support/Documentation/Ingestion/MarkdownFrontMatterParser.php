<?php

declare(strict_types=1);

namespace App\Support\Documentation\Ingestion;

use App\Support\Documentation\Schemas\DocumentationValidationException;
use App\Support\Documentation\Schemas\MarkdownFrontMatterSchema;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class MarkdownFrontMatterParser
{
    public function __construct(
        private readonly MarkdownFrontMatterSchema $schema
    ) {}

    /**
     * @return array{front_matter: array<string, mixed>, body: string}
     */
    public function parse(string $markdown, string $sourcePath): array
    {
        if (! preg_match('/\A---\R(?P<yaml>.*?)\R---\R?(?P<body>.*)\z/s', $markdown, $matches)) {
            throw DocumentationValidationException::fromErrors(
                ['Markdown file must begin with YAML front matter delimited by --- markers.'],
                "Markdown front matter validation failed for {$sourcePath}"
            );
        }

        try {
            $parsed = Yaml::parse((string) $matches['yaml'], Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE);
        } catch (ParseException $exception) {
            throw DocumentationValidationException::fromErrors(
                ['Malformed YAML front matter: '.$exception->getMessage()],
                "Markdown front matter validation failed for {$sourcePath}"
            );
        }

        if (! is_array($parsed) || array_is_list($parsed)) {
            throw DocumentationValidationException::fromErrors(
                ['YAML front matter must be a mapping/object.'],
                "Markdown front matter validation failed for {$sourcePath}"
            );
        }

        /** @var array<string, mixed> $frontMatter */
        $frontMatter = $parsed;
        $this->schema->validate($frontMatter, $sourcePath);

        $body = ltrim((string) $matches['body']);
        $body = $this->stripLeadingTitleBlock($body, (string) ($frontMatter['title'] ?? ''));

        return [
            'front_matter' => $frontMatter,
            'body' => $body,
        ];
    }

    /**
     * Strip the leading `# Title` heading and the description paragraph
     * that immediately follows it when they duplicate the frontmatter
     * title/summary already rendered by the UI chrome.
     */
    private function stripLeadingTitleBlock(string $body, string $frontMatterTitle): string
    {
        if ($frontMatterTitle === '' || $body === '') {
            return $body;
        }

        $pattern = '/\A#\s+'.preg_quote($frontMatterTitle, '/').'\s*\R+/i';

        $stripped = preg_replace($pattern, '', $body, 1);

        return is_string($stripped) ? ltrim($stripped) : $body;
    }
}
