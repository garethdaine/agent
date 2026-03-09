<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $task_id
 * @property int $depends_on_task_id
 * @property \Carbon\CarbonInterface|null $created_at
 * @property \Carbon\CarbonInterface|null $updated_at
 * @property-read \App\Models\DelegationTask|null $task
 * @property-read \App\Models\DelegationTask|null $dependsOnTask
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class DelegationTaskDependency extends Model
{
    use HasFactory;

    protected $table = 'delegation_task_dependencies';

    protected $fillable = [
        'task_id',
        'depends_on_task_id',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(DelegationTask::class, 'task_id');
    }

    public function dependsOnTask(): BelongsTo
    {
        return $this->belongsTo(DelegationTask::class, 'depends_on_task_id');
    }
}
