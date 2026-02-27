<?php

namespace App\Support\NlSchedule;

use Cron\CronExpression;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Rule-based schedule parser for natural language scheduling patterns.
 *
 * Handles v1 NL patterns with confidence scoring. Does not handle
 * complex or ambiguous patterns - those require LLM fallback.
 *
 * Day indexing: ISO-8601 (1=Monday through 7=Sunday).
 * Cron day-of-week mapping: 0=Sunday, 1=Monday...6=Saturday.
 */
final class RuleBasedScheduleParser
{
    /**
     * ISO-8601 day names to cron day-of-week values.
     * ISO-8601: Mon=1, Tue=2, ..., Sun=7
     * Cron: Sun=0, Mon=1, ..., Sat=6
     */
    private const DAY_MAP = [
        'monday' => 1,
        'tuesday' => 2,
        'wednesday' => 3,
        'thursday' => 4,
        'friday' => 5,
        'saturday' => 6,
        'sunday' => 0,
        'mon' => 1,
        'tue' => 2,
        'wed' => 3,
        'thu' => 4,
        'fri' => 5,
        'sat' => 6,
        'sun' => 0,
    ];

    /**
     * Parse a natural language schedule description into a ParseResult.
     *
     * @param string $input Natural language schedule description
     * @param string $timezone IANA timezone string
     * @return ParseResult
     * @throws \InvalidArgumentException If input is invalid or contains sub-minute intervals
     */
    public function parse(string $input, string $timezone): ParseResult
    {
        $input = trim($input);
        $normalized = strtolower($input);

        // Reject sub-minute intervals
        if (preg_match('/every\s+(\d+)\s*seconds?/i', $normalized)) {
            throw new \InvalidArgumentException('Sub-minute intervals are not supported. Minimum interval is 1 minute.');
        }

        // Try each pattern in order of specificity
        $result = $this->tryEveryXMinutes($normalized, $timezone)
            ?? $this->tryEveryXHours($normalized, $timezone)
            ?? $this->tryEveryXHoursStartingAt($normalized, $timezone)
            ?? $this->tryEveryDayBetween($normalized, $timezone)
            ?? $this->tryDailyAt($normalized, $timezone)
            ?? $this->tryWeekdaysAt($normalized, $timezone)
            ?? $this->tryWeekendsAt($normalized, $timezone)
            ?? $this->trySpecificDayAt($normalized, $timezone)
            ?? $this->tryHourly($normalized, $timezone)
            ?? $this->tryTwiceADay($normalized, $timezone)
            ?? $this->tryBareTimeWithoutAmPm($normalized, $timezone);

        if ($result === null) {
            // Unknown pattern - return low confidence result for LLM fallback
            return new ParseResult(
                cronExpression: '0 0 * * *',
                timezone: $timezone,
                explanation: "Unable to parse schedule pattern: \"{$input}\". Defaulting to daily at midnight.",
                activeHours: null,
                nextRuns: $this->calculateNextRuns('0 0 * * *', $timezone, null),
                confidence: 0.1,
                parserPath: 'rule_based',
                ambiguous: true,
            );
        }

        return $result;
    }

    /**
     * Pattern: "every X minutes" or "every X minute"
     */
    private function tryEveryXMinutes(string $input, string $timezone): ?ParseResult
    {
        if (!preg_match('/every\s+(\d+)\s*minutes?$/i', $input, $matches)) {
            return null;
        }

        $minutes = (int) $matches[1];
        $this->validateMinimumInterval($minutes);

        // For intervals that don't divide evenly into 60, use simple step
        $cron = "*/{$minutes} * * * *";

        return new ParseResult(
            cronExpression: $cron,
            timezone: $timezone,
            explanation: "Every {$minutes} minute" . ($minutes !== 1 ? 's' : ''),
            activeHours: null,
            nextRuns: $this->calculateNextRuns($cron, $timezone, null),
            confidence: 0.95,
            parserPath: 'rule_based',
            ambiguous: false,
        );
    }

    /**
     * Pattern: "every X hours" or "every X hour"
     */
    private function tryEveryXHours(string $input, string $timezone): ?ParseResult
    {
        if (!preg_match('/^every\s+(\d+)\s*hours?$/i', $input, $matches)) {
            return null;
        }

        $hours = (int) $matches[1];

        if ($hours < 1 || $hours > 23) {
            return null;
        }

        $cron = "0 */{$hours} * * *";

        return new ParseResult(
            cronExpression: $cron,
            timezone: $timezone,
            explanation: "Every {$hours} hour" . ($hours !== 1 ? 's' : '') . ' at minute 0',
            activeHours: null,
            nextRuns: $this->calculateNextRuns($cron, $timezone, null),
            confidence: 0.95,
            parserPath: 'rule_based',
            ambiguous: false,
        );
    }

    /**
     * Pattern: "every X hours starting at TIME"
     */
    private function tryEveryXHoursStartingAt(string $input, string $timezone): ?ParseResult
    {
        if (!preg_match('/every\s+(\d+)\s*hours?\s+starting\s+at\s+(\d{1,2})(?::(\d{2}))?\s*(am|pm)?/i', $input, $matches)) {
            return null;
        }

        $hours = (int) $matches[1];
        $startHour = (int) $matches[2];
        $startMinute = isset($matches[3]) && $matches[3] !== '' ? (int) $matches[3] : 0;
        $ampm = $matches[4] ?? null;

        if ($hours < 1 || $hours > 23) {
            return null;
        }

        $startHour = $this->normalizeHour($startHour, $ampm);
        $cron = "{$startMinute} */{$hours} * * *";

        $activeHours = [
            'start' => sprintf('%02d:%02d', $startHour, $startMinute),
        ];

        return new ParseResult(
            cronExpression: $cron,
            timezone: $timezone,
            explanation: "Every {$hours} hour" . ($hours !== 1 ? 's' : '') . " starting at " . sprintf('%02d:%02d', $startHour, $startMinute),
            activeHours: $activeHours,
            nextRuns: $this->calculateNextRuns($cron, $timezone, $activeHours),
            confidence: 0.85,
            parserPath: 'rule_based',
            ambiguous: false,
        );
    }

    /**
     * Pattern: "every day between TIME and TIME"
     */
    private function tryEveryDayBetween(string $input, string $timezone): ?ParseResult
    {
        if (!preg_match('/every\s+day\s+between\s+(\d{1,2})(?::(\d{2}))?\s*(am|pm)?\s+and\s+(\d{1,2})(?::(\d{2}))?\s*(am|pm)?/i', $input, $matches)) {
            return null;
        }

        $startHour = (int) $matches[1];
        $startMinute = isset($matches[2]) && $matches[2] !== '' ? (int) $matches[2] : 0;
        $startAmPm = $matches[3] ?? null;
        $endHour = (int) $matches[4];
        $endMinute = isset($matches[5]) && $matches[5] !== '' ? (int) $matches[5] : 0;
        $endAmPm = $matches[6] ?? null;

        $startHour = $this->normalizeHour($startHour, $startAmPm);
        $endHour = $this->normalizeHour($endHour, $endAmPm);

        // Cron for every hour on the hour during the window
        $cron = "0 {$startHour} * * *";

        $activeHours = [
            'start' => sprintf('%02d:%02d', $startHour, $startMinute),
            'end' => sprintf('%02d:%02d', $endHour, $endMinute),
        ];

        return new ParseResult(
            cronExpression: $cron,
            timezone: $timezone,
            explanation: "Every day between " . sprintf('%02d:%02d', $startHour, $startMinute) . " and " . sprintf('%02d:%02d', $endHour, $endMinute),
            activeHours: $activeHours,
            nextRuns: $this->calculateNextRuns($cron, $timezone, $activeHours),
            confidence: 0.85,
            parserPath: 'rule_based',
            ambiguous: false,
        );
    }

    /**
     * Pattern: "daily at TIME" with AM/PM
     */
    private function tryDailyAt(string $input, string $timezone): ?ParseResult
    {
        if (!preg_match('/daily\s+at\s+(\d{1,2})(?::(\d{2}))?\s*(am|pm)/i', $input, $matches)) {
            return null;
        }

        $hour = (int) $matches[1];
        $minute = isset($matches[2]) && $matches[2] !== '' ? (int) $matches[2] : 0;
        $ampm = strtolower($matches[3]);

        $hour = $this->normalizeHour($hour, $ampm);
        $cron = "{$minute} {$hour} * * *";

        return new ParseResult(
            cronExpression: $cron,
            timezone: $timezone,
            explanation: "Daily at " . sprintf('%d:%02d %s', $hour > 12 ? $hour - 12 : ($hour === 0 ? 12 : $hour), $minute, $hour >= 12 ? 'PM' : 'AM'),
            activeHours: null,
            nextRuns: $this->calculateNextRuns($cron, $timezone, null),
            confidence: 0.95,
            parserPath: 'rule_based',
            ambiguous: false,
        );
    }

    /**
     * Pattern: "daily at TIME" WITHOUT AM/PM - ambiguous
     */
    private function tryBareTimeWithoutAmPm(string $input, string $timezone): ?ParseResult
    {
        if (!preg_match('/daily\s+at\s+(\d{1,2})(?::(\d{2}))?$/i', $input, $matches)) {
            return null;
        }

        $hour = (int) $matches[1];
        $minute = isset($matches[2]) && $matches[2] !== '' ? (int) $matches[2] : 0;

        // Assume PM for business hours (5-11), AM otherwise
        $assumedHour = ($hour >= 1 && $hour <= 11) ? $hour : $hour;
        $cron = "{$minute} {$assumedHour} * * *";

        return new ParseResult(
            cronExpression: $cron,
            timezone: $timezone,
            explanation: "Ambiguous time: {$hour}" . ($minute > 0 ? sprintf(':%02d', $minute) : '') . " - could be AM or PM. Please specify AM/PM.",
            activeHours: null,
            nextRuns: $this->calculateNextRuns($cron, $timezone, null),
            confidence: 0.5,
            parserPath: 'rule_based',
            ambiguous: true,
        );
    }

    /**
     * Pattern: "weekdays at TIME"
     */
    private function tryWeekdaysAt(string $input, string $timezone): ?ParseResult
    {
        if (!preg_match('/weekdays?\s+at\s+(\d{1,2})(?::(\d{2}))?\s*(am|pm)/i', $input, $matches)) {
            return null;
        }

        $hour = (int) $matches[1];
        $minute = isset($matches[2]) && $matches[2] !== '' ? (int) $matches[2] : 0;
        $ampm = strtolower($matches[3]);

        $hour = $this->normalizeHour($hour, $ampm);
        // Weekdays: Monday-Friday = cron 1-5
        $cron = "{$minute} {$hour} * * 1-5";

        return new ParseResult(
            cronExpression: $cron,
            timezone: $timezone,
            explanation: "Weekdays (Monday-Friday) at " . sprintf('%d:%02d %s', $hour > 12 ? $hour - 12 : ($hour === 0 ? 12 : $hour), $minute, $hour >= 12 ? 'PM' : 'AM'),
            activeHours: null,
            nextRuns: $this->calculateNextRuns($cron, $timezone, null),
            confidence: 0.95,
            parserPath: 'rule_based',
            ambiguous: false,
        );
    }

    /**
     * Pattern: "weekends at TIME"
     */
    private function tryWeekendsAt(string $input, string $timezone): ?ParseResult
    {
        if (!preg_match('/weekends?\s+at\s+(\d{1,2})(?::(\d{2}))?\s*(am|pm)/i', $input, $matches)) {
            return null;
        }

        $hour = (int) $matches[1];
        $minute = isset($matches[2]) && $matches[2] !== '' ? (int) $matches[2] : 0;
        $ampm = strtolower($matches[3]);

        $hour = $this->normalizeHour($hour, $ampm);
        // Weekends: Saturday=6, Sunday=0
        $cron = "{$minute} {$hour} * * 6,0";

        return new ParseResult(
            cronExpression: $cron,
            timezone: $timezone,
            explanation: "Weekends (Saturday-Sunday) at " . sprintf('%d:%02d %s', $hour > 12 ? $hour - 12 : ($hour === 0 ? 12 : $hour), $minute, $hour >= 12 ? 'PM' : 'AM'),
            activeHours: null,
            nextRuns: $this->calculateNextRuns($cron, $timezone, null),
            confidence: 0.95,
            parserPath: 'rule_based',
            ambiguous: false,
        );
    }

    /**
     * Pattern: "every Monday at TIME" (any specific day)
     */
    private function trySpecificDayAt(string $input, string $timezone): ?ParseResult
    {
        $daysPattern = implode('|', array_keys(self::DAY_MAP));

        if (!preg_match("/every\s+({$daysPattern})\s+at\s+(\d{1,2})(?::(\d{2}))?\s*(am|pm)?/i", $input, $matches)) {
            return null;
        }

        $dayName = strtolower($matches[1]);
        $hour = (int) $matches[2];
        $minute = isset($matches[3]) && $matches[3] !== '' ? (int) $matches[3] : 0;
        $ampm = $matches[4] ?? null;

        $cronDay = self::DAY_MAP[$dayName];
        $hour = $this->normalizeHour($hour, $ampm);
        $cron = "{$minute} {$hour} * * {$cronDay}";

        // Confidence is slightly lower if AM/PM not specified
        $confidence = $ampm !== null ? 0.95 : 0.7;
        $ambiguous = $ampm === null;

        return new ParseResult(
            cronExpression: $cron,
            timezone: $timezone,
            explanation: "Every " . ucfirst($dayName) . " at " . sprintf('%d:%02d', $hour > 12 ? $hour - 12 : ($hour === 0 ? 12 : $hour), $minute) . ($ampm ? ' ' . strtoupper($ampm) : ''),
            activeHours: null,
            nextRuns: $this->calculateNextRuns($cron, $timezone, null),
            confidence: $confidence,
            parserPath: 'rule_based',
            ambiguous: $ambiguous,
        );
    }

    /**
     * Pattern: "hourly"
     */
    private function tryHourly(string $input, string $timezone): ?ParseResult
    {
        if (!preg_match('/^hourly$/i', $input)) {
            return null;
        }

        $cron = '0 * * * *';

        return new ParseResult(
            cronExpression: $cron,
            timezone: $timezone,
            explanation: 'Every hour at minute 0',
            activeHours: null,
            nextRuns: $this->calculateNextRuns($cron, $timezone, null),
            confidence: 0.99,
            parserPath: 'rule_based',
            ambiguous: false,
        );
    }

    /**
     * Pattern: "twice a day" - ambiguous, suggests 9am and 5pm
     */
    private function tryTwiceADay(string $input, string $timezone): ?ParseResult
    {
        if (!preg_match('/twice\s+a\s+day/i', $input)) {
            return null;
        }

        // Default to 9am and 5pm (business hours)
        $cron = '0 9,17 * * *';

        return new ParseResult(
            cronExpression: $cron,
            timezone: $timezone,
            explanation: 'Twice a day - suggesting 9:00 AM and 5:00 PM. Please confirm or specify exact times.',
            activeHours: null,
            nextRuns: $this->calculateNextRuns($cron, $timezone, null),
            confidence: 0.6,
            parserPath: 'rule_based',
            ambiguous: true,
        );
    }

    /**
     * Normalize hour based on AM/PM indicator.
     */
    private function normalizeHour(int $hour, ?string $ampm): int
    {
        if ($ampm === null) {
            return $hour;
        }

        $ampm = strtolower($ampm);

        if ($ampm === 'am') {
            return $hour === 12 ? 0 : $hour;
        }

        if ($ampm === 'pm') {
            return $hour === 12 ? 12 : $hour + 12;
        }

        return $hour;
    }

    /**
     * Validate minimum interval from configuration.
     *
     * @throws \InvalidArgumentException If interval is below minimum
     */
    private function validateMinimumInterval(int $minutes): void
    {
        $minInterval = (int) config('agent.nl_parse.min_interval_minutes', 1);

        if ($minutes < $minInterval) {
            throw new \InvalidArgumentException(
                "Interval of {$minutes} minute(s) is below the minimum allowed interval of {$minInterval} minute(s)."
            );
        }
    }

    /**
     * Calculate the next N runs for the cron expression.
     *
     * @param string $cronExpression 5-part cron expression
     * @param string $timezone IANA timezone
     * @param array|null $activeHours Active hours configuration (not used for filtering in this basic implementation)
     * @return array Array of {local, utc} timestamp pairs
     */
    private function calculateNextRuns(string $cronExpression, string $timezone, ?array $activeHours): array
    {
        try {
            $cron = new CronExpression($cronExpression);
            $tz = new DateTimeZone($timezone);
            $utcTz = new DateTimeZone('UTC');

            $runs = [];
            $now = new DateTimeImmutable('now', $tz);

            for ($i = 0; $i < 5; $i++) {
                $nextRun = $cron->getNextRunDate($i === 0 ? $now : $runs[$i - 1]['_datetime']);
                $nextRunTz = $nextRun->setTimezone($tz);
                $nextRunUtc = $nextRun->setTimezone($utcTz);

                $runs[] = [
                    'local' => $nextRunTz->format('c'),
                    'utc' => $nextRunUtc->format('c'),
                    '_datetime' => $nextRun, // Internal use for iteration
                ];
            }

            // Remove internal _datetime from output
            return array_map(fn ($run) => ['local' => $run['local'], 'utc' => $run['utc']], $runs);
        } catch (\Exception $e) {
            return [];
        }
    }
}
