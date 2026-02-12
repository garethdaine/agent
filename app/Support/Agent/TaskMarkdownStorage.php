<?php

namespace App\Support\Agent;

use Illuminate\Support\Str;
use RuntimeException;

class TaskMarkdownStorage
{
    public function persistInlineContent(string $content, int $userId): string
    {
        $trimmed = trim($content);

        if ($trimmed === '') {
            throw new RuntimeException('Inline markdown content cannot be empty.');
        }

        $base = $this->resolvePrimaryTaskBase();
        $dir = rtrim($base, '/').'/inline/'.date('Y/m/d');

        if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new RuntimeException('Unable to create inline task markdown directory.');
        }

        $filename = sprintf('task-u%d-%s.md', $userId, Str::uuid()->toString());
        $path = $dir.'/'.$filename;

        $written = @file_put_contents($path, $content);

        if ($written === false) {
            throw new RuntimeException('Unable to persist inline markdown content to task file.');
        }

        $resolved = realpath($path);

        if (! is_string($resolved) || $resolved === '') {
            throw new RuntimeException('Persisted markdown path could not be resolved.');
        }

        return $resolved;
    }

    private function resolvePrimaryTaskBase(): string
    {
        $bases = (array) config('agent.allowed_task_markdown_bases', []);

        foreach ($bases as $base) {
            if (is_string($base) && $base !== '') {
                return $base;
            }
        }

        throw new RuntimeException('No allowed task markdown base is configured.');
    }
}
