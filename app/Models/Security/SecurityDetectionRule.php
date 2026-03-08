<?php

declare(strict_types=1);

namespace App\Models\Security;

use App\Enums\Security\DetectionPatternType;
use App\Enums\Security\InjectionSeverity;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SecurityDetectionRule extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'pattern',
        'pattern_type',
        'severity',
        'weight',
        'enabled',
    ];

    protected static function newFactory(): \Database\Factories\SecurityDetectionRuleFactory
    {
        return \Database\Factories\SecurityDetectionRuleFactory::new();
    }

    protected function casts(): array
    {
        return [
            'pattern_type' => DetectionPatternType::class,
            'severity' => InjectionSeverity::class,
            'weight' => 'float',
            'enabled' => 'boolean',
        ];
    }
}
