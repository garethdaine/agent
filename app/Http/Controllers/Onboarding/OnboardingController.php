<?php

declare(strict_types=1);

namespace App\Http\Controllers\Onboarding;

use App\Actions\Onboarding\CompleteOnboardingAction;
use App\Actions\Onboarding\GetOnboardingStateAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    public function __construct(
        private readonly GetOnboardingStateAction $getOnboardingState,
        private readonly CompleteOnboardingAction $completeOnboarding,
    ) {}

    public function welcome(Request $request): Response
    {
        $state = $this->getOnboardingState->execute();

        return Inertia::render('Onboarding/Welcome', [
            'hasJobs' => $request->user()->agentJobs()->exists(),
            'hasConnectors' => $state['has_connectors'],
            'hasConnectedChannel' => $state['has_connected_channel'],
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
        $this->completeOnboarding->execute($request->user());

        return redirect()->route('dashboard')->with('onboarding_completed', true);
    }
}
