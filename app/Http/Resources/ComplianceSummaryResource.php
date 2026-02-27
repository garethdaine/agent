<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ComplianceSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if (empty($this->resource)) {
            return [];
        }

        return [
            'status' => $this->resource['compliance_status'] ?? 'pass',
            'complexity' => $this->resource['complexity_classification'] ?? null,
            'category' => $this->resource['task_category'] ?? null,
            'plan_required' => $this->resource['plan_required'] ?? false,
            'plan_completed' => $this->resource['plan_completed'] ?? false,
            'verification_required' => $this->resource['verification_required'] ?? false,
            'verification_completed' => $this->resource['verification_completed'] ?? false,
            'block_reason' => $this->resource['compliance_block_reason'] ?? null,
            'remediation' => $this->resource['compliance_remediation'] ?? null,
            'gates' => $this->resource['compliance_gates'] ?? [],
        ];
    }
}
