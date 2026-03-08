<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $documentation_entry_id
 * @property int|null $documentation_fragment_id
 * @property string|null $route_name
 * @property string|null $setting_key
 * @property string|null $feature_flag
 * @property \Carbon\CarbonInterface|null $created_at
 * @property \Carbon\CarbonInterface|null $updated_at
 * @property-read \App\Models\DocumentationEntry|null $entry
 * @property-read \App\Models\DocumentationFragment|null $fragment
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class DocumentationLink extends Model
{
    use HasFactory;

    protected $fillable = [
        'documentation_entry_id',
        'documentation_fragment_id',
        'route_name',
        'setting_key',
        'feature_flag',
    ];

    public function entry(): BelongsTo
    {
        return $this->belongsTo(DocumentationEntry::class, 'documentation_entry_id');
    }

    public function fragment(): BelongsTo
    {
        return $this->belongsTo(DocumentationFragment::class, 'documentation_fragment_id');
    }
}
