<?php

declare(strict_types=1);

namespace App\Support\Documentation;

use App\Models\DocumentationEntry;
use App\Models\DocumentationFragment;
use App\Support\Documentation\Schemas\DocumentationValidationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class DocsRuntimeBootstrapService
{
    public function __construct(
        private readonly DocsSyncService $syncService
    ) {}

    public function ensureRuntimeDocsAvailable(): void
    {
        if ($this->hasPublishedEntriesAndFragments()) {
            return;
        }

        Cache::lock('docs:runtime-bootstrap', 30)->get(function (): void {
            if ($this->hasPublishedEntriesAndFragments()) {
                return;
            }

            try {
                $this->syncService->sync('commit', 'repo');
            } catch (DocumentationValidationException $exception) {
                Log::channel('docs')->warning('Runtime docs bootstrap validation failed.', [
                    'message' => $exception->getMessage(),
                    'errors' => $exception->errors(),
                ]);
            } catch (Throwable $exception) {
                Log::channel('docs')->warning('Runtime docs bootstrap failed unexpectedly.', [
                    'message' => $exception->getMessage(),
                ]);
            }
        });
    }

    private function hasPublishedEntriesAndFragments(): bool
    {
        $locale = (string) config('documentation.locale.default', 'en');

        $hasEntries = DocumentationEntry::query()
            ->where('locale', $locale)
            ->where('status', 'published')
            ->exists();

        if (! $hasEntries) {
            return false;
        }

        return DocumentationFragment::query()
            ->where('locale', $locale)
            ->where('status', 'published')
            ->exists();
    }
}
