<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DelegationTask;
use App\Models\DelegationTaskDependency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DelegationTaskDependency>
 */
class DelegationTaskDependencyFactory extends Factory
{
    protected $model = DelegationTaskDependency::class;

    public function definition(): array
    {
        return [
            'task_id' => DelegationTask::factory(),
            'depends_on_task_id' => DelegationTask::factory(),
        ];
    }
}
