<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamCapacitySnapshot extends Model
{
    protected $fillable = [
        'organization_id', 'professional_id', 'recorded_by', 'week_start',
        'scheduled_minutes', 'absences', 'change_type',
    ];

    protected function casts(): array
    {
        return ['week_start' => 'immutable_date', 'absences' => 'array'];
    }

    public function professional(): BelongsTo
    {
        return $this->belongsTo(User::class, 'professional_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
