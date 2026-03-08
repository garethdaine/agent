<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
