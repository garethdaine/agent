<?php

namespace App\Services\Runtime\Adapters;

use App\DTOs\Runtime\RuntimeContext;
use App\DTOs\Runtime\ToolResult;
use App\Enums\Runtime\RuntimeMode;
use App\Services\Security\ExfiltrationDetector;
use Illuminate\Support\Facades\Http;

class WebToolAdapter extends AbstractToolAdapter
{
    private ?ExfiltrationDetector $exfiltrationDetector = null;

    public function setExfiltrationDetector(ExfiltrationDetector $detector): void
    {
        $this->exfiltrationDetector = $detector;
    }
    public function name(): string
    {
        return 'web';
    }

    public function schema(): array
    {
        return [
            'operations' => ['fetch'],
            'parameters' => [
                'operation' => ['type' => 'string', 'required' => true],
                'url' => ['type' => 'string', 'required' => true],
                'method' => ['type' => 'string', 'optional' => true, 'default' => 'GET'],
                'headers' => ['type' => 'object', 'optional' => true],
                'body' => ['type' => 'string', 'optional' => true],
            ],
        ];
    }

    public function authorize(RuntimeContext $context, array $args): bool
    {
        $operation = $args['operation'] ?? 'fetch';
        if ($operation !== 'fetch') {
            return false;
        }

        if ($context->mode === RuntimeMode::Safe) {
            return false;
        }

        return parent::authorize($context, $args);
    }

    protected function getRequiredCapability(array $args): string
    {
        return 'query';
    }

    public function execute(RuntimeContext $context, array $args): ToolResult
    {
        $startTime = hrtime(true);
        $operation = $args['operation'] ?? 'fetch';

        if ($operation !== 'fetch') {
            return ToolResult::failure("Unknown operation: {$operation}", $this->duration($startTime));
        }

        $url = $args['url'] ?? '';
        if ($url === '') {
            return ToolResult::failure('url is required for fetch', $this->duration($startTime));
        }

        if (! $this->isUrlAllowed($url)) {
            return ToolResult::failure(
                'URL host is not in allowed list (RUNTIME_WEB_ALLOWED_HOSTS)',
                $this->duration($startTime)
            );
        }

        $method = strtoupper($args['method'] ?? 'GET');
        $allowedMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD'];
        if (! in_array($method, $allowedMethods, true)) {
            return ToolResult::failure("Method not allowed: {$method}", $this->duration($startTime));
        }

        $headers = is_array($args['headers'] ?? null) ? $args['headers'] : [];
        $body = isset($args['body']) && is_string($args['body']) ? $args['body'] : null;

        if ($this->exfiltrationDetector !== null && in_array($method, ['POST', 'PUT', 'PATCH'], true) && $body !== null) {
            $inspection = $this->exfiltrationDetector->inspect($method, $url, $body, $context->session->id ?? null);
            if ($inspection->blocked) {
                return ToolResult::failure(
                    'Request blocked: potential data exfiltration detected ('.implode(', ', array_map(fn ($p) => $p->value, $inspection->matchedPatterns)).')',
                    $this->duration($startTime)
                );
            }
        }
        $timeout = config('runtime.web.timeout_seconds', 30);

        try {
            $request = Http::timeout($timeout)->withHeaders($headers);

            $response = match ($method) {
                'GET' => $request->get($url),
                'HEAD' => $request->head($url),
                'POST' => $request->withBody($body ?? '', 'application/octet-stream')->post($url),
                'PUT' => $request->withBody($body ?? '', 'application/octet-stream')->put($url),
                'PATCH' => $request->withBody($body ?? '', 'application/octet-stream')->patch($url),
                'DELETE' => $request->delete($url),
                default => throw new \InvalidArgumentException("Unsupported method: {$method}"),
            };

            $status = $response->status();
            $responseHeaders = $response->headers();
            $responseBody = $response->body();

            return ToolResult::success([
                'status_code' => $status,
                'headers' => $responseHeaders,
                'body' => $responseBody,
                'successful' => $response->successful(),
            ], $this->duration($startTime));
        } catch (\Throwable $e) {
            return ToolResult::failure(
                'HTTP request failed: '.$e->getMessage(),
                $this->duration($startTime)
            );
        }
    }

    private function isUrlAllowed(string $url): bool
    {
        $parsed = parse_url($url);
        if (! isset($parsed['host']) || ! is_string($parsed['host'])) {
            return false;
        }

        $scheme = $parsed['scheme'] ?? '';
        if (! in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        $allowedHosts = config('runtime.web.allowed_hosts', []);
        if (! is_array($allowedHosts)) {
            return false;
        }
        if ($allowedHosts === []) {
            return false;
        }

        $host = strtolower($parsed['host']);

        return in_array($host, $allowedHosts, true);
    }

    private function duration(int $startTime): int
    {
        return (int) ((hrtime(true) - $startTime) / 1_000_000);
    }
}
