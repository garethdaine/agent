<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('interrogation_events')
            ->select(['id', 'payload'])
            ->where('event_type', 'question')
            ->orderBy('id')
            ->chunkById(200, function ($events): void {
                foreach ($events as $event) {
                    $payload = $this->decodePayload($event->payload ?? null);
                    if (! is_array($payload)) {
                        continue;
                    }

                    $existingCategory = $payload['category'] ?? null;
                    if (! is_string($existingCategory)) {
                        continue;
                    }

                    $normalizedCategory = $this->normalizeCategory($existingCategory);
                    if ($normalizedCategory === '' || $normalizedCategory === $existingCategory) {
                        continue;
                    }

                    $payload['category'] = $normalizedCategory;

                    DB::table('interrogation_events')
                        ->where('id', $event->id)
                        ->update([
                            'payload' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                            'updated_at' => CarbonImmutable::now('UTC'),
                        ]);
                }
            });
    }

    public function down(): void
    {
        // Irreversible data normalization.
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodePayload(mixed $payload): ?array
    {
        if (is_array($payload)) {
            return $payload;
        }

        if (! is_string($payload)) {
            return null;
        }

        $decoded = json_decode($payload, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function normalizeCategory(string $category): string
    {
        $normalized = strtolower(trim($category));
        if ($normalized === '') {
            return '';
        }

        $normalized = str_replace('_', '-', $normalized);
        $normalized = preg_replace('/\s+/', '-', $normalized) ?? $normalized;
        $normalized = preg_replace('/-+/', '-', $normalized) ?? $normalized;
        $normalized = trim($normalized, '-');

        return $normalized;
    }
};
