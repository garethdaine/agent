<?php

declare(strict_types=1);

namespace App\Services\Skills\Validation\Analyzers;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class ExfiltrationAnalyzer
{
    private const URL_CONSTRUCTION_PATTERNS = [
        '/\$\w+\s*\.\s*["\']https?:/',
        '/["\']https?:\/\/["\']\s*\.\s*\$/',
        '/url\s*=\s*["\'].*\$/',
        '/f["\']https?:\/\/\{/',
        '/`https?:\/\/\$\{/',
    ];

    private const NETWORK_CALL_PATTERNS = [
        '/\bfetch\s*\(/',
        '/\bcurl_exec\s*\(/',
        '/\bcurl_init\s*\(/',
        '/\bwget\b/',
        '/\brequests\.(get|post|put|delete|patch)\s*\(/',
        '/\burllib\.request/',
        '/\bhttp\.client/',
        '/\bfile_get_contents\s*\(\s*["\']https?:/',
        '/\bfopen\s*\(\s*["\']https?:/',
    ];

    private const WEBHOOK_PATTERNS = [
        '/webhook\.site/i',
        '/requestbin/i',
        '/ngrok\.io/i',
        '/burpcollaborator/i',
        '/oastify\.com/i',
        '/pipedream/i',
        '/hookbin/i',
    ];

    private const ENCODE_TRANSMIT_PATTERNS = [
        '/base64_encode.*(?:curl|fetch|http|request|post)/is',
        '/btoa.*(?:fetch|XMLHttpRequest|axios)/is',
        '/encode.*(?:send|transmit|post|upload)/is',
    ];

    public function analyze(string $content, string $extractedPath): float
    {
        $findingsCount = 0;
        $allContent = $content;

        // Also scan scripts directory if it exists
        $scriptsPath = $extractedPath.'/scripts';
        if (is_dir($scriptsPath)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($scriptsPath, RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $scriptContent = file_get_contents($file->getPathname());
                    if ($scriptContent !== false) {
                        $allContent .= "\n".$scriptContent;
                    }
                }
            }
        }

        foreach (self::URL_CONSTRUCTION_PATTERNS as $pattern) {
            if (@preg_match($pattern, $allContent)) {
                $findingsCount++;
            }
        }

        foreach (self::NETWORK_CALL_PATTERNS as $pattern) {
            if (@preg_match($pattern, $allContent)) {
                $findingsCount++;
            }
        }

        foreach (self::WEBHOOK_PATTERNS as $pattern) {
            if (@preg_match($pattern, $allContent)) {
                $findingsCount++;
            }
        }

        foreach (self::ENCODE_TRANSMIT_PATTERNS as $pattern) {
            if (@preg_match($pattern, $allContent)) {
                $findingsCount++;
            }
        }

        return min(1.0, $findingsCount * 0.3);
    }
}
