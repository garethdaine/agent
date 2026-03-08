<?php

declare(strict_types=1);

namespace App\Services\Security;

use App\DTOs\Security\InjectionDetectionResult;
use App\Enums\Runtime\RuntimeMode;
use App\Enums\Security\DetectionPatternType;
use App\Enums\Security\InjectionAction;
use App\Models\Security\SecurityDetectionRule;
use Illuminate\Support\Collection;

class InjectionDetectionEngine
{
    private const array SEVERITY_MULTIPLIERS = [
        'low' => 0.25,
        'medium' => 0.5,
        'high' => 0.75,
        'critical' => 1.0,
    ];

    private const int SCAN_TIMEOUT_MS = 40;

    private const array IMPERATIVE_VERBS = [
        'ignore', 'forget', 'disregard', 'override', 'execute',
        'send', 'post', 'delete', 'reveal', 'bypass', 'output',
        'transfer', 'forward', 'upload', 'activate',
    ];

    private const array ROLE_SWITCH_PHRASES = [
        'you are now', 'from now on you are', 'your new role',
        'your purpose is now', 'you should now', 'pretend you are',
        'act as if', 'switch to', 'you have been reprogrammed',
    ];

    private const array AUTHORITY_MARKERS = [
        'IMPORTANT:', 'URGENT:', 'CRITICAL:', 'ADMIN OVERRIDE',
        'developer mode', 'maintenance mode', 'authorized by',
        'admin note:', 'debug mode',
    ];

    private const array DELIMITER_PATTERNS = [
        '---END SYSTEM---', '</system>', '[/INST]',
        '---END INSTRUCTIONS---', '<<SYS>>', '<</SYS>>',
    ];

    private ?Collection $rules = null;

    public function __construct(
        private readonly SecurityConfigProvider $config,
    ) {}

    public function scan(string $content, RuntimeMode $mode, ?int $accountId = null): InjectionDetectionResult
    {
        $startTime = hrtime(true);

        if ($content === '') {
            return new InjectionDetectionResult(
                score: 0.0,
                matchedPatterns: [],
                recommendedAction: InjectionAction::Log,
                scanDurationMs: 0,
            );
        }

        $matchedPatterns = [];
        $weightedScore = 0.0;
        $rules = $this->loadRules();
        $totalRuleCount = $rules->count();

        foreach ($rules as $rule) {
            if ($this->hasExceededTimeout($startTime)) {
                return $this->buildResult($weightedScore, $totalRuleCount, $matchedPatterns, $startTime, InjectionAction::Log);
            }

            $matched = $this->evaluateRule($rule, $content);

            if ($matched) {
                $severityMultiplier = self::SEVERITY_MULTIPLIERS[$rule->severity->value] ?? 0.5;
                $weightedScore += $rule->weight * $severityMultiplier;

                $matchedPatterns[] = [
                    'rule_id' => $rule->id,
                    'pattern' => $rule->pattern,
                    'severity' => $rule->severity->value,
                    'weight' => $rule->weight,
                ];
            }
        }

        // Normalize rule-based score, then add heuristic bonuses
        $normalizedRuleScore = $this->normalizeScore($weightedScore, $totalRuleCount);

        $heuristicBonus = $this->detectInstructionDensity($content)
            + $this->detectRoleSwitching($content)
            + $this->detectAuthorityClaims($content)
            + $this->detectDelimiters($content);

        $normalizedScore = min(1.0, $normalizedRuleScore + $heuristicBonus);

        $threshold = $this->config->getThreshold($mode, $accountId);
        $action = $normalizedScore >= $threshold
            ? InjectionAction::from((string) $this->config->get('injection_action', $accountId))
            : InjectionAction::Log;

        $durationMs = (int) ((hrtime(true) - $startTime) / 1_000_000);

        return new InjectionDetectionResult(
            score: $normalizedScore,
            matchedPatterns: $matchedPatterns,
            recommendedAction: $action,
            scanDurationMs: $durationMs,
        );
    }

    public function scanWithThreshold(string $content, float $threshold): InjectionDetectionResult
    {
        $startTime = hrtime(true);

        if ($content === '') {
            return new InjectionDetectionResult(
                score: 0.0,
                matchedPatterns: [],
                recommendedAction: InjectionAction::Log,
                scanDurationMs: 0,
            );
        }

        $matchedPatterns = [];
        $weightedScore = 0.0;
        $rules = $this->loadRules();
        $totalRuleCount = $rules->count();

        foreach ($rules as $rule) {
            if ($this->hasExceededTimeout($startTime)) {
                return $this->buildResult($weightedScore, $totalRuleCount, $matchedPatterns, $startTime, InjectionAction::Log);
            }

            $matched = $this->evaluateRule($rule, $content);

            if ($matched) {
                $severityMultiplier = self::SEVERITY_MULTIPLIERS[$rule->severity->value] ?? 0.5;
                $weightedScore += $rule->weight * $severityMultiplier;

                $matchedPatterns[] = [
                    'rule_id' => $rule->id,
                    'pattern' => $rule->pattern,
                    'severity' => $rule->severity->value,
                    'weight' => $rule->weight,
                ];
            }
        }

        $normalizedRuleScore = $this->normalizeScore($weightedScore, $totalRuleCount);

        $heuristicBonus = $this->detectInstructionDensity($content)
            + $this->detectRoleSwitching($content)
            + $this->detectAuthorityClaims($content)
            + $this->detectDelimiters($content);

        $normalizedScore = min(1.0, $normalizedRuleScore + $heuristicBonus);
        $action = $normalizedScore >= $threshold
            ? InjectionAction::from((string) $this->config->get('injection_action'))
            : InjectionAction::Log;

        $durationMs = (int) ((hrtime(true) - $startTime) / 1_000_000);

        return new InjectionDetectionResult(
            score: $normalizedScore,
            matchedPatterns: $matchedPatterns,
            recommendedAction: $action,
            scanDurationMs: $durationMs,
        );
    }

    private function loadRules(): Collection
    {
        if ($this->rules === null) {
            $this->rules = SecurityDetectionRule::query()
                ->where('enabled', true)
                ->get();
        }

        return $this->rules;
    }

    private function evaluateRule(SecurityDetectionRule $rule, string $content): bool
    {
        return match ($rule->pattern_type) {
            DetectionPatternType::Regex => $this->evaluateRegex($rule->pattern, $content),
            DetectionPatternType::Keyword => $this->evaluateKeyword($rule->pattern, $content),
            default => false,
        };
    }

    private function evaluateRegex(string $pattern, string $content): bool
    {
        try {
            return preg_match($pattern, $content) === 1;
        } catch (\Throwable) {
            return false;
        }
    }

    private function evaluateKeyword(string $keyword, string $content): bool
    {
        return stripos($content, $keyword) !== false;
    }

    private function detectInstructionDensity(string $content): float
    {
        $words = str_word_count(strtolower($content));

        if ($words === 0) {
            return 0.0;
        }

        $verbCount = 0;
        $lowerContent = strtolower($content);

        foreach (self::IMPERATIVE_VERBS as $verb) {
            $verbCount += substr_count($lowerContent, $verb);
        }

        $densityPer100 = ($verbCount / max(1, $words)) * 100;

        return $densityPer100 > 3 ? 0.3 : 0.0;
    }

    private function detectRoleSwitching(string $content): float
    {
        $lowerContent = strtolower($content);
        $count = 0;

        foreach (self::ROLE_SWITCH_PHRASES as $phrase) {
            if (str_contains($lowerContent, $phrase)) {
                $count++;
            }
        }

        return $count > 0 ? 0.2 : 0.0;
    }

    private function detectAuthorityClaims(string $content): float
    {
        $count = 0;

        foreach (self::AUTHORITY_MARKERS as $marker) {
            if (stripos($content, $marker) !== false) {
                $count++;
            }
        }

        return $count > 2 ? 0.25 : 0.0;
    }

    private function detectDelimiters(string $content): float
    {
        foreach (self::DELIMITER_PATTERNS as $delimiter) {
            if (str_contains($content, $delimiter)) {
                return 0.4;
            }
        }

        return 0.0;
    }

    private function normalizeScore(float $weightedScore, int $totalRuleCount): float
    {
        // Cap denominator so a few critical matches produce high scores
        // With cap of 12: denominator = 3.0, single critical match = 0.33
        $maxPossible = max(1, min($totalRuleCount, 12) * 0.25);
        $normalized = $weightedScore / $maxPossible;

        return min(1.0, max(0.0, $normalized));
    }

    private function hasExceededTimeout(int $startTimeNs): bool
    {
        $elapsedMs = (hrtime(true) - $startTimeNs) / 1_000_000;

        return $elapsedMs > self::SCAN_TIMEOUT_MS;
    }

    private function buildResult(float $weightedScore, int $totalRuleCount, array $matchedPatterns, int $startTimeNs, InjectionAction $action): InjectionDetectionResult
    {
        $normalizedScore = $this->normalizeScore($weightedScore, $totalRuleCount);
        $durationMs = (int) ((hrtime(true) - $startTimeNs) / 1_000_000);

        return new InjectionDetectionResult(
            score: $normalizedScore,
            matchedPatterns: $matchedPatterns,
            recommendedAction: $action,
            scanDurationMs: $durationMs,
        );
    }
}
