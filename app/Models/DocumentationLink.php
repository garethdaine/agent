<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentationLink extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function entry(): BelongsTo
    {
        return $this->belongsTo(DocumentationEntry::class, 'documentation_entry_id');
    }

    public function fragment(): BelongsTo
    {
        return $this->belongsTo(DocumentationFragment::class, 'documentation_fragment_id');
    }
}
