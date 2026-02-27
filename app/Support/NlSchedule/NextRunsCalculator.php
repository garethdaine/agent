<?php

namespace App\Support\NlSchedule;

use Carbon\CarbonImmutable;
use Cron\CronExpression;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Calculates the next N scheduled runs for a cron expression,
 * optionally filtering by active hours configuration.
 *
 * Returns array of {local, utc} timestamp pairs representing the
 * true dispatch times (i.e., times when jobs will actually execute).
 *
 * Known limitations:
 * - Maximum 1000 cron iterations to prevent infinite loops
 * - Active hours overnight windows not supported
 */
final class NextRunsCalculator
{
    /**
     * Maximum cron iterations to prevent infinite loops.
     * E.g., if active hours config has no valid days, we stop after this many attempts.
     */
    private const MAX_ITERATIONS = 1000;

    public function __construct(
        private readonly ActiveHoursEvaluator $evaluator
    ) {
    }

    /**
     * Calculate the next N runs for a cron expression, filtered by active hours.
     *
     * @param string $cronExpression 5-part cron expression
     * @param string $timezone IANA timezone for the schedule
     * @param array|null $activeHours Active hours configuration (null = no filtering)
     * @param CarbonImmutable|null $from Starting point for calculation (defaults to now)
     * @param int $count Number of runs to return (default 5)
     * @return array<int, array{local: string, utc: string}> Array of timestamp pairs
     */
    public function calculate(
        string $cronExpression,
        string $timezone,
        ?array $activeHours,
        ?CarbonImmutable $from = null,
        int $count = 5
    ): array {
        $cron = new CronExpression($cronExpression);
        $tz = new DateTimeZone($timezone);
        $utcTz = new DateTimeZone('UTC');

        $runs = [];

        // Ensure we're working in the target timezone
        if ($from !== null) {
            $current = $from->setTimezone($timezone);
        } else {
            $current = CarbonImmutable::now($tz);
        }

        // Convert to DateTimeImmutable for cron library, preserving the timezone
        $currentDateTime = new DateTimeImmutable(
            $current->format('Y-m-d H:i:s'),
            $tz
        );

        $iterations = 0;

        while (count($runs) < $count && $iterations < self::MAX_ITERATIONS) {
            $iterations++;

            // Get the next cron run
            $nextRun = $cron->getNextRunDate($currentDateTime);

            // Convert to CarbonImmutable for active hours evaluation
            $nextRunCarbon = CarbonImmutable::createFromFormat(
                'Y-m-d H:i:s',
                $nextRun->format('Y-m-d H:i:s'),
                $tz
            );

            // Check if this run falls within active hours
            if ($this->evaluator->isWithinActiveHours($nextRunCarbon, $activeHours, $timezone)) {
                // Convert to immutable to avoid mutation issues, then convert to local and UTC
                // The cron library returns mutable DateTime, so setTimezone modifies in place
                $nextRunImmutable = DateTimeImmutable::createFromInterface($nextRun);
                $nextRunLocal = $nextRunImmutable->setTimezone($tz);
                $nextRunUtc = $nextRunImmutable->setTimezone($utcTz);

                $runs[] = [
                    'local' => $nextRunLocal->format('c'),
                    'utc' => $nextRunUtc->format('c'),
                ];
            }

            // Move to next iteration
            $currentDateTime = $nextRun;
        }

        return $runs;
    }
}
