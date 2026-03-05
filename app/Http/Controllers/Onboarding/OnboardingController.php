<?php

namespace App\Http\Controllers\Onboarding;

use App\Http\Controllers\Controller;
use App\Models\ConnectorAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    public function welcome(Request $request): Response
    {
        $connectorCount = ConnectorAccount::count();
        $connectedCount = ConnectorAccount::where('status', ConnectorAccount::STATUS_CONNECTED)->count();

        return Inertia::render('Onboarding/Welcome', [
            'hasJobs' => $request->user()->agentJobs()->exists(),
            'hasConnectors' => $connectorCount > 0,
            'hasConnectedChannel' => $connectedCount > 0,
        ]);
    }

    public function firstJob(Request $request): Response
    {
        return Inertia::render('Onboarding/FirstJob', [
            'allowedTaskBases' => config('agent.allowed_task_markdown_bases', []),
            'allowedWorkDirBases' => config('agent.allowed_working_directory_bases', []),
            'runnerTypes' => array_keys(config('agent.runner_executables', [])),
        ]);
    }

    public function complete(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user->hasCompletedOnboarding()) {
            $user->forceFill(['onboarding_completed_at' => now()])->save();
        }

        return redirect()->route('dashboard')->with('onboarding_completed', true);
    }
}
