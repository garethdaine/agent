<?php

declare(strict_types=1);

namespace App\Http\Controllers\Docs;

use App\Http\Controllers\Controller;
use App\Models\DocumentationEntry;
use App\Support\Documentation\DocsCatalog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DocsPageController extends Controller
{
    public function __construct(
        private readonly DocsCatalog $catalog
    ) {}

    public function index(Request $request): Response
    {
        $query = trim($request->string('q')->toString());
        $domain = trim($request->string('domain')->toString());
        $section = trim($request->string('section')->toString());
        $locale = (string) config('documentation.locale.default', 'en');

        $entries = DocumentationEntry::query()
            ->where('locale', $locale)
            ->where('status', 'published')
            ->when($domain !== '', fn ($builder) => $builder->where('domain', $domain))
            ->when($section !== '', fn ($builder) => $builder->where('section', $section))
            ->when($query !== '', function ($builder) use ($query): void {
                $builder->where(function ($nested) use ($query): void {
                    $nested->where('title', 'like', "%{$query}%")
                        ->orWhere('summary', 'like', "%{$query}%")
                        ->orWhere('slug', 'like', "%{$query}%");
                });
            })
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get(['slug', 'title', 'summary', 'section', 'domain', 'updated_at'])
            ->map(fn (DocumentationEntry $entry): array => [
                'slug' => (string) $entry->slug,
                'title' => (string) $entry->title,
                'summary' => (string) ($entry->summary ?? ''),
                'section' => (string) ($entry->section ?? ''),
                'domain' => (string) $entry->domain,
                'updated_at' => $entry->updated_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        if ($entries === []) {
            $entries = $this->catalog->search($query, $domain, $section, 50);
        }

        return Inertia::render('Docs/Index', [
            'entries' => $entries,
        ]);
    }

    public function show(string $slug): Response
    {
        $locale = (string) config('documentation.locale.default', 'en');
        $runtimeEntry = DocumentationEntry::query()
            ->where('slug', $slug)
            ->where('locale', $locale)
            ->where('status', 'published')
            ->first();

        if ($runtimeEntry !== null) {
            return Inertia::render('Docs/Show', [
                'entry' => [
                    'slug' => (string) $runtimeEntry->slug,
                    'title' => (string) $runtimeEntry->title,
                    'summary' => (string) ($runtimeEntry->summary ?? ''),
                    'section' => (string) ($runtimeEntry->section ?? ''),
                    'domain' => (string) $runtimeEntry->domain,
                    'body_html' => (string) ($runtimeEntry->body_html ?? ''),
                    'body_markdown' => (string) $runtimeEntry->body_markdown,
                    'updated_at' => $runtimeEntry->updated_at?->toIso8601String(),
                ],
            ]);
        }

        $entry = $this->catalog->findEntry($slug);

        abort_if($entry === null, 404);

        return Inertia::render('Docs/Show', [
            'entry' => $entry,
        ]);
    }
}
