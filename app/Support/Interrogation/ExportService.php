<?php

namespace App\Support\Interrogation;

use App\Models\InterrogationSession;

class ExportService
{
    public function __construct(private readonly SummaryPayloadNormalizer $summaryPayloadNormalizer) {}

    public function exportSummary(InterrogationSession $session): string
    {
        $directory = rtrim($session->project_directory, '/').'/docs/discovery';
        $this->ensureDirectory($directory);

        $slug = $this->sessionSlug($session);
        $path = $this->nextAvailablePath($directory, $slug, 'md');

        $summary = is_array($session->summary_json)
            ? $this->summaryPayloadNormalizer->normalize($session->summary_json)
            : [];
        $markdown = (string) ($summary['summary_markdown'] ?? '');

        $content = "# Requirements Discovery Summary\n\n";
        $content .= "Session: {$session->id}\n\n";

        if ($markdown !== '') {
            $content .= $markdown."\n";
        }

        $content .= $this->appendListSection('Goals', $summary['goals'] ?? []);
        $content .= $this->appendListSection('Constraints', $summary['constraints'] ?? []);
        $content .= $this->appendListSection('Acceptance Criteria', $summary['acceptance_criteria'] ?? []);
        $content .= $this->appendListSection('Open Questions', $summary['open_questions'] ?? []);

        file_put_contents($path, $content);

        return $path;
    }

    public function exportPlan(InterrogationSession $session): string
    {
        $directory = rtrim($session->project_directory, '/').'/docs/plans';
        $this->ensureDirectory($directory);

        $slug = $this->sessionSlug($session);
        $path = $this->nextAvailablePath($directory, $slug, 'md');

        $plan = is_array($session->plan_json) ? $session->plan_json : [];
        $markdown = (string) ($plan['plan_markdown'] ?? '');

        $content = "# Implementation Plan\n\n";
        $content .= "Derived from discovery session {$session->id}.\n\n";

        if ($markdown !== '') {
            $content .= $markdown."\n";
        }

        $content .= $this->appendListSection('Sections', $plan['sections'] ?? []);
        $content .= $this->appendListSection('Risks', $plan['risks'] ?? []);
        $content .= $this->appendListSection('Assumptions', $plan['assumptions'] ?? []);

        file_put_contents($path, $content);

        return $path;
    }

    private function ensureDirectory(string $path): void
    {
        if (! is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }

    private function sessionSlug(InterrogationSession $session): string
    {
        $base = is_string($session->name) && trim($session->name) !== ''
            ? trim($session->name)
            : 'interrogation-session-'.$session->id;

        $slug = strtolower((string) preg_replace('/[^a-z0-9]+/i', '-', $base));
        $slug = trim($slug, '-');

        return $slug !== '' ? $slug : 'interrogation-session-'.$session->id;
    }

    private function nextAvailablePath(string $directory, string $slug, string $extension): string
    {
        $candidate = $directory.'/'.$slug.'.'.$extension;

        if (! file_exists($candidate)) {
            return $candidate;
        }

        for ($i = 2; $i <= 200; $i++) {
            $candidate = $directory.'/'.$slug.'-v'.$i.'.'.$extension;
            if (! file_exists($candidate)) {
                return $candidate;
            }
        }

        return $directory.'/'.$slug.'-'.time().'.'.$extension;
    }

    /**
     * @param  mixed  $items
     */
    private function appendListSection(string $title, $items): string
    {
        if (! is_array($items) || $items === []) {
            return '';
        }

        $lines = ["\n## {$title}\n"];

        foreach ($items as $item) {
            if (! is_scalar($item)) {
                continue;
            }

            $lines[] = '- '.trim((string) $item);
        }

        $lines[] = "\n";

        return implode("\n", $lines);
    }
}
