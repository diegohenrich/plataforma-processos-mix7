<?php

namespace App\Models;

use App\Enums\DemandStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Demand extends Model
{
    use HasFactory;

    protected $fillable = ['organization_id', 'created_by', 'client_user_id', 'title', 'brief', 'status'];

    protected function casts(): array
    {
        return ['status' => DemandStatus::class];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_user_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(DemandTask::class)->orderBy('created_at');
    }

    public function events(): HasMany
    {
        return $this->hasMany(DemandEvent::class)->latest();
    }

    public function reviewLinks(): HasMany
    {
        return $this->hasMany(DemandReviewLink::class)->orderByDesc('version');
    }

    public function aiPlanningRuns(): HasMany
    {
        return $this->hasMany(AiPlanningRun::class)->latest();
    }

    public function aiAgentRuns(): HasMany
    {
        return $this->hasMany(AiAgentRun::class)->latest();
    }
}
