<?php

namespace App\Support\Agent;

class CommandPolicy
{
    /**
     * @return array{normalized_template:?string,resolved_executable_path:?string,errors:array<string,string>}
     */
    public function validateForSave(string $runnerType, ?string $template): array
    {
        $errors = [];

        $normalizedTemplate = $this->normalizeTemplate($runnerType, $template);

        if ($normalizedTemplate === null) {
            $errors['command_template'] = 'A command_template is required for the selected runner_type.';
        }

        if ($normalizedTemplate !== null) {
            $templateErrors = $this->validateTemplateContent($runnerType, $normalizedTemplate);
            $errors = array_merge($errors, $templateErrors);
        }

        $resolvedExecutablePath = $this->resolveExecutable($runnerType);

        if ($resolvedExecutablePath === null) {
            $errors['runner_type'] = 'The executable for the selected runner_type could not be resolved or is not allowlisted.';
        }

        return [
            'normalized_template' => $normalizedTemplate,
            'resolved_executable_path' => $resolvedExecutablePath,
            'errors' => $errors,
        ];
    }

    private function normalizeTemplate(string $runnerType, ?string $template): ?string
    {
        $template = is_string($template) ? trim($template) : null;

        if ($template !== null && $template !== '') {
            return $template;
        }

        if ($runnerType === 'custom') {
            return null;
        }

        $defaults = config('agent.default_templates', []);

        return $defaults[$runnerType] ?? null;
    }

    /**
     * @return array<string, string>
     */
    private function validateTemplateContent(string $runnerType, string $template): array
    {
        $errors = [];

        if (preg_match('/[\r\n]/', $template)) {
            $errors['command_template'] = 'The command_template must be a single line.';

            return $errors;
        }

        if (preg_match('/(\|\||&&|[|;<>`]|\$\(|\$\{)/', $template)) {
            $errors['command_template'] = 'The command_template contains prohibited shell operators.';

            return $errors;
        }

        preg_match_all('/\{\{[^}]+\}\}/', $template, $placeholderMatches);
        $placeholders = $placeholderMatches[0] ?? [];
        $allowedPlaceholders = config('agent.allowed_placeholders', []);

        foreach ($placeholders as $placeholder) {
            if (! in_array($placeholder, $allowedPlaceholders, true)) {
                $errors['command_template'] = sprintf('Unknown placeholder detected: %s', $placeholder);

                return $errors;
            }
        }

        $tokens = preg_split('/\s+/', trim($template)) ?: [];

        foreach ($tokens as $token) {
            if ((str_contains($token, '{{') || str_contains($token, '}}')) && ! in_array($token, $allowedPlaceholders, true)) {
                $errors['command_template'] = 'Placeholders must be whole tokens.';

                return $errors;
            }
        }

        if ($runnerType === 'custom') {
            $customExecutable = (config('agent.runner_executables', []))['custom'] ?? null;

            if (! is_string($customExecutable) || ! str_starts_with($template, $customExecutable)) {
                $errors['command_template'] = 'Custom runner templates must start with the configured custom executable path.';

                return $errors;
            }

            if (! in_array('{{task_markdown_path}}', $tokens, true)) {
                $errors['command_template'] = 'Custom runner templates must include {{task_markdown_path}}.';
            }
        }

        return $errors;
    }

    private function resolveExecutable(string $runnerType): ?string
    {
        $runnerExecutables = config('agent.runner_executables', []);
        $configuredPath = $runnerExecutables[$runnerType] ?? null;

        if (! is_string($configuredPath) || $configuredPath === '') {
            return null;
        }

        $resolved = realpath($configuredPath);

        if ($resolved === false || ! is_file($resolved) || ! is_executable($resolved)) {
            return null;
        }

        $allowed = [];

        foreach ($runnerExecutables as $path) {
            if (is_string($path) && $path !== '') {
                $candidate = realpath($path);

                if ($candidate !== false) {
                    $allowed[] = $candidate;
                }
            }
        }

        if (! in_array($resolved, $allowed, true)) {
            return null;
        }

        return $resolved;
    }
}
