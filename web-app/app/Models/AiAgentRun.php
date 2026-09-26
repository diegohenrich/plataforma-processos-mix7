<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiAgentRun extends Model
{
    protected $fillable = [
        'organization_id', 'demand_id', 'requested_by', 'agent', 'provider', 'model',
        'input_hash', 'input_characters', 'input_tokens', 'output_tokens', 'provider_cost',
        'cost_currency', 'tool_trace', 'answer', 'status', 'error_message', 'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'tool_trace' => 'array',
            'provider_cost' => 'decimal:10',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
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
}
