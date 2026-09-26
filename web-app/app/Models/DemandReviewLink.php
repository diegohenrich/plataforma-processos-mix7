<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DemandReviewLink extends Model
{
    protected $fillable = ['organization_id', 'demand_id', 'created_by', 'version', 'token_hash', 'material_url', 'material_file_path', 'material_file_name', 'material_mime', 'material_file_size', 'expires_at', 'revoked_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function demand(): BelongsTo
    {
        return $this->belongsTo(Demand::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(DemandReviewResponse::class)->oldest();
    }

    public function isAvailable(): bool
    {
        return $this->revoked_at === null && $this->expires_at->isFuture()
            && $this->demand->status->value === 'client_approval';
    }
}
