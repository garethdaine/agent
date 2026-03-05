<?php

namespace App\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

class RedactSensitiveProcessor implements ProcessorInterface
{
    private const PATTERNS = [
        '/Bearer\s+[A-Za-z0-9\-._~+\/]+=*/i' => 'Bearer [REDACTED]',
        '/(?:api[_-]?key|token|secret|password|credential|authorization)\s*[:=]\s*["\']?[A-Za-z0-9\-._~+\/]{8,}["\']?/i' => '$0_REDACTED',
        '/sk-[A-Za-z0-9]{20,}/' => '[REDACTED_KEY]',
        '/xoxb-[A-Za-z0-9\-]+/' => '[REDACTED_SLACK_TOKEN]',
        '/ghp_[A-Za-z0-9]{36,}/' => '[REDACTED_GH_TOKEN]',
    ];

    public function __invoke(LogRecord $record): LogRecord
    {
        if (! config('logging.redact_sensitive', false)) {
            return $record;
        }

        $message = $this->redact($record->message);
        $context = $this->redactArray($record->context);
        $extra = $this->redactArray($record->extra);

        return $record->with(message: $message, context: $context, extra: $extra);
    }

    private function redact(string $value): string
    {
        foreach (self::PATTERNS as $pattern => $replacement) {
            $value = preg_replace($pattern, $replacement, $value) ?? $value;
        }

        return $value;
    }

    private function redactArray(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $data[$key] = $this->redact($value);
            } elseif (is_array($value)) {
                $data[$key] = $this->redactArray($value);
            }
        }

        return $data;
    }
}
