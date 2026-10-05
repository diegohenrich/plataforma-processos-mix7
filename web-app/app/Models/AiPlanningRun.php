<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiPlanningRun extends Model
{
    protected $fillable = [
        'organization_id', 'demand_id', 'requested_by', 'reviewed_by', 'provider', 'model',
        'input_hash', 'input_characters', 'input_tokens', 'output_tokens', 'proposal',
        'reviewed_tasks', 'status', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'proposal' => 'array',
            'reviewed_tasks' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function demand(): BelongsTo
    {
        return $this->belongsTo(Demand::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
