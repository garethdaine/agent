<?php

declare(strict_types=1);

namespace App\Console\Commands\Skills;

use App\Services\Skills\SkillParser;
use Illuminate\Console\Command;
use ZipArchive;

class SkillBuildLibraryCommand extends Command
{
    protected $signature = 'skill:build-library
        {--skip-validation : Skip parse validation (build archives only)}';

    protected $description = 'Build .skill archives and manifest.json from skill-library/skills/ source directories';

    public function handle(SkillParser $parser): int
    {
        $skillsDir = base_path('skill-library/skills');
        $distDir = base_path('skill-library/dist');

        if (! is_dir($skillsDir)) {
            $this->error("Skills source directory not found: {$skillsDir}");

            return self::FAILURE;
        }

        if (! is_dir($distDir)) {
            mkdir($distDir, 0755, true);
        }

        $skipValidation = (bool) $this->option('skip-validation');
        $entries = [];
        $errors = [];

        $dirs = array_filter(
            array_diff(scandir($skillsDir), ['.', '..']),
            fn (string $name) => is_dir($skillsDir.'/'.$name)
        );

        sort($dirs);
        $this->info(sprintf('Found %d skill directories.', count($dirs)));

        foreach ($dirs as $slug) {
            $skillDir = $skillsDir.'/'.$slug;
            $skillMdPath = $skillDir.'/SKILL.md';

            if (! file_exists($skillMdPath)) {
                $this->warn("  [{$slug}] Missing SKILL.md — skipped.");
                $errors[] = $slug;

                continue;
            }

            $content = file_get_contents($skillMdPath);

            if ($content === false) {
                $this->warn("  [{$slug}] Could not read SKILL.md — skipped.");
                $errors[] = $slug;

                continue;
            }

            // Parse
            $parsed = $parser->parse($content);

            if (! $skipValidation && $parsed->hasErrors()) {
                $this->warn("  [{$slug}] Validation errors:");
                foreach ($parsed->errors as $error) {
                    $this->warn("    - {$error}");
                }
                $errors[] = $slug;

                continue;
            }

            // Build ZIP archive
            $archivePath = $distDir.'/'.$slug.'.skill';
            $zip = new ZipArchive;

            if ($zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                $this->warn("  [{$slug}] Failed to create ZIP archive — skipped.");
                $errors[] = $slug;

                continue;
            }

            $this->addDirectoryToZip($zip, $skillDir, '');
            $zip->close();

            // Compute checksum
            $checksum = 'sha256:'.hash_file('sha256', $archivePath);

            // Extract metadata for manifest
            $xAgent = $parsed->rawFrontmatter['x-agent'] ?? [];

            $category = $this->inferCategory($slug, $xAgent['industries'] ?? $parsed->industries);

            $entries[] = [
                'slug' => $slug,
                'name' => $this->slugToTitle($parsed->name ?? $slug),
                'description' => trim($parsed->description ?? ''),
                'version' => $parsed->version ?? '1.0.0',
                'author' => $parsed->author ?? 'agentops',
                'category' => $category,
                'industries' => $parsed->industries,
                'risk_level' => $parsed->riskLevel,
                'file' => 'dist/'.$slug.'.skill',
                'checksum' => $checksum,
            ];

            $this->info("  [{$slug}] OK — {$checksum}");
        }

        // Write manifest
        $manifest = [
            'version' => '1.0.0',
            'generated_at' => now()->toIso8601String(),
            'skills' => $entries,
        ];

        $manifestPath = base_path('skill-library/manifest.json');
        file_put_contents(
            $manifestPath,
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n"
        );

        $this->newLine();
        $this->info(sprintf(
            'Manifest written with %d skills to %s',
            count($entries),
            $manifestPath
        ));

        if (! empty($errors)) {
            $this->warn(sprintf('%d skill(s) had errors: %s', count($errors), implode(', ', $errors)));

            return self::FAILURE;
        }

        $this->info('All skills built successfully.');

        return self::SUCCESS;
    }

    private function addDirectoryToZip(ZipArchive $zip, string $dir, string $prefix): void
    {
        $items = array_diff(scandir($dir), ['.', '..']);

        foreach ($items as $item) {
            $fullPath = $dir.'/'.$item;
            $zipPath = $prefix === '' ? $item : $prefix.'/'.$item;

            if (is_dir($fullPath)) {
                $zip->addEmptyDir($zipPath);
                $this->addDirectoryToZip($zip, $fullPath, $zipPath);
            } else {
                $zip->addFile($fullPath, $zipPath);
            }
        }
    }

    private function inferCategory(string $slug, array $industries): string
    {
        $complianceSlugs = [
            'financial-compliance-check', 'care-compliance-checker', 'compliance-inspection-reporter',
            'supplier-compliance-monitor', 'placement-compliance-checker', 'tenancy-compliance-checker',
            'food-safety-compliance',
        ];

        if (in_array($slug, $complianceSlugs, true)) {
            return 'compliance';
        }

        $industryToCategory = [
            'financial-services' => 'financial',
            'accounting' => 'financial',
            'insurance' => 'financial',
            'legal' => 'legal',
            'healthcare' => 'healthcare',
            'social-care' => 'healthcare',
            'facilities-management' => 'facilities',
            'construction' => 'facilities',
            'waste-management' => 'facilities',
            'logistics' => 'logistics',
            'supply-chain' => 'logistics',
            'recruitment' => 'recruitment',
            'professional-services' => 'recruitment',
            'property' => 'property',
            'real-estate' => 'property',
            'food-manufacturing' => 'manufacturing',
            'manufacturing' => 'manufacturing',
            'catering' => 'manufacturing',
            'retail' => 'manufacturing',
            'cross-industry' => 'cross-industry',
        ];

        foreach ($industries as $industry) {
            if (isset($industryToCategory[$industry])) {
                return $industryToCategory[$industry];
            }
        }

        return 'cross-industry';
    }

    private function slugToTitle(string $slug): string
    {
        $words = explode('-', $slug);
        $acronyms = ['pii', 'sop', 'nda', 'fca', 'sox', 'gdpr', 'cqc', 'haccp', 'ewc', 'ir35', 'awr', 'epc', 'sla'];

        return implode(' ', array_map(function (string $word) use ($acronyms) {
            if (in_array(strtolower($word), $acronyms, true)) {
                return strtoupper($word);
            }

            return ucfirst($word);
        }, $words));
    }
}
