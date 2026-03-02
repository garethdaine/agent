<?php

namespace App\Support\Org;

use App\Models\OrgRitualRun;
use App\Models\OrgRitualTemplate;
use Illuminate\Support\Str;

/**
 * Service for managing OrgRitualRun lifecycle.
 *
 * Responsibilities:
 * - Create new runs from templates
 * - Manage run state transitions (queued -> running -> succeeded/failed/partial)
 * - Persist phase outputs as phases complete
 * - Support partial retry by preserving successful phase outputs
 */
class OrgRitualRunService
{
    /**
     * Create a new ritual run from a template.
     *
     * Initializes the run in 'queued' state with a new correlation ID.
     * The run inherits the user_id from the template.
     *
     * @param  OrgRitualTemplate  $template  The template to create a run from
     * @return OrgRitualRun The newly created run
     */
    public function createRun(OrgRitualTemplate $template): OrgRitualRun
    {
        return OrgRitualRun::create([
            'ritual_template_id' => $template->id,
            'user_id' => $template->user_id,
            'state' => OrgRitualRun::STATE_QUEUED,
            'phase_outputs' => [],
            'correlation_id' => Str::uuid()->toString(),
        ]);
    }

    /**
     * Mark a phase as completed and persist its output.
     *
     * Merges the new phase output into the existing phase_outputs array.
     * This preserves outputs from previously completed phases.
     *
     * @param  OrgRitualRun  $run  The run to update
     * @param  string  $phaseId  The ID of the completed phase
     * @param  array  $output  The output data from the phase
     */
    public function completePhase(OrgRitualRun $run, string $phaseId, array $output): void
    {
        $outputs = $run->phase_outputs ?? [];
        $outputs[$phaseId] = $output;

        $run->update(['phase_outputs' => $outputs]);
    }

    /**
     * Retry failed phases from a partial run.
     *
     * Creates a new run that preserves successful phase outputs from the
     * original run. Only phases with non-null outputs are considered
     * successful and preserved.
     *
     * @param  OrgRitualRun  $originalRun  The partial run to retry from
     * @return OrgRitualRun The new run with preserved outputs
     */
    public function retryFailedPhases(OrgRitualRun $originalRun): OrgRitualRun
    {
        // Filter phase_outputs to only include successful (non-null) outputs
        $preservedOutputs = array_filter(
            $originalRun->phase_outputs ?? [],
            fn ($output) => $output !== null
        );

        return OrgRitualRun::create([
            'ritual_template_id' => $originalRun->ritual_template_id,
            'user_id' => $originalRun->user_id,
            'state' => OrgRitualRun::STATE_QUEUED,
            'phase_outputs' => $preservedOutputs,
            'correlation_id' => Str::uuid()->toString(),
        ]);
    }

    /**
     * Mark a run as running.
     *
     * Sets the state to 'running' and records the start time.
     *
     * @param  OrgRitualRun  $run  The run to start
     */
    public function markRunning(OrgRitualRun $run): void
    {
        $run->update([
            'state' => OrgRitualRun::STATE_RUNNING,
            'started_at' => now(),
        ]);
    }

    /**
     * Mark a run as succeeded.
     *
     * Sets the state to 'succeeded' and records the completion time.
     *
     * @param  OrgRitualRun  $run  The run to mark as succeeded
     */
    public function markSucceeded(OrgRitualRun $run): void
    {
        $run->update([
            'state' => OrgRitualRun::STATE_SUCCEEDED,
            'completed_at' => now(),
        ]);
    }

    /**
     * Mark a run as failed.
     *
     * Sets the state to 'failed' and records the completion time.
     *
     * @param  OrgRitualRun  $run  The run to mark as failed
     */
    public function markFailed(OrgRitualRun $run): void
    {
        $run->update([
            'state' => OrgRitualRun::STATE_FAILED,
            'completed_at' => now(),
        ]);
    }

    /**
     * Mark a run as partial.
     *
     * Sets the state to 'partial' and records the completion time.
     * A partial run has some successful phases and some failed phases.
     *
     * @param  OrgRitualRun  $run  The run to mark as partial
     */
    public function markPartial(OrgRitualRun $run): void
    {
        $run->update([
            'state' => OrgRitualRun::STATE_PARTIAL,
            'completed_at' => now(),
        ]);
    }
}
