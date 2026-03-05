# Code Review Organization Setup Guide

This guide walks through setting up an AI organization that autonomously reviews the codebase every hour, producing a gap analysis report covering SOLID principles, Laravel best practices, design patterns, code quality, and bugs.

---

## Prerequisites

Ensure these `.env` flags are set:

```env
ORG_LAYER_ENABLED=true
ORG_PROFILES_ENABLED=true
ORG_RITUALS_ENABLED=true
ORG_COUNCILS_ENABLED=true
ORG_COST_ENABLED=true
DELEGATION_ENABLED=true
DELEGATION_UI_ENABLED=true
AGENT_OFFICE_3D_ENABLED=true
```

Ensure delegation capabilities are seeded:

```bash
php artisan db:seed --class=DelegationCapabilitySeeder
```

---

## Step 1: Create Delegatee Profiles

Delegatee profiles define the actual runners (claude, codex, custom) that execute work. Each agent in the org needs an underlying delegatee profile.

### Via UI

1. Navigate to **https://agent.test/agent/delegatee-profiles/create**
2. For each reviewer specialty, create a profile:

| Name | Runner Type | Working Directory |
|------|-------------|-------------------|
| Code Review Lead Runner | claude | /Users/garethdaine/Code/agent |
| SOLID Reviewer Runner | claude | /Users/garethdaine/Code/agent |
| Laravel Best Practice Runner | claude | /Users/garethdaine/Code/agent |
| Design Pattern Reviewer Runner | claude | /Users/garethdaine/Code/agent |
| Adversarial Reviewer Runner | claude | /Users/garethdaine/Code/agent |
| Code Quality Reviewer Runner | claude | /Users/garethdaine/Code/agent |

3. For each profile, set the command template with appropriate review instructions
4. Assign capabilities: `code_execution`, `review`, `research`

### Via API

```bash
curl -X POST https://agent.test/agent/api/v1/delegation/delegatee-profiles \
  -H "Content-Type: application/json" \
  -H "X-Agent-Api-Version: 1.0" \
  -d '{
    "name": "SOLID Reviewer Runner",
    "runner_type": "claude",
    "command_template": "claude --print -p \"Review for SOLID violations: {{task_markdown_path}}\"",
    "working_directory": "/Users/garethdaine/Code/agent",
    "capabilities": ["code_execution", "review", "research"]
  }'
```

### Via Seeder (Automated)

```bash
php artisan db:seed --class=CodeReviewOrgSeeder
```

---

## Step 2: Create Org Agent Profiles

Org agents represent roles in the organization. Each maps to a delegatee profile.

### Via UI

1. Navigate to **https://agent.test/agent/org/agents/create**
2. Create these agents:

| Name | Role Slug | Reports To |
|------|-----------|------------|
| Engineering Lead | coordinator | (none - top of hierarchy) |
| SOLID Analyst | solid_reviewer | Engineering Lead |
| Laravel Specialist | laravel_reviewer | Engineering Lead |
| Design Pattern Expert | pattern_reviewer | Engineering Lead |
| Adversarial Reviewer | adversarial_reviewer | Engineering Lead |
| Quality Inspector | quality_reviewer | Engineering Lead |

3. For each agent:
   - Select the corresponding delegatee profile
   - Bind relevant capabilities
   - Set the parent agent (Engineering Lead for all except the lead)

### Via Org Layer Builder

1. Navigate to **https://agent.test/agent/org/builder**
2. Click **"+ Add agent"** to add each agent node
3. Configure each node with name, role, and delegatee profile
4. Draw edges from each reviewer to the Engineering Lead
5. Click **"Save"** to persist

### Via API

```bash
curl -X POST https://agent.test/agent/api/v1/org/agents \
  -H "Content-Type: application/json" \
  -H "X-Agent-Api-Version: 1.0" \
  -d '{
    "name": "Engineering Lead",
    "role_slug": "coordinator",
    "role_description": "Coordinates the code review process and produces final reports.",
    "delegatee_profile_id": "<delegatee-uuid>",
    "capability_bindings": ["code_execution", "review", "research"],
    "authority_overrides": {}
  }'
```

---

## Step 3: Set Up the Reporting Hierarchy

The hierarchy determines escalation paths and delegation routing. The Org Layer Builder (Step 2 alternative) handles this visually, but you can also do it via API.

The reporting structure for this org is flat:

```
Engineering Lead (coordinator)
├── SOLID Analyst
├── Laravel Specialist
├── Design Pattern Expert
├── Adversarial Reviewer
└── Quality Inspector
```

The hierarchy is automatically created when you set `parent_agent_id` on each agent profile, plus reporting edges are created by the seeder or the Org Layer Builder.

---

## Step 4: Create the Code Review Council

Councils enable group deliberation with voting and synthesis.

### Via UI

1. Navigate to **https://agent.test/agent/org/councils/create**
2. Set:
   - **Name:** Code Review Council
   - **Synthesis Mode:** weighted
   - **Members:** All 6 agents
   - **Chair:** Engineering Lead (weight: 2.0)
   - **Adversarial Reviewer weight:** 1.5
   - **Others weight:** 1.0
3. Enable model synthesis for AI-assisted decision making
4. Set report sections: executive_summary, critical_findings, solid_violations, laravel_anti_patterns, design_pattern_issues, bugs_and_security, code_quality_metrics, adversarial_notes, recommendations

### Via API

```bash
curl -X POST https://agent.test/agent/api/v1/org/councils \
  -H "Content-Type: application/json" \
  -H "X-Agent-Api-Version: 1.0" \
  -d '{
    "name": "Code Review Council",
    "description": "Deliberation council for code review findings.",
    "synthesis_mode": "weighted",
    "use_model_synthesis": true,
    "member_list": [
      {"agent_id": "<lead-uuid>", "name": "Engineering Lead", "role": "coordinator", "weight": 2.0, "is_chair": true},
      {"agent_id": "<solid-uuid>", "name": "SOLID Analyst", "role": "solid_reviewer", "weight": 1.0, "is_chair": false},
      ...
    ],
    "report_sections": ["executive_summary", "critical_findings", "recommendations"]
  }'
```

---

## Step 5: Create the Ritual Template

Rituals are scheduled workflows that use the delegation engine.

### Via UI

1. Navigate to **https://agent.test/agent/org/rituals/create**
2. Set:
   - **Name:** Hourly Codebase Review
   - **Cron Expression:** `0 * * * *` (every hour on the hour)
   - **Timezone:** Pacific/Auckland
3. Define the phase graph (DAG):

```
analyze_solid ─────────────┐
analyze_laravel ───────────┤
analyze_patterns ──────────┼─→ adversarial_review → synthesize_report
analyze_quality ───────────┘
```

4. Map phases to roles:
   - `analyze_solid` → `solid_reviewer`
   - `analyze_laravel` → `laravel_reviewer`
   - `analyze_patterns` → `pattern_reviewer`
   - `analyze_quality` → `quality_reviewer`
   - `adversarial_review` → `adversarial_reviewer`
   - `synthesize_report` → `coordinator`

5. Set context inputs:
   - `target_directory`: `/Users/garethdaine/Code/agent`
   - `report_output_directory`: `/Users/garethdaine/Code/agent/docs/review`

### Via API

```bash
curl -X POST https://agent.test/agent/api/v1/org/rituals \
  -H "Content-Type: application/json" \
  -H "X-Agent-Api-Version: 1.0" \
  -d '{
    "name": "Hourly Codebase Review",
    "cron_expression": "0 * * * *",
    "timezone": "Pacific/Auckland",
    "phase_graph": [
      {"id": "analyze_solid", "name": "SOLID Analysis", "depends_on": []},
      {"id": "analyze_laravel", "name": "Laravel Best Practice Review", "depends_on": []},
      {"id": "analyze_patterns", "name": "Design Pattern Review", "depends_on": []},
      {"id": "analyze_quality", "name": "Code Quality Review", "depends_on": []},
      {"id": "adversarial_review", "name": "Adversarial Review", "depends_on": ["analyze_solid", "analyze_laravel", "analyze_patterns", "analyze_quality"]},
      {"id": "synthesize_report", "name": "Report Synthesis", "depends_on": ["adversarial_review"]}
    ],
    "phase_role_mappings": {
      "analyze_solid": "solid_reviewer",
      "analyze_laravel": "laravel_reviewer",
      "analyze_patterns": "pattern_reviewer",
      "analyze_quality": "quality_reviewer",
      "adversarial_review": "adversarial_reviewer",
      "synthesize_report": "coordinator"
    },
    "context_inputs": {
      "target_directory": "/Users/garethdaine/Code/agent",
      "report_output_directory": "/Users/garethdaine/Code/agent/docs/review"
    },
    "verification_strategy": "ai_critic",
    "notification_level": "all"
  }'
```

---

## Step 6: Trigger a Manual Run

### Via UI

1. Navigate to **https://agent.test/agent/org/rituals**
2. Click on "Hourly Codebase Review"
3. Click **"Run Now"**

### Via API

```bash
curl -X POST https://agent.test/agent/api/v1/org/rituals/<ritual-uuid>/run \
  -H "X-Agent-Api-Version: 1.0"
```

---

## Step 7: Monitor in the Agent Office

1. Navigate to **https://agent.test/agent/office**
2. When a ritual runs, you'll see:
   - **Engineering Lead** moves to the Conference Room to coordinate
   - **Specialist reviewers** (SOLID, Laravel, Pattern, Quality) work at their workstations in parallel
   - **Adversarial Reviewer** moves to the War Room once specialists finish
   - All agents move to the Conference Room for council deliberation
   - Engineering Lead produces the final report

### Zone Activity Mapping

| Zone | Activity |
|------|----------|
| Workstations | Individual review work (writing_code) |
| Conference Room | Council deliberation (chatting), report coordination |
| War Room | Adversarial review (reading/writing_code) |
| Escalation | Failed ritual handling |
| Archives | Memory formation from findings |
| Break Room | Idle agents between runs |

---

## Step 8: Check Output Reports

Reports are generated at:

```
/Users/garethdaine/Code/agent/docs/review/
```

Each run produces findings that feed into the report template.

---

## Automated Scheduling

The ritual runs automatically every hour via `OrgDispatchDueRitualsJob`. For this to work:

1. **Horizon must be running:** `php artisan horizon`
2. **Scheduler must be running:** `php artisan schedule:work`
3. **ORG_LAYER_ENABLED must be true** in `.env`

The scheduler checks all active, non-paused ritual templates every minute. When a template's cron expression indicates it's due, it dispatches `OrgExecuteRitualJob` which:

1. Creates a ritual run
2. Maps phase graph to delegation tasks via `RitualToDelegationMapper`
3. Builds a `DelegationGraph` with the task DAG
4. Transitions the graph to `ready` state
5. Broadcasts activity events to the Agent Office

---

## Architecture Overview

```
OrgRitualTemplate (cron: 0 * * * *)
    │
    ├─ OrgDispatchDueRitualsJob (every minute)
    │   └─ OrgExecuteRitualJob
    │       ├─ RitualToDelegationMapper → maps phases to tasks
    │       ├─ DelegationGraphBuilder → creates DAG
    │       └─ GraphStateTransitionService → starts execution
    │
    ├─ DelegationGraph
    │   ├─ Task: SOLID Analysis (parallel)
    │   ├─ Task: Laravel Review (parallel)
    │   ├─ Task: Pattern Review (parallel)
    │   ├─ Task: Quality Review (parallel)
    │   ├─ Task: Adversarial Review (depends on above 4)
    │   └─ Task: Report Synthesis (depends on adversarial)
    │
    ├─ OrgCouncilTemplate (Code Review Council)
    │   └─ Weighted synthesis with Engineering Lead as chair
    │
    └─ AgentActivityChanged events → Agent Office
        └─ Real-time avatar movement and zone transitions
```
