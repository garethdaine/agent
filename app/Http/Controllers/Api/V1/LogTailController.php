<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class LogTailController extends Controller
{
    private const ALLOWED_CHANNELS = ['laravel', 'messenger', 'runtime', 'docs'];

    private const MAX_LINES = 200;

    public function index(Request $request): JsonResponse
    {
        $channel = $request->input('channel', 'laravel');
        $lines = min((int) $request->input('lines', 100), self::MAX_LINES);
        $level = $request->input('level');
        $search = $request->input('search');

        if (! in_array($channel, self::ALLOWED_CHANNELS, true)) {
            return response()->json([
                'error' => 'Invalid channel. Allowed: '.implode(', ', self::ALLOWED_CHANNELS),
            ], 422);
        }

        $path = $this->resolveLogPath($channel);

        if (! $path || ! File::exists($path)) {
            return response()->json([
                'data' => [
                    'channel' => $channel,
                    'file' => basename($path ?? ''),
                    'lines' => [],
                    'total' => 0,
                ],
            ]);
        }

        $rawLines = $this->tailFile($path, $lines * 3);

        $parsed = $this->parseLines($rawLines, $channel);

        if ($level) {
            $parsed = array_values(array_filter(
                $parsed,
                fn (array $entry) => strcasecmp($entry['level'], $level) === 0
            ));
        }

        if ($search) {
            $needle = mb_strtolower($search);
            $parsed = array_values(array_filter(
                $parsed,
                fn (array $entry) => str_contains(mb_strtolower($entry['message']), $needle)
            ));
        }

        $parsed = array_slice($parsed, -$lines);

        return response()->json([
            'data' => [
                'channel' => $channel,
                'file' => basename($path),
                'lines' => $parsed,
                'total' => count($parsed),
                'channels' => self::ALLOWED_CHANNELS,
            ],
        ]);
    }

    public function export(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $channel = $request->input('channel', 'laravel');

        if (! in_array($channel, self::ALLOWED_CHANNELS, true)) {
            abort(422, 'Invalid channel.');
        }

        $path = $this->resolveLogPath($channel);

        if (! $path || ! File::exists($path)) {
            abort(404, 'Log file not found.');
        }

        $filename = $channel.'-'.now()->format('Y-m-d-His').'.log';

        return response()->streamDownload(function () use ($path): void {
            readfile($path);
        }, $filename, [
            'Content-Type' => 'text/plain',
        ]);
    }

    private function resolveLogPath(string $channel): ?string
    {
        $baseName = match ($channel) {
            'laravel' => 'laravel',
            'messenger' => 'messenger',
            'runtime' => 'runtime',
            'docs' => 'docs',
            default => null,
        };

        if (! $baseName) {
            return null;
        }

        $dailyPath = storage_path('logs/'.$baseName.'-'.now()->format('Y-m-d').'.log');
        if (File::exists($dailyPath)) {
            return $dailyPath;
        }

        $singlePath = storage_path('logs/'.$baseName.'.log');
        if (File::exists($singlePath)) {
            return $singlePath;
        }

        return $dailyPath;
    }

    private function tailFile(string $path, int $lineCount): array
    {
        $file = new \SplFileObject($path, 'r');
        $file->seek(PHP_INT_MAX);
        $totalLines = $file->key();

        $start = max(0, $totalLines - $lineCount);
        $lines = [];

        $file->seek($start);
        while (! $file->eof()) {
            $line = $file->current();
            if (trim($line) !== '') {
                $lines[] = rtrim($line);
            }
            $file->next();
        }

        return $lines;
    }

    private function parseLines(array $rawLines, string $channel): array
    {
        $entries = [];

        if ($channel === 'runtime') {
            foreach ($rawLines as $line) {
                $decoded = json_decode($line, true);
                if ($decoded) {
                    $entries[] = [
                        'timestamp' => $decoded['datetime'] ?? ($decoded['timestamp'] ?? ''),
                        'level' => $decoded['level_name'] ?? ($decoded['level'] ?? 'info'),
                        'message' => $decoded['message'] ?? $line,
                        'context' => $decoded['context'] ?? [],
                    ];
                } else {
                    $entries[] = $this->parseLaravelLine($line);
                }
            }

            return $entries;
        }

        foreach ($rawLines as $line) {
            if (preg_match('/^\[(\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2})/', $line)) {
                $entries[] = $this->parseLaravelLine($line);
            } elseif (! empty($entries)) {
                $entries[count($entries) - 1]['message'] .= "\n".$line;
            }
        }

        return $entries;
    }

    private function parseLaravelLine(string $line): array
    {
        if (preg_match('/^\[(\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}[^\]]*)\]\s+\w+\.(\w+):\s+(.*)$/s', $line, $m)) {
            return [
                'timestamp' => $m[1],
                'level' => strtolower($m[2]),
                'message' => $m[3],
                'context' => [],
            ];
        }

        return [
            'timestamp' => '',
            'level' => 'info',
            'message' => $line,
            'context' => [],
        ];
    }
}
