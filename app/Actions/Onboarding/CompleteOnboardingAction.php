<?php

declare(strict_types=1);

namespace App\Actions\Onboarding;

use App\Models\User;

class CompleteOnboardingAction
{
    public function execute(User $user): void
    {
        if (! $user->hasCompletedOnboarding()) {
            $user->forceFill(['onboarding_completed_at' => now()])->save();
        }
    }
}
