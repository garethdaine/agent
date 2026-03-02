<?php

declare(strict_types=1);

namespace App\Support\RepoAnalysis;

use App\Models\RepoAnalysisArtifact;
use App\Models\RepoAnalysisSession;
use Carbon\CarbonImmutable;

class ReportComposer
{
    /**
     * @param  array<string, mixed>  $coverageSummary
     * @return array{
     *   report_version: string,
     *   report_hash: string,
     *   payload_json: array<string, mixed>,
     *   metadata_json: array<string, mixed>,
     *   payload: array<int, array{artifact_key: string, artifact_type: string, content_hash: string}>
     * }
     */
    public function compose(RepoAnalysisSession $session, array $coverageSummary): array
    {
        $artifacts = RepoAnalysisArtifact::query()
            ->where('repo_analysis_session_id', $session->id)
            ->select(['artifact_key', 'artifact_type', 'content_hash'])
            ->orderBy('artifact_key')
            ->get()
            ->map(static fn ($artifact): array => [
                'artifact_key' => (string) $artifact->artifact_key,
                'artifact_type' => (string) $artifact->artifact_type,
                'content_hash' => (string) $artifact->content_hash,
            ])
            ->all();

        $hashInput = [
            'snapshot_hash' => (string) $session->snapshot_hash,
            'artifacts' => array_map(static fn (array $artifact): array => [
                'artifact_key' => $artifact['artifact_key'],
                'content_hash' => $artifact['content_hash'],
            ], $artifacts),
        ];

        $reportHash = hash('sha256', $this->canonicalJson($hashInput));

        $payload = [
            'session_id' => $session->id,
            'snapshot_hash' => (string) $session->snapshot_hash,
            'artifact_count' => count($artifacts),
            'artifacts' => $artifacts,
            'coverage' => $coverageSummary,
            'generated_at' => CarbonImmutable::now('UTC')->toIso8601String(),
        ];

        return [
            'report_version' => '1.0.0',
            'report_hash' => $reportHash,
            'payload_json' => $payload,
            'metadata_json' => [
                'artifact_hash_count' => count($artifacts),
                'hash_input' => $hashInput,
            ],
            'payload' => $artifacts,
        ];
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function canonicalJson(array $value): string
    {
        $normalized = $this->normalize($value);

        return json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    /**
     * @return mixed
     */
    private function normalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if ($this->isList($value)) {
            return array_map(fn (mixed $entry): mixed => $this->normalize($entry), $value);
        }

        ksort($value, SORT_STRING);
        foreach ($value as $key => $entry) {
            $value[$key] = $this->normalize($entry);
        }

        return $value;
    }

    private function isList(array $value): bool
    {
        if ($value === []) {
            return true;
        }

        return array_keys($value) === range(0, count($value) - 1);
    }
}
