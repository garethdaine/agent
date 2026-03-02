<?php

declare(strict_types=1);

namespace App\Support\Documentation;

use App\Models\DocumentationFragment;
use App\Support\Agent\FeatureFlagManager;

class TooltipRegistryService
{
    public function __construct(
        private readonly DocsTelemetryService $telemetry,
        private readonly FeatureFlagManager $featureFlags,
        private readonly DocsCatalog $catalog,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>|null
     */
    public function resolve(string $uiKey, ?string $locale = null, array $context = []): ?array
    {
        $normalizedUiKey = trim($uiKey);
        $normalizedLocale = trim((string) ($locale ?? config('documentation.locale.default', 'en')));
        $normalizedLocale = $normalizedLocale !== '' ? $normalizedLocale : 'en';

        if ($normalizedUiKey === '') {
            $this->telemetry->recordTooltipMiss('', 'missing_key', [
                ...$context,
                'locale' => $normalizedLocale,
            ]);

            return null;
        }

        $fragment = DocumentationFragment::query()
            ->with('learnMoreEntry:id,slug')
            ->where('ui_key', $normalizedUiKey)
            ->where('locale', $normalizedLocale)
            ->first();

        if ($fragment !== null) {
            return $this->fromDatabaseFragment($fragment, $context, $normalizedLocale);
        }

        $catalogFragment = $this->catalog->findFragment($normalizedUiKey);

        if ($catalogFragment !== null) {
            return [
                'ui_key' => $normalizedUiKey,
                'short_text' => (string) ($catalogFragment['short_text'] ?? ''),
                'long_text' => $catalogFragment['long_text'] ?? null,
                'severity' => (string) ($catalogFragment['severity'] ?? 'info'),
                'learn_more_slug' => $catalogFragment['learn_more_slug'] ?? null,
            ];
        }

        $this->telemetry->recordTooltipMiss($normalizedUiKey, 'missing_key', [
            ...$context,
            'locale' => $normalizedLocale,
        ]);

        return null;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>|null
     */
    private function fromDatabaseFragment(DocumentationFragment $fragment, array $context, string $locale): ?array
    {
        if ((string) $fragment->status !== 'published') {
            $this->telemetry->recordTooltipMiss((string) $fragment->ui_key, 'inactive_status', [
                ...$context,
                'locale' => $locale,
                'status' => (string) $fragment->status,
            ]);

            return null;
        }

        $featureFlag = trim((string) ($fragment->feature_flag ?? ''));
        if ($featureFlag !== '' && ! $this->featureFlags->enabled($featureFlag)) {
            $this->telemetry->recordTooltipMiss((string) $fragment->ui_key, 'feature_flag_disabled', [
                ...$context,
                'locale' => $locale,
                'feature_flag' => $featureFlag,
            ]);

            return null;
        }

        $shortText = trim((string) $fragment->short_text);
        if ($shortText === '') {
            $this->telemetry->recordTooltipMiss((string) $fragment->ui_key, 'missing_short_text', [
                ...$context,
                'locale' => $locale,
            ]);

            return null;
        }

        return [
            'ui_key' => (string) $fragment->ui_key,
            'short_text' => $shortText,
            'long_text' => $fragment->long_text,
            'severity' => (string) $fragment->severity,
            'learn_more_slug' => $fragment->learnMoreEntry?->slug,
        ];
    }
}
