<?php

namespace App\Models;

use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DemandTask extends Model
{
    use HasFactory;

    protected $fillable = ['organization_id', 'demand_id', 'created_by', 'assigned_to', 'title', 'description', 'status', 'estimate_minutes'];

    protected function casts(): array
    {
        return ['status' => TaskStatus::class];
    }

    public function demand(): BelongsTo
    {
        return $this->belongsTo(Demand::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TaskTimeEntry::class, 'task_id');
    }

    public function dependencies(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'demand_task_dependencies', 'task_id', 'depends_on_task_id');
    }

    public function dependents(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'demand_task_dependencies', 'depends_on_task_id', 'task_id');
    }
}
