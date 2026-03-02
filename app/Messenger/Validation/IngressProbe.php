<?php

declare(strict_types=1);

namespace App\Messenger\Validation;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Service for validating webhook ingress endpoints.
 *
 * Performs reachability checks, TLS validation, and provider-specific
 * verification challenges for webhook URLs during connector setup.
 *
 * Provider-specific verification:
 * - Slack: URL verification challenge (echo challenge parameter)
 * - Telegram: setWebhook API confirmation
 * - Discord: PING/PONG interaction endpoint validation
 * - WhatsApp: verify_token configuration check (verification happens on Meta's side)
 */
class IngressProbe
{
    private const HTTP_TIMEOUT = 10;

    private const SLACK_CHALLENGE_TIMEOUT = 3;

    private const TLS_WARNING_DAYS = 7;

    /**
     * Run full probe for a webhook URL with all applicable checks.
     *
     * @param  string  $url  The webhook URL to probe
     * @param  string  $provider  Provider name (slack, telegram, discord, whatsapp)
     * @param  array<string, mixed>  $credentials  Provider credentials for verification
     * @param  array<string, mixed>  $options  Additional options (skip_tls, etc.)
     */
    public function probe(string $url, string $provider, array $credentials, array $options = []): ProbeResult
    {
        // Skip probing if URL is empty
        if (empty($url)) {
            return ProbeResult::failure(
                'Webhook URL not provided',
                'A publicly accessible HTTPS URL is required for webhook mode.'
            );
        }

        // Basic reachability check
        $reachability = $this->checkReachability($url);
        if (! $reachability->success) {
            return $reachability;
        }

        // TLS validation (skip in test environment if requested)
        if (! ($options['skip_tls'] ?? false)) {
            $tls = $this->checkTlsFromUrl($url);
            if (! $tls->success) {
                return $tls;
            }

            // Collect warnings from TLS check
            $warning = $tls->warning;
        } else {
            $warning = null;
        }

        // Provider-specific verification
        $providerResult = match ($provider) {
            'slack' => $this->verifySlack($url),
            'telegram' => $this->verifyTelegram($url, $credentials),
            'discord' => $this->verifyDiscord($url, $credentials),
            'whatsapp' => $this->verifyWhatsApp($credentials),
            default => ProbeResult::success(),
        };

        if (! $providerResult->success) {
            return $providerResult;
        }

        // Combine results - propagate warnings
        if ($warning !== null) {
            return ProbeResult::successWithWarning($warning, $providerResult->metadata);
        }

        return ProbeResult::success($providerResult->metadata);
    }

    /**
     * Check basic HTTP reachability of the webhook URL.
     */
    public function checkReachability(string $url): ProbeResult
    {
        try {
            $response = Http::timeout(self::HTTP_TIMEOUT)
                ->withOptions(['verify' => false]) // We check TLS separately
                ->head($url);

            // Consider 2xx and 404 as reachable (404 = path not configured yet)
            $status = $response->status();
            if ($status >= 200 && $status < 300) {
                return ProbeResult::success();
            }

            if ($status === 404) {
                return ProbeResult::success();
            }

            if ($status >= 500) {
                return ProbeResult::failure(
                    sprintf('Server returned HTTP %d', $status),
                    sprintf(
                        'The webhook endpoint returned HTTP %d. Check your server logs for errors and ensure the application is running.',
                        $status
                    )
                );
            }

            if ($status >= 400) {
                return ProbeResult::failure(
                    sprintf('Server returned HTTP %d', $status),
                    sprintf(
                        'The webhook endpoint returned HTTP %d. This may be expected if the endpoint requires POST requests.',
                        $status
                    )
                );
            }

            return ProbeResult::success();
        } catch (\Throwable $e) {
            return ProbeResult::failure(
                'Webhook URL not reachable',
                sprintf(
                    'Could not connect to %s. Check firewall rules and ensure the URL is publicly accessible. Error: %s',
                    parse_url($url, PHP_URL_HOST) ?? $url,
                    $e->getMessage()
                )
            );
        }
    }

    /**
     * Check TLS certificate validity from actual URL connection.
     */
    public function checkTlsFromUrl(string $url): ProbeResult
    {
        $host = parse_url($url, PHP_URL_HOST);
        $port = parse_url($url, PHP_URL_PORT) ?? 443;

        if (! $host) {
            return ProbeResult::failure(
                'Invalid URL',
                'Could not parse hostname from the webhook URL.'
            );
        }

        try {
            $context = stream_context_create([
                'ssl' => [
                    'capture_peer_cert' => true,
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                ],
            ]);

            $client = @stream_socket_client(
                sprintf('ssl://%s:%d', $host, $port),
                $errno,
                $errstr,
                self::HTTP_TIMEOUT,
                STREAM_CLIENT_CONNECT,
                $context
            );

            if (! $client) {
                return ProbeResult::failure(
                    'TLS connection failed',
                    sprintf(
                        'Could not establish TLS connection to %s. Error: %s. Ensure HTTPS is properly configured on your server.',
                        $host,
                        $errstr ?: 'Unknown error'
                    )
                );
            }

            $params = stream_context_get_params($client);
            $cert = $params['options']['ssl']['peer_certificate'] ?? null;

            if (! $cert) {
                fclose($client);

                return ProbeResult::failure(
                    'No TLS certificate',
                    sprintf(
                        'Could not retrieve TLS certificate from %s. Ensure HTTPS is properly configured.',
                        $host
                    )
                );
            }

            $certInfo = openssl_x509_parse($cert);
            fclose($client);

            if (! $certInfo) {
                return ProbeResult::failure(
                    'Invalid TLS certificate',
                    'Could not parse the TLS certificate. Ensure a valid certificate is installed.'
                );
            }

            return $this->checkTls($url, [
                'valid_from' => $certInfo['validFrom_time_t'] ?? 0,
                'valid_to' => $certInfo['validTo_time_t'] ?? 0,
            ]);
        } catch (\Throwable $e) {
            return ProbeResult::failure(
                'TLS validation error',
                sprintf('Could not validate TLS certificate: %s', $e->getMessage())
            );
        }
    }

    /**
     * Check TLS certificate validity using provided certificate info.
     *
     * @param  array{valid_from: int, valid_to: int}  $certInfo
     */
    public function checkTls(string $url, array $certInfo): ProbeResult
    {
        $now = time();
        $validFrom = $certInfo['valid_from'];
        $validTo = $certInfo['valid_to'];

        if ($now < $validFrom) {
            return ProbeResult::failure(
                'TLS certificate not yet valid',
                sprintf(
                    'The TLS certificate is not yet valid (valid from %s). Check your server clock and certificate configuration.',
                    date('Y-m-d H:i:s', $validFrom)
                )
            );
        }

        if ($now > $validTo) {
            return ProbeResult::failure(
                'TLS certificate expired',
                sprintf(
                    'The TLS certificate expired on %s. Renew your certificate from your certificate authority or use a service like Let\'s Encrypt.',
                    date('Y-m-d H:i:s', $validTo)
                )
            );
        }

        $daysUntilExpiry = (int) floor(($validTo - $now) / 86400);

        if ($daysUntilExpiry < self::TLS_WARNING_DAYS) {
            return ProbeResult::successWithWarning(
                sprintf(
                    'TLS certificate expiring in %d days (on %s). Consider renewing soon.',
                    $daysUntilExpiry,
                    date('Y-m-d', $validTo)
                )
            );
        }

        return ProbeResult::success();
    }

    /**
     * Verify Slack webhook URL using URL verification challenge.
     *
     * Slack sends a url_verification event with a challenge parameter
     * that must be echoed back in the response.
     */
    public function verifySlack(string $url): ProbeResult
    {
        $challenge = Str::random(32);

        try {
            $response = Http::timeout(self::SLACK_CHALLENGE_TIMEOUT)
                ->withOptions(['verify' => false])
                ->post($url, [
                    'type' => 'url_verification',
                    'challenge' => $challenge,
                ]);

            if (! $response->successful()) {
                if ($response->status() === 0) {
                    return ProbeResult::failure(
                        'Slack verification timeout',
                        'The webhook endpoint did not respond within 3 seconds. Slack requires responses within this window. Check your endpoint performance and network latency.'
                    );
                }

                return ProbeResult::failure(
                    'Slack verification failed',
                    sprintf(
                        'The webhook endpoint returned HTTP %d. Ensure your endpoint is configured to handle Slack events.',
                        $response->status()
                    )
                );
            }

            $responseChallenge = $response->json('challenge');

            if ($responseChallenge !== $challenge) {
                return ProbeResult::failure(
                    'Slack URL verification failed',
                    'The webhook endpoint did not echo the challenge parameter correctly. Ensure your endpoint extracts the "challenge" field from url_verification requests and returns it in the response body as {"challenge": "<challenge>"}.'
                );
            }

            return ProbeResult::success();
        } catch (\Throwable $e) {
            return ProbeResult::failure(
                'Slack verification timeout',
                sprintf(
                    'Could not verify Slack webhook: %s. Ensure the endpoint is accessible and responds within 3 seconds.',
                    $e->getMessage()
                )
            );
        }
    }

    /**
     * Verify Telegram webhook URL using setWebhook API.
     *
     * @param  array{bot_token: string}  $credentials
     */
    public function verifyTelegram(string $url, array $credentials): ProbeResult
    {
        $botToken = $credentials['bot_token'] ?? '';

        if (empty($botToken)) {
            return ProbeResult::failure(
                'Missing bot token',
                'Telegram bot token is required for webhook verification.'
            );
        }

        try {
            $response = Http::timeout(self::HTTP_TIMEOUT)
                ->post(sprintf('https://api.telegram.org/bot%s/setWebhook', $botToken), [
                    'url' => $url,
                ]);

            $data = $response->json();

            if (! ($data['ok'] ?? false)) {
                $description = $data['description'] ?? 'Unknown error';

                return ProbeResult::failure(
                    'Telegram setWebhook failed',
                    sprintf(
                        'Telegram API returned: %s. Ensure the webhook URL is publicly accessible via HTTPS.',
                        $description
                    )
                );
            }

            return ProbeResult::success();
        } catch (\Throwable $e) {
            return ProbeResult::failure(
                'Telegram verification failed',
                sprintf('Could not call Telegram setWebhook API: %s', $e->getMessage())
            );
        }
    }

    /**
     * Verify Discord webhook URL using PING/PONG interaction.
     *
     * Note: This sends a PING interaction to the endpoint and expects a PONG response.
     * In production, Discord verifies signature. Here we just check basic functionality.
     *
     * @param  array{public_key?: string}  $credentials
     */
    public function verifyDiscord(string $url, array $credentials): ProbeResult
    {
        try {
            // Discord PING interaction (type 1)
            $response = Http::timeout(self::HTTP_TIMEOUT)
                ->withOptions(['verify' => false])
                ->withHeaders([
                    'Content-Type' => 'application/json',
                ])
                ->withBody(json_encode(['type' => 1]), 'application/json')
                ->post($url);

            if ($response->status() === 401) {
                return ProbeResult::guidance(
                    'Ingress endpoint responded with 401 (expected for unsigned requests). '.
                    'Webhook signature verification is working correctly.'
                );
            }

            if (! $response->successful()) {
                return ProbeResult::failure(
                    'Discord verification failed',
                    sprintf(
                        'The webhook endpoint returned HTTP %d. Ensure your endpoint handles Discord interactions.',
                        $response->status()
                    )
                );
            }

            $data = $response->json();
            $responseType = $data['type'] ?? null;

            if ($responseType !== 1) {
                return ProbeResult::failure(
                    'Discord PONG response not received',
                    sprintf(
                        'Expected PONG response (type 1) but received type %s. Ensure your endpoint returns {"type": 1} for PING interactions.',
                        $responseType ?? 'null'
                    )
                );
            }

            return ProbeResult::success();
        } catch (\Throwable $e) {
            return ProbeResult::failure(
                'Discord verification failed',
                sprintf('Could not verify Discord webhook: %s', $e->getMessage())
            );
        }
    }

    /**
     * Verify WhatsApp webhook configuration.
     *
     * Note: WhatsApp webhook verification happens on Meta's side when configuring
     * the webhook in the Meta App Dashboard. We just validate that the verify_token
     * is configured and provide guidance for Meta dashboard setup.
     *
     * @param  array{verify_token?: string, phone_number_id?: string}  $credentials
     */
    public function verifyWhatsApp(array $credentials): ProbeResult
    {
        $verifyToken = $credentials['verify_token'] ?? '';
        $phoneNumberId = $credentials['phone_number_id'] ?? '';

        if (empty($verifyToken)) {
            return ProbeResult::failure(
                'Missing verify_token',
                'WhatsApp verify_token is required. This is a secret token you define that Meta will send during webhook verification.'
            );
        }

        if (empty($phoneNumberId)) {
            return ProbeResult::failure(
                'Missing phone_number_id',
                'WhatsApp phone_number_id is required. Find this in your Meta App Dashboard under WhatsApp > Getting Started.'
            );
        }

        return ProbeResult::success([
            'verify_token' => $verifyToken,
            'phone_number_id' => $phoneNumberId,
            'note' => 'Configure webhook in Meta App Dashboard with the verify_token above.',
        ]);
    }
}
