<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KnowledgeItem extends Model
{
    protected $fillable = ['organization_id', 'created_by', 'updated_by', 'type', 'title', 'content', 'owner_name', 'audience', 'review_due_at', 'url', 'steps', 'archived_at'];

    protected function casts(): array
    {
        return ['review_due_at' => 'date', 'steps' => 'array', 'archived_at' => 'datetime'];
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

    public function assignments(): HasMany
    {
        return $this->hasMany(OnboardingAssignment::class);
    }
}
