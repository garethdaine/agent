# Autonomous Feature Delivery

You are running inside `/Users/garethdaine/Code/agent`.

## Objective
On each run, deliver exactly one feature from:
`/Users/garethdaine/Code/agent/storage/app/public/features.xlsx`

Workflow for the selected feature:
1. Analyze the existing codebase and design the implementation.
2. Create a concrete task list.
3. Implement the feature end-to-end.
4. Fully test the feature.
5. Write a delivery summary to `/Users/garethdaine/Code/agent/docs`.
6. Mark that single feature as complete in the spreadsheet, with strict safety checks.

## Hard Rules (Do Not Violate)
- Modify at most one feature row per run.
- In the spreadsheet, modify exactly one cell only: the completion/status cell for the selected feature row.
- Do not alter any other cell value, formula, style, width, sheet name, ordering, filters, or metadata.
- Never mark a feature complete unless implementation is done and tests are passing.
- If any safety check fails, do not save spreadsheet changes; keep/restore original.
- Never run destructive database commands (`migrate:fresh`, `migrate:refresh`, `db:wipe`, `schema:drop`, manual `DROP`/`TRUNCATE`) in any environment.

## Feature Selection
1. Open `/Users/garethdaine/Code/agent/storage/app/public/features.xlsx`.
2. Use sheet: `Novel Feature Suggestions`.
3. Locate a dedicated completion column using one of these exact headers only:
   - `Implementation Status`
   - `Build Status`
   - `Status`
   - `Completed`
4. Never treat `MVP Buildable` as completion state.
5. If no dedicated completion column exists, stop and write a blocker summary to `/Users/garethdaine/Code/agent/docs` (no spreadsheet edits).
6. From incomplete rows, pick exactly one feature using:
   - Highest priority first (`High` > `Medium` > `Low`)
   - Then lowest numeric ID (e.g. `F-001` before `F-002`)

## Implementation Process
1. Read the chosen feature row fully (problem, value, implementation notes, target users).
2. Analyze relevant existing code paths before coding.
3. Create a concise execution checklist in the summary under a "Task List" section.
4. Implement code changes with Laravel best practices and existing project conventions.
5. Run quality checks and tests:
   - `./vendor/bin/pint`
   - `composer test`
   - If frontend/build-sensitive files changed, also run: `npm run build`
6. If any required check fails, fix issues or stop without updating spreadsheet completion.

## Required Summary Output
Create one markdown file in `/Users/garethdaine/Code/agent/docs` named:
`feature-delivery-<feature-id>-<YYYYMMDD-HHMMSS>.md`

Include:
- Feature ID and name
- What was implemented
- Task List (checked/unchecked steps)
- Files changed
- Test commands run and pass/fail results
- Known limitations/follow-ups
- Spreadsheet update result (success/failure)

## Spreadsheet Update Safety Protocol (Mandatory)
Only after implementation + tests pass:
1. Create backup copy:
   - `/Users/garethdaine/Code/agent/storage/app/public/features.backup.<YYYYMMDD-HHMMSS>.xlsx`
2. Update only the selected feature's completion/status cell to exactly: `Complete`
3. Save workbook.
4. Re-open both backup and updated files.
5. Verify a strict diff:
   - Exactly one cell changed across workbook.
   - Changed cell is the intended completion/status cell for the selected row.
   - New value is `Complete`.
6. If verification fails:
   - Restore original from backup.
   - Record failure in summary.
   - Stop.
7. If verification succeeds:
   - Delete backup file.
   - Record success in summary.

## No-Work Condition
If all features are already complete:
- Do not change spreadsheet.
- Write a short "no pending features" summary file in `/Users/garethdaine/Code/agent/docs`.

## Final Output Contract
At run end, produce:
1. Implemented feature changes (or clear blocker/no-op result).
2. Summary markdown in `/Users/garethdaine/Code/agent/docs`.
3. Spreadsheet completion update only when all checks passed.
