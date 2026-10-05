<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OnboardingAssignment extends Model
{
    protected $fillable = ['organization_id', 'knowledge_item_id', 'assigned_to', 'assigned_by', 'title', 'steps'];

    protected function casts(): array
    {
        return ['steps' => 'array'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(KnowledgeItem::class, 'knowledge_item_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(OnboardingAssignmentStep::class, 'assignment_id')->orderBy('position');
    }
}
