<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PerformanceReview extends Model
{
    protected $fillable = ['organization_id', 'task_id', 'professional_id', 'reviewer_id', 'reviewer_role', 'reviewer_weight', 'deadline_assessment', 'quality_assessment', 'evidence', 'external_factors'];

    public function task(): BelongsTo
    {
        return $this->belongsTo(DemandTask::class, 'task_id');
    }

    public function professional(): BelongsTo
    {
        return $this->belongsTo(User::class, 'professional_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(PerformanceReviewResponse::class)->latest();
    }
}
