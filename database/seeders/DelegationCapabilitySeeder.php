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
        $slugs = config('delegation.capabilities_seed', []);

        foreach ($slugs as $slug) {
            DelegationCapability::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => ucwords(str_replace('_', ' ', $slug)),
                    'is_active' => true,
                ],
            );
        }
    }
}
