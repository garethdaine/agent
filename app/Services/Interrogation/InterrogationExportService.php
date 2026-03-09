<?php

declare(strict_types=1);

namespace App\Services\Interrogation;

use App\Models\InterrogationSession;
use App\Support\Interrogation\ExportService;

class InterrogationExportService
{
    public function __construct(
        private readonly ExportService $exportService,
    ) {}

    public function exportSummary(InterrogationSession $session): string
    {
        return $this->exportService->exportSummary($session);
    }

    public function exportPlan(InterrogationSession $session): string
    {
        return $this->exportService->exportPlan($session);
    }
}
