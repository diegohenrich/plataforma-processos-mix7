<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DemandEvent extends Model
{
    public $timestamps = false;

    protected $fillable = ['organization_id', 'demand_id', 'task_id', 'actor_id', 'event_type', 'summary', 'from_status', 'to_status', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(DemandTask::class);
    }
}
