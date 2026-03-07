<?php

declare(strict_types=1);

namespace App\Services\Skills;

use App\Services\Skills\DTOs\SkillLibraryEntry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

final class SkillLibrary
{
    private ?array $cachedManifest = null;

    private ?int $cachedMtime = null;

    public function __construct(
        private readonly string $manifestPath,
    ) {}

    /**
     * @param  array{category?: string, industry?: string, risk_level?: string, search?: string}  $filters
     */
    public function browse(array $filters = []): Collection
    {
        $manifest = $this->getManifest();
        $entries = collect($manifest['skills'] ?? [])
            ->map(fn (array $skill) => $this->toEntry($skill));

        if (isset($filters['category'])) {
            $entries = $entries->filter(fn (SkillLibraryEntry $e) => $e->category === $filters['category']);
        }

        if (isset($filters['industry'])) {
            $entries = $entries->filter(fn (SkillLibraryEntry $e) => in_array($filters['industry'], $e->industries, true));
        }

        if (isset($filters['risk_level'])) {
            $entries = $entries->filter(fn (SkillLibraryEntry $e) => $e->riskLevel === $filters['risk_level']);
        }

        if (isset($filters['search'])) {
            $entries = $this->applySearch($entries, $filters['search']);
        }

        return $entries->values();
    }

    public function search(string $query): Collection
    {
        return $this->browse(['search' => $query]);
    }

    public function find(string $slug): ?SkillLibraryEntry
    {
        $manifest = $this->getManifest();

        foreach ($manifest['skills'] ?? [] as $skill) {
            if (($skill['slug'] ?? '') === $slug) {
                return $this->toEntry($skill);
            }
        }

        return null;
    }

    private function getManifest(): array
    {
        if (! file_exists($this->manifestPath)) {
            Log::warning('Skill library manifest not found', ['path' => $this->manifestPath]);

            return ['skills' => []];
        }

        $currentMtime = filemtime($this->manifestPath);

        if ($this->cachedManifest !== null && $this->cachedMtime === $currentMtime) {
            return $this->cachedManifest;
        }

        $content = file_get_contents($this->manifestPath);
        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            Log::warning('Failed to parse skill library manifest', ['path' => $this->manifestPath]);

            return ['skills' => []];
        }

        $this->cachedManifest = $decoded;
        $this->cachedMtime = $currentMtime;

        return $decoded;
    }

    private function toEntry(array $skill): SkillLibraryEntry
    {
        return new SkillLibraryEntry(
            slug: $skill['slug'] ?? '',
            name: $skill['name'] ?? '',
            description: $skill['description'] ?? '',
            version: $skill['version'] ?? '',
            author: $skill['author'] ?? '',
            category: $skill['category'] ?? '',
            industries: $skill['industries'] ?? [],
            riskLevel: $skill['risk_level'] ?? 'standard',
            filePath: $skill['file'] ?? '',
            checksum: $skill['checksum'] ?? '',
        );
    }

    private function applySearch(Collection $entries, string $query): Collection
    {
        $terms = array_filter(
            explode(' ', mb_strtolower($query)),
            fn (string $term) => mb_strlen($term) >= 2
        );

        if (empty($terms)) {
            return $entries;
        }

        return $entries->filter(function (SkillLibraryEntry $entry) use ($terms) {
            $haystack = mb_strtolower($entry->name.' '.$entry->description);

            foreach ($terms as $term) {
                if (str_contains($haystack, $term)) {
                    return true;
                }
            }

            return false;
        });
    }
}
