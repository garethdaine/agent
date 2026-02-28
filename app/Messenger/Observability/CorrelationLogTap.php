<?php

declare(strict_types=1);

namespace App\Messenger\Observability;

use Monolog\LogRecord;

/**
 * Monolog tap that injects correlation IDs into log entries.
 *
 * This tap adds a correlation_id field to the 'extra' array of each log record,
 * enabling log aggregation tools to correlate all log entries related to a
 * single message lifecycle.
 *
 * Usage in config/logging.php:
 *
 *     'messenger' => [
 *         'driver' => 'daily',
 *         'path' => storage_path('logs/messenger.log'),
 *         'tap' => [App\Messenger\Observability\CorrelationLogTap::class],
 *     ],
 */
class CorrelationLogTap
{
    /**
     * Customize the given logger instance.
     *
     * @param  \Illuminate\Log\Logger  $logger
     */
    public function __invoke($logger): void
    {
        foreach ($logger->getHandlers() as $handler) {
            $handler->pushProcessor(function (LogRecord $record): LogRecord {
                $correlationId = app(CorrelationContext::class)->get();

                if ($correlationId) {
                    $extra = $record->extra;
                    $extra['correlation_id'] = $correlationId;

                    return $record->with(extra: $extra);
                }

                return $record;
            });
        }
    }
}
