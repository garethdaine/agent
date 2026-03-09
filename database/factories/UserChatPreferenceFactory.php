<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Models\UserChatPreference;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserChatPreference>
 */
class UserChatPreferenceFactory extends Factory
{
    protected $model = UserChatPreference::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'require_confirmation_for_delete' => true,
            'require_confirmation_for_stop' => true,
            'require_confirmation_for_steer' => false,
        ];
    }
}
