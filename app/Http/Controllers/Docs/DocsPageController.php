<?php

declare(strict_types=1);

namespace App\Http\Controllers\Docs;

use App\Http\Controllers\Controller;
use App\Models\DocumentationEntry;
use App\Support\Documentation\DocsCatalog;
use App\Support\Documentation\DocsRuntimeBootstrapService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DocsPageController extends Controller
{
    public function __construct(
        private readonly DocsCatalog $catalog,
        private readonly DocsRuntimeBootstrapService $runtimeBootstrap,
    ) {}

    public function index(Request $request): Response
    {
        $this->runtimeBootstrap->ensureRuntimeDocsAvailable();

        $filters = $this->filters($request);
        $entries = $this->runtimeEntries($filters['q'], $filters['domain'], $filters['section'])
            ->values()
            ->all();

        if ($entries === []) {
            $entries = $this->catalog->search($filters['q'], $filters['domain'], $filters['section'], 200);
        }

        $activeSlug = trim((string) $request->query('slug', ''));
        if ($activeSlug === '' && $entries !== []) {
            $activeSlug = (string) ($entries[0]['slug'] ?? '');
        }

        $activeEntry = $this->runtimeEntry($activeSlug) ?? $this->catalog->findEntry($activeSlug);

        return Inertia::render('Docs/Index', [
            'entries' => $entries,
            'activeEntry' => $activeEntry,
            'filters' => $filters,
        ]);
    }

    public function show(Request $request, string $slug): Response
    {
        $this->runtimeBootstrap->ensureRuntimeDocsAvailable();

        $filters = $this->filters($request);
        $entry = $this->runtimeEntry($slug) ?? $this->catalog->findEntry($slug);

        abort_if($entry === null, 404);

        $entries = $this->runtimeEntries($filters['q'], $filters['domain'], $filters['section']);

        if ($entries->isEmpty()) {
            $entries = collect($this->catalog->search($filters['q'], $filters['domain'], $filters['section'], 200));
        }

        if (! $entries->contains(fn (array $item): bool => (string) ($item['slug'] ?? '') === $slug)) { // @phpstan-ignore nullCoalesce.offset
            $entries->prepend([
                'slug' => (string) ($entry['slug'] ?? $slug),
                'title' => (string) ($entry['title'] ?? $slug),
                'summary' => (string) ($entry['summary'] ?? ''),
                'section' => (string) ($entry['section'] ?? ''),
                'domain' => (string) ($entry['domain'] ?? ''),
                'updated_at' => $entry['updated_at'] ?? null,
            ]);
        }

        return Inertia::render('Docs/Show', [
            'entry' => $entry,
            'entries' => $entries->values()->all(),
            'filters' => $filters,
        ]);
    }

    /**
     * @return array{q:string,domain:string,section:string}
     */
    private function filters(Request $request): array
    {
        return [
            'q' => trim($request->string('q')->toString()),
            'domain' => trim($request->string('domain')->toString()),
            'section' => trim($request->string('section')->toString()),
        ];
    }

    /**
     * @return Collection<int, array{slug:string,title:string,summary:string,section:string,domain:string,updated_at:string|null}>
     */
    private function runtimeEntries(string $query, string $domain, string $section): Collection
    {
        $locale = (string) config('documentation.locale.default', 'en');

        return DocumentationEntry::query() // @phpstan-ignore return.type
            ->where('locale', $locale)
            ->where('status', 'published')
            ->when($domain !== '', fn ($builder) => $builder->where('domain', $domain))
            ->when($section !== '', fn ($builder) => $builder->where('section', $section))
            ->when($query !== '', function ($builder) use ($query): void {
                $builder->where(function ($nested) use ($query): void {
                    $nested->where('title', 'like', "%{$query}%")
                        ->orWhere('summary', 'like', "%{$query}%")
                        ->orWhere('slug', 'like', "%{$query}%")
                        ->orWhere('body_markdown', 'like', "%{$query}%");
                });
            })
            ->orderBy('section')
            ->orderBy('title')
            ->limit(200)
            ->get(['slug', 'title', 'summary', 'section', 'domain', 'updated_at'])
            ->map(fn (DocumentationEntry $entry): array => [
                'slug' => (string) $entry->slug,
                'title' => (string) $entry->title,
                'summary' => (string) ($entry->summary ?? ''),
                'section' => (string) ($entry->section ?? ''),
                'domain' => (string) $entry->domain,
                'updated_at' => $entry->updated_at?->toIso8601String(),
            ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function runtimeEntry(string $slug): ?array
    {
        $normalizedSlug = trim($slug);
        if ($normalizedSlug === '') {
            return null;
        }

        $locale = (string) config('documentation.locale.default', 'en');
        $entry = DocumentationEntry::query()
            ->where('slug', $normalizedSlug)
            ->where('locale', $locale)
            ->where('status', 'published')
            ->first();

        if ($entry === null) {
            return null;
        }

        return [
            'slug' => (string) $entry->slug,
            'title' => (string) $entry->title,
            'summary' => (string) ($entry->summary ?? ''),
            'section' => (string) ($entry->section ?? ''),
            'domain' => (string) $entry->domain,
            'body_html' => (string) ($entry->body_html ?? ''),
            'body_markdown' => (string) $entry->body_markdown,
            'updated_at' => $entry->updated_at?->toIso8601String(),
        ];
    }
}
