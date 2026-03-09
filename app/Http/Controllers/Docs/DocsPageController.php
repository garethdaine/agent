<?php

declare(strict_types=1);

namespace App\Http\Controllers\Docs;

use App\Actions\Documentation\FindDocumentationEntryAction;
use App\Actions\Documentation\ListDocumentationEntriesAction;
use App\Http\Controllers\Controller;
use App\Support\Documentation\DocsCatalog;
use App\Support\Documentation\DocsRuntimeBootstrapService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DocsPageController extends Controller
{
    public function __construct(
        private readonly DocsCatalog $catalog,
        private readonly DocsRuntimeBootstrapService $runtimeBootstrap,
        private readonly ListDocumentationEntriesAction $listEntries,
        private readonly FindDocumentationEntryAction $findEntry,
    ) {}

    public function index(Request $request): Response
    {
        $this->runtimeBootstrap->ensureRuntimeDocsAvailable();

        $filters = $this->filters($request);
        $locale = (string) config('documentation.locale.default', 'en');

        $entries = $this->listEntries->execute($filters['q'], $filters['domain'], $filters['section'], $locale)
            ->values()
            ->all();

        if ($entries === []) {
            $entries = $this->catalog->search($filters['q'], $filters['domain'], $filters['section'], 200);
        }

        $activeSlug = trim((string) $request->query('slug', ''));
        if ($activeSlug === '' && $entries !== []) {
            $activeSlug = (string) ($entries[0]['slug'] ?? '');
        }

        $activeEntry = $this->findEntry->execute($activeSlug, $locale) ?? $this->catalog->findEntry($activeSlug);

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
        $locale = (string) config('documentation.locale.default', 'en');

        $entry = $this->findEntry->execute($slug, $locale) ?? $this->catalog->findEntry($slug);

        abort_if($entry === null, 404);

        $entries = $this->listEntries->execute($filters['q'], $filters['domain'], $filters['section'], $locale);

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
}
