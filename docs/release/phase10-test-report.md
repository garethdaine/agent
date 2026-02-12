# Phase 10 Acceptance Test Report

Date (UTC): 2026-02-12

## Scope
This report records the required Phase 10 acceptance checks from the signed baseline:
- DST spring-forward skip
- DST fall-back double-window
- overlap/cooldown precedence
- delayed scheduler bounded backfill
- stop/timeouts races and idempotent stop
- duplicate dispatch idempotency
- soft-delete/restore/hard-prune paths
- endpoint status-code coverage (`200/201/202/401/404/409/422/429/503`)

## Test Commands and Outcomes
- `php artisan test tests/Feature/AgentDispatchDueCommandTest.php`
  - PASS (9 tests)
  - Covers DST spring-forward skip, DST fall-back double-window, overlap/cooldown precedence, delayed bounded backfill, duplicate dispatch idempotency.
  - Raw log: `docs/release/logs/phase10-agent-dispatch.log`
- `php artisan test tests/Feature/AgentRunnerLifecycleTest.php`
  - PASS (4 tests)
  - Covers timeout lifecycle and run-terminal behavior.
  - Raw log: `docs/release/logs/phase10-agent-runner-lifecycle.log`
- `php artisan test tests/Feature/AgentApiWorkflowTest.php`
  - PASS (7 tests)
  - Covers idempotent stop and core API workflow contracts.
  - Raw log: `docs/release/logs/phase10-agent-api-workflow.log`
- `php artisan test tests/Feature/AgentMaintenancePruneTest.php`
  - PASS (4 tests)
  - Covers soft-delete/restore/hard-prune and retention pruning behavior.
  - Raw log: `docs/release/logs/phase10-agent-maintenance-prune.log`
- `php artisan test tests/Feature/AgentApiContractCoverageTest.php`
  - PASS (4 tests)
  - Covers endpoint code matrix including `401`, `409`, `422`, `429`, `503`.
  - Raw log: `docs/release/logs/phase10-agent-api-contracts.log`
- Consolidated suite:
  - `php artisan test tests/Feature/AgentDispatchDueCommandTest.php tests/Feature/AgentRunnerLifecycleTest.php tests/Feature/AgentApiWorkflowTest.php tests/Feature/AgentMaintenancePruneTest.php tests/Feature/AgentApiContractCoverageTest.php`
  - PASS (28 tests, 118 assertions)
  - Raw log: `docs/release/logs/phase10-acceptance-suite.log`

## Runtime Command Artifacts
- `php artisan agent:dispatch-due`
- `php artisan agent:prune --runs --events --audit --jobs --dry-run --json`
- Raw log: `docs/release/logs/phase10-runtime.log`

## UI Screenshot Artifacts
- `docs/release/screenshots/phase10-dashboard.png`
- `docs/release/screenshots/phase10-monitor.png`

## Notes
- Scheduler due evaluation now enforces exact minute/hour cron field matching after timezone conversion to prevent DST spring-forward shifted dispatches.
