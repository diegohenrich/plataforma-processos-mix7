<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DemandModuleStep extends Model
{
    protected $fillable = ['organization_id', 'demand_id', 'key', 'label', 'position', 'completed_by', 'completed_at'];

    protected function casts(): array
    {
        return ['position' => 'integer', 'completed_at' => 'datetime'];
    }

    public function demand(): BelongsTo
    {
        return $this->belongsTo(Demand::class);
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
