<?php

declare(strict_types=1);

namespace App\Http\Controllers\Docs;

use App\Http\Controllers\Controller;
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
        $entries = $this->catalog->search(
            $request->string('q')->toString(),
            $request->string('domain')->toString(),
            $request->string('section')->toString(),
            50
        );

        return Inertia::render('Docs/Index', [
            'entries' => $entries,
        ]);
    }

    public function show(string $slug): Response
    {
        $entry = $this->catalog->findEntry($slug);

        abort_if($entry === null, 404);

        return Inertia::render('Docs/Show', [
            'entry' => $entry,
        ]);
    }
}
