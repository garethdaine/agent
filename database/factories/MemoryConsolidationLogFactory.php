<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\MemoryConsolidationLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MemoryConsolidationLog>
 */
class MemoryConsolidationLogFactory extends Factory
{
    protected $model = MemoryConsolidationLog::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'consolidation_type' => $this->faker->randomElement([
                MemoryConsolidationLog::TYPE_WORKING_TO_CORE,
                MemoryConsolidationLog::TYPE_VECTOR_DEDUP,
                MemoryConsolidationLog::TYPE_FAILURE_BACKFILL,
                MemoryConsolidationLog::TYPE_GRAPH_CLEANUP,
                MemoryConsolidationLog::TYPE_PRUNE,
            ]),
            'source_count' => $this->faker->numberBetween(1, 500),
            'result_summary' => $this->faker->sentence(),
            'checkpoint_json' => null,
            'created_at' => now(),
        ];
    }
}
