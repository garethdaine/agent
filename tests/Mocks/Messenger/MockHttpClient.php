<?php

declare(strict_types=1);

namespace Tests\Mocks\Messenger;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Mock HTTP client for recording and replaying HTTP requests.
 *
 * This class provides a fluent API for setting up expected HTTP responses
 * and recording actual requests for later assertion.
 */
class MockHttpClient
{
    /**
     * @var array<string, array{status: int, body: array<string, mixed>, headers: array<string, string>}>
     */
    private array $responses = [];

    /**
     * @var array<int, array{method: string, url: string, body: array<string, mixed>, headers: array<string, string>}>
     */
    private array $recordedRequests = [];

    private bool $recording = false;

    /**
     * Set up a canned response for a URL pattern.
     *
     * @param  array<string, mixed>  $body
     * @param  array<string, string>  $headers
     */
    public function respondTo(
        string $urlPattern,
        array $body,
        int $status = 200,
        array $headers = []
    ): self {
        $this->responses[$urlPattern] = [
            'status' => $status,
            'body' => $body,
            'headers' => $headers,
        ];

        return $this;
    }

    /**
     * Set up Slack API responses.
     */
    public function fakeSlackApi(): self
    {
        return $this
            ->respondTo('slack.com/api/chat.postMessage', [
                'ok' => true,
                'channel' => 'C_MOCK',
                'ts' => time().'.123456',
                'message' => ['text' => 'Mock message'],
            ])
            ->respondTo('slack.com/api/chat.update', [
                'ok' => true,
                'channel' => 'C_MOCK',
                'ts' => time().'.123456',
            ])
            ->respondTo('slack.com/api/files.upload', [
                'ok' => true,
                'file' => ['id' => 'F_MOCK'],
            ]);
    }

    /**
     * Set up Telegram Bot API responses.
     */
    public function fakeTelegramApi(): self
    {
        return $this
            ->respondTo('api.telegram.org/*/sendMessage', [
                'ok' => true,
                'result' => [
                    'message_id' => rand(1000, 9999),
                    'chat' => ['id' => 123456789],
                ],
            ])
            ->respondTo('api.telegram.org/*/editMessageText', [
                'ok' => true,
                'result' => [
                    'message_id' => rand(1000, 9999),
                ],
            ])
            ->respondTo('api.telegram.org/*/sendDocument', [
                'ok' => true,
                'result' => [
                    'message_id' => rand(1000, 9999),
                    'document' => ['file_id' => 'F_MOCK'],
                ],
            ]);
    }

    /**
     * Enable request recording.
     */
    public function startRecording(): self
    {
        $this->recording = true;
        $this->recordedRequests = [];

        return $this;
    }

    /**
     * Stop request recording.
     */
    public function stopRecording(): self
    {
        $this->recording = false;

        return $this;
    }

    /**
     * Get all recorded requests.
     *
     * @return array<int, array{method: string, url: string, body: array<string, mixed>, headers: array<string, string>}>
     */
    public function getRecordedRequests(): array
    {
        return $this->recordedRequests;
    }

    /**
     * Assert that a request was made to the given URL.
     */
    public function assertRequested(string $urlPattern): void
    {
        foreach ($this->recordedRequests as $request) {
            if (str_contains($request['url'], $urlPattern)) {
                return;
            }
        }

        throw new \PHPUnit\Framework\AssertionFailedError(
            "No request was made to URL matching: {$urlPattern}"
        );
    }

    /**
     * Assert that no requests were made.
     */
    public function assertNoRequests(): void
    {
        if (count($this->recordedRequests) > 0) {
            throw new \PHPUnit\Framework\AssertionFailedError(
                'Expected no requests, but '.count($this->recordedRequests).' requests were made.'
            );
        }
    }

    /**
     * Apply the mock responses to Laravel's HTTP facade.
     */
    public function apply(): void
    {
        $fakeResponses = [];

        foreach ($this->responses as $pattern => $config) {
            $fakeResponses[$pattern] = Http::response(
                $config['body'],
                $config['status'],
                $config['headers']
            );
        }

        Http::fake($fakeResponses);

        // If recording is enabled, we need to wrap the fake to capture requests
        if ($this->recording) {
            Http::fake(function ($request) {
                $this->recordedRequests[] = [
                    'method' => $request->method(),
                    'url' => $request->url(),
                    'body' => $request->data(),
                    'headers' => $request->headers(),
                ];

                // Find matching response
                foreach ($this->responses as $pattern => $config) {
                    if ($this->matchesPattern($request->url(), $pattern)) {
                        return Http::response(
                            $config['body'],
                            $config['status'],
                            $config['headers']
                        );
                    }
                }

                // Default response
                return Http::response(['ok' => false, 'error' => 'no_match'], 404);
            });
        }
    }

    /**
     * Reset all state.
     */
    public function reset(): void
    {
        $this->responses = [];
        $this->recordedRequests = [];
        $this->recording = false;
    }

    /**
     * Check if a URL matches a pattern (supports * wildcards).
     */
    private function matchesPattern(string $url, string $pattern): bool
    {
        // Convert pattern wildcards to regex
        $regex = str_replace(['*', '/'], ['.*', '\/'], $pattern);

        return (bool) preg_match("/{$regex}/", $url);
    }
}
