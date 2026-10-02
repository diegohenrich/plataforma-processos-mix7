<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceAccessEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'organization_id', 'service_access_id', 'service_access_request_id', 'actor_id', 'event_type', 'created_at',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(ServiceAccessRequest::class, 'service_access_request_id');
    }
}
