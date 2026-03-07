<?php

declare(strict_types=1);

namespace App\Services\Skills\Validation;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class CodeSafetyValidator
{
    private const BINARY_HEADERS = [
        "\x7FELF",     // ELF
        "MZ",          // PE/COFF (Windows)
        "\xFE\xED\xFA", // Mach-O
        "\xCA\xFE\xBA\xBE", // Java class / Mach-O fat
    ];

    private const EXTENSION_TO_LANGUAGE = [
        'py' => 'python',
        'sh' => 'bash',
        'bash' => 'bash',
        'js' => 'javascript',
        'mjs' => 'javascript',
        'php' => 'php',
    ];

    private const DANGEROUS_PHP_TOKENS = [
        T_EVAL,
    ];

    private const DANGEROUS_FUNCTION_NAMES = [
        'eval', 'exec', 'system', 'shell_exec', 'passthru',
        'proc_open', 'popen', 'pcntl_exec',
    ];

    private const NETWORK_PATTERNS = [
        '/\brequests\.(get|post|put|delete|patch)\s*\(/i',
        '/\burllib\.request/i',
        '/\bhttp\.client/i',
        '/\bcurl_exec\s*\(/i',
        '/\bcurl_init\s*\(/i',
        '/\bfetch\s*\(/i',
        '/\bXMLHttpRequest/i',
        '/\baxios\b/i',
        '/\bwget\b/i',
        '/\bsocket_create\s*\(/i',
        '/\bfsockopen\s*\(/i',
        '/\bstream_socket_client\s*\(/i',
    ];

    private const FILESYSTEM_ESCAPE_PATTERNS = [
        '/\.\.\//',
        '/\.\.\\\\/',
    ];

    private const PROCESS_SPAWN_PATTERNS = [
        '/\bsubprocess\.(run|call|Popen|check_output)\s*\(/i',
        '/\bos\.system\s*\(/i',
        '/\bos\.popen\s*\(/i',
        '/\bchild_process/i',
        '/\bspawn\s*\(/i',
    ];

    public function validate(string $extractedPath): StageResult
    {
        $scriptsPath = $extractedPath.'/scripts';

        if (! is_dir($scriptsPath)) {
            return StageResult::pass('code_safety', 1);
        }

        $failures = [];
        $checks = 0;
        $allowlist = config('agent.skills.validation.script_language_allowlist', []);

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($scriptsPath, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $filePath = $file->getPathname();
            $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            $relativePath = str_replace($extractedPath.'/', '', $filePath);

            // Check file extension is in allowlist
            $checks++;
            $language = self::EXTENSION_TO_LANGUAGE[$extension] ?? null;
            if ($language === null || ! in_array($language, $allowlist, true)) {
                $failures[] = [
                    'check' => 'language_allowlist',
                    'severity' => StageResult::SEVERITY_BLOCK,
                    'message' => "Disallowed script language (.{$extension}): {$relativePath}",
                ];

                continue;
            }

            // Check for binary content
            $checks++;
            if ($this->isBinary($filePath)) {
                $failures[] = [
                    'check' => 'binary_check',
                    'severity' => StageResult::SEVERITY_BLOCK,
                    'message' => "Binary executable detected: {$relativePath}",
                ];

                continue;
            }

            $content = file_get_contents($filePath);
            if ($content === false) {
                continue;
            }

            // Dangerous function check
            $checks++;
            $dangerousFunctions = $this->scanDangerousFunctions($content, $language);
            if (! empty($dangerousFunctions)) {
                $failures[] = [
                    'check' => 'dangerous_functions',
                    'severity' => StageResult::SEVERITY_BLOCK,
                    'message' => "Dangerous functions in {$relativePath}: ".implode(', ', $dangerousFunctions),
                ];
            }

            // Network call check
            $checks++;
            if ($this->hasNetworkCalls($content)) {
                $failures[] = [
                    'check' => 'network_calls',
                    'severity' => StageResult::SEVERITY_BLOCK,
                    'message' => "Undeclared network calls in {$relativePath}",
                ];
            }

            // Filesystem escape check
            $checks++;
            if ($this->hasFilesystemEscape($content)) {
                $failures[] = [
                    'check' => 'filesystem_escape',
                    'severity' => StageResult::SEVERITY_BLOCK,
                    'message' => "Filesystem escape pattern in {$relativePath}",
                ];
            }

            // Process spawning check
            $checks++;
            if ($this->hasProcessSpawning($content)) {
                $failures[] = [
                    'check' => 'process_spawning',
                    'severity' => StageResult::SEVERITY_BLOCK,
                    'message' => "Process spawning detected in {$relativePath}",
                ];
            }
        }

        if (! empty($failures)) {
            return StageResult::fail('code_safety', $failures, max(1, $checks));
        }

        return StageResult::pass('code_safety', max(1, $checks));
    }

    private function isBinary(string $filePath): bool
    {
        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            return false;
        }

        $header = fread($handle, 4);
        fclose($handle);

        if ($header === false || strlen($header) < 2) {
            return false;
        }

        foreach (self::BINARY_HEADERS as $binaryHeader) {
            if (str_starts_with($header, $binaryHeader)) {
                return true;
            }
        }

        return false;
    }

    private function scanDangerousFunctions(string $content, string $language): array
    {
        $found = [];

        if ($language === 'php') {
            $tokens = @token_get_all('<?php '.$content);
            foreach ($tokens as $token) {
                if (is_array($token)) {
                    if (in_array($token[0], self::DANGEROUS_PHP_TOKENS, true)) {
                        $found[] = token_name($token[0]);
                    }
                    if ($token[0] === T_STRING && in_array(strtolower($token[1]), self::DANGEROUS_FUNCTION_NAMES, true)) {
                        $found[] = $token[1];
                    }
                }
            }
        } else {
            $configDangerous = config('agent.skills.validation.dangerous_functions', []);
            foreach ($configDangerous as $func) {
                if (preg_match('/\b'.preg_quote($func, '/').'\s*\(/i', $content)) {
                    $found[] = $func;
                }
            }
        }

        return array_unique($found);
    }

    private function hasNetworkCalls(string $content): bool
    {
        foreach (self::NETWORK_PATTERNS as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }

        return false;
    }

    private function hasFilesystemEscape(string $content): bool
    {
        foreach (self::FILESYSTEM_ESCAPE_PATTERNS as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }

        return false;
    }

    private function hasProcessSpawning(string $content): bool
    {
        foreach (self::PROCESS_SPAWN_PATTERNS as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }

        return false;
    }
}
