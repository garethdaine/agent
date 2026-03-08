<?php

namespace App\Support\NlSchedule;

use Carbon\CarbonImmutable;

/**
 * Evaluates whether a given timestamp falls within an active hours window.
 *
 * Active hours configuration schema:
 * {
 *   "start": "HH:MM",  // Start time (defaults to 00:00 if not specified)
 *   "end": "HH:MM",    // End time (defaults to 23:59 if not specified)
 *   "days": [1,2,3,4,5] // ISO-8601 day indexing: 1=Mon..7=Sun
 * }
 *
 * Known limitations:
 * - Overnight windows (e.g., 22:00-06:00) are NOT supported in v1
 * - Multi-window support per day is NOT supported
 * - Window is inclusive on both ends (09:00 and 17:00 are both within 09:00-17:00)
 */
final class ActiveHoursEvaluator
{
    /**
     * Check if a timestamp falls within the active hours window.
     *
     * @param  CarbonImmutable  $timestamp  The timestamp to check
     * @param  array|null  $config  Active hours configuration (null = no restriction)
     * @param  string  $timezone  IANA timezone to evaluate the window in
     * @return bool True if within active hours, false otherwise
     */
    public function isWithinActiveHours(
        CarbonImmutable $timestamp,
        ?array $config,
        string $timezone
    ): bool {
        // Null config means no restriction - always active
        if ($config === null) {
            return true;
        }

        // Convert timestamp to the configured timezone for evaluation
        $zoned = $timestamp->setTimezone($timezone);

        // Check day of week (ISO-8601: 1=Mon..7=Sun)
        $dayOfWeek = $zoned->dayOfWeekIso;
        $allowedDays = $config['days'] ?? [];

        if (! in_array($dayOfWeek, $allowedDays, true)) {
            return false;
        }

        // Check time window (inclusive on both ends)
        $currentTime = $zoned->format('H:i');
        $start = $config['start'] ?? '00:00';
        $end = $config['end'] ?? '23:59';

        return $currentTime >= $start && $currentTime <= $end;
    }

    /**
     * Generate structured metadata for a skipped run due to active hours.
     *
     * @param  CarbonImmutable  $timestamp  The scheduled timestamp that was skipped
     * @param  array  $config  The active hours configuration
     * @param  string  $jobId  The job identifier
     * @return array Structured skip metadata
     */
    public function getSkipMetadata(
        CarbonImmutable $timestamp,
        array $config,
        string $jobId
    ): array {
        return [
            'skip_reason' => 'outside_active_hours',
            'job_id' => $jobId,
            'scheduled_time' => $timestamp->toIso8601String(),
            'active_hours_config' => $config,
        ];
    }
}
