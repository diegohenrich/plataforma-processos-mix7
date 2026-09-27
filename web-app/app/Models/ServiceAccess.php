<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceAccess extends Model
{
    protected $fillable = [
        'organization_id', 'created_by', 'updated_by', 'name', 'service_url', 'access_method',
        'instructions', 'review_due_on', 'archived_at',
    ];

    protected function casts(): array
    {
        return ['review_due_on' => 'date', 'archived_at' => 'datetime'];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function requests(): HasMany
    {
        return $this->hasMany(ServiceAccessRequest::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ServiceAccessEvent::class);
    }
}
