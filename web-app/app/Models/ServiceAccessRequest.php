<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceAccessRequest extends Model
{
    protected $fillable = [
        'organization_id', 'service_access_id', 'user_id', 'reviewed_by', 'status', 'active_slot', 'requested_at', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return ['requested_at' => 'datetime', 'reviewed_at' => 'datetime'];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(ServiceAccess::class, 'service_access_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
