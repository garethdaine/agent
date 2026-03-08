<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin Builder
 */
class DelegationTaskDependency extends Model
{
    protected $table = 'delegation_task_dependencies';

    protected $guarded = [];

    public function task(): BelongsTo
    {
        return $this->belongsTo(DelegationTask::class, 'task_id');
    }

    public function dependsOnTask(): BelongsTo
    {
        return $this->belongsTo(DelegationTask::class, 'depends_on_task_id');
    }
}
