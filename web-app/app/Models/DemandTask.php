<?php

namespace App\Models;

use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
