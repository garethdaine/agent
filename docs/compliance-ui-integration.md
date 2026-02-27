# Compliance UI Integration Guide

## Overview

This document describes the API endpoints and response structures that frontend applications should use to render compliance status badges and indicators.

## API Endpoints

### GET /agent/api/v1/compliance/status

Returns the current compliance configuration and enabled flags.

**Authentication**: Required (Sanctum)

**Response**:
```json
{
  "enabled": true,
  "enforcement_mode": "advisory",
  "flags": {
    "compliance.plan_gate_enabled": true,
    "compliance.verification_gate_enabled": true,
    "compliance.elegance_gate_enabled": false,
    "compliance.lessons_enabled": true
  }
}
```

**Field Descriptions**:
- `enabled`: Whether the compliance layer is active
- `enforcement_mode`: Either `"advisory"` (logs only) or `"strict"` (blocks execution)
- `flags`: Object containing individual gate flags

### GET /agent/api/v1/compliance/metrics

Returns aggregated compliance telemetry for dashboard widgets.

**Authentication**: Required (Sanctum)

**Response**:
```json
{
  "period": "last_24h",
  "gate_evaluations": 0,
  "pass_rate": null,
  "block_rate": null,
  "top_block_reasons": []
}
```

**Note**: Metrics collection is not yet fully implemented. Fields return placeholder values.

## Response Extensions

### Run Responses

`GET /agent/api/v1/runs/{id}` includes a `compliance_summary` object when compliance data exists in the run's metadata:

```json
{
  "data": {
    "id": 123,
    "status": "succeeded",
    "compliance_summary": {
      "status": "pass",
      "complexity": "non_trivial",
      "category": "feature",
      "plan_required": true,
      "plan_completed": true,
      "verification_required": true,
      "verification_completed": true,
      "block_reason": null,
      "remediation": null,
      "gates": [
        {"gate": "plan", "status": "pass"},
        {"gate": "verification", "status": "pass"}
      ]
    }
  }
}
```

When a run has no compliance data, the `compliance_summary` field is omitted entirely.

### Session Responses

`GET /agent/api/v1/interrogation/sessions/{id}` may include `compliance_summary` on task metadata when compliance data exists:

```json
{
  "data": {
    "id": 456,
    "status": "completed",
    "compliance_summary": {
      "status": "blocked",
      "complexity": "non_trivial",
      "category": "bugfix",
      "plan_required": true,
      "plan_completed": true,
      "verification_required": true,
      "verification_completed": false,
      "block_reason": "missing_automated_check",
      "remediation": "Run tests and capture output.",
      "gates": [
        {"gate": "plan", "status": "pass"},
        {"gate": "verification", "status": "blocked", "reason_code": "missing_automated_check"}
      ]
    }
  }
}
```

## Badge Rendering

Frontend should render badges based on `compliance_summary.status`:

| Status | Visual | Description |
|--------|--------|-------------|
| `pass` | Green badge/checkmark | All gates passed |
| `blocked` | Red badge | Gate blocked execution, show `remediation` in tooltip |
| `advisory` | Yellow/amber badge | Warning only, execution continued |

### Badge Implementation Example (Vue)

```vue
<template>
  <span :class="badgeClass" :title="badgeTooltip">
    <icon :name="badgeIcon" />
    {{ badgeLabel }}
  </span>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  complianceSummary: Object
});

const badgeClass = computed(() => ({
  'badge-success': props.complianceSummary?.status === 'pass',
  'badge-danger': props.complianceSummary?.status === 'blocked',
  'badge-warning': props.complianceSummary?.status === 'advisory',
}));

const badgeIcon = computed(() => {
  switch (props.complianceSummary?.status) {
    case 'pass': return 'check-circle';
    case 'blocked': return 'x-circle';
    case 'advisory': return 'alert-triangle';
    default: return null;
  }
});

const badgeLabel = computed(() => props.complianceSummary?.status ?? 'Unknown');

const badgeTooltip = computed(() => {
  if (props.complianceSummary?.status === 'blocked') {
    return props.complianceSummary?.remediation ?? props.complianceSummary?.block_reason;
  }
  return null;
});
</script>
```

## Navigation Integration

### Settings Menu
Add "Compliance" link to settings/admin menu pointing to compliance dashboard:
```
Settings > Compliance
```

### Job Monitor Views
Show compliance badge on each run card in the job monitor list:
- Badge appears next to status indicator
- Clicking badge reveals gate details modal

### Build Panel
Show compliance status on each task card during build execution:
- Badge appears in task header
- Blocked tasks show remediation instructions

## Compliance Dashboard Widget

The compliance health widget should display:

1. **Pass Rate**: Percentage of gate evaluations that passed (last 24h)
2. **Top Block Reasons**: Most common reasons for blocked executions
3. **Enforcement Mode**: Current mode indicator (advisory/strict)

```vue
<ComplianceHealthWidget
  :enabled="complianceStatus.enabled"
  :mode="complianceStatus.enforcement_mode"
  :metrics="complianceMetrics"
/>
```

## Backward Compatibility

The `compliance_summary` field is an optional addition to existing API responses:
- Old clients can safely ignore this field
- No breaking changes to existing response structures
- Field is only present when compliance data exists

## Error Handling

### 401 Unauthorized
All compliance endpoints require authentication. Redirect to login if 401 received.

### Missing compliance_summary
If `compliance_summary` is absent from a run/session response, render no badge (compliance not applicable for that resource).

## API Reference

| Endpoint | Method | Auth | Description |
|----------|--------|------|-------------|
| `/agent/api/v1/compliance/status` | GET | Required | Current compliance configuration |
| `/agent/api/v1/compliance/metrics` | GET | Required | Aggregated telemetry metrics |
| `/agent/api/v1/runs/{id}` | GET | Required | Run details with optional compliance_summary |
| `/agent/api/v1/interrogation/sessions/{id}` | GET | Required | Session details with optional compliance_summary |
