<?php

namespace Database\Seeders;

use App\Models\DelegationCapability;
use Illuminate\Database\Seeder;

class DelegationCapabilitySeeder extends Seeder
{
    /**
     * Seed the delegation capabilities from config.
     */
    public function run(): void
    {
        $capabilities = config('delegation.capabilities_seed', [
            'code_execution',
            'review',
            'testing',
            'documentation',
            'deployment',
            'monitoring',
        ]);

        foreach ($capabilities as $slug) {
            DelegationCapability::firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => ucwords(str_replace('_', ' ', $slug)),
                    'is_active' => true,
                ]
            );
        }
    }
}
