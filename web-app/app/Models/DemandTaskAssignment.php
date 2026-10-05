<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DemandTaskAssignment extends Model
{
    protected $fillable = [
        'organization_id',
        'demand_task_id',
        'professional_id',
        'assigned_by',
        'assigned_at',
        'accepted_at',
        'completed_at',
        'released_at',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'immutable_datetime',
            'accepted_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'released_at' => 'immutable_datetime',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(DemandTask::class, 'demand_task_id');
    }

    public function professional(): BelongsTo
    {
        return $this->belongsTo(User::class, 'professional_id');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
