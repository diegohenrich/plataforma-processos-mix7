<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskTimeEntry extends Model
{
    public $timestamps = false;

    protected $fillable = ['organization_id', 'task_id', 'user_id', 'started_at', 'last_heartbeat_at', 'ended_at'];

    protected function casts(): array
    {
        return ['started_at' => 'immutable_datetime', 'last_heartbeat_at' => 'immutable_datetime', 'ended_at' => 'immutable_datetime'];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(DemandTask::class, 'task_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
