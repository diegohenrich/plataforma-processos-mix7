<?php

namespace App\Models;

use App\Enums\DemandModule;
use App\Enums\DemandStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Demand extends Model
{
    use HasFactory;

    protected $fillable = ['organization_id', 'created_by', 'responsible_user_id', 'client_user_id', 'title', 'brief', 'intake_source', 'brief_author_id', 'materials_location', 'access_instructions', 'ai_summary', 'suggested_solution', 'module_key', 'module_version', 'module_label', 'module_fields_schema', 'module_fields_data', 'status'];

    protected function casts(): array
    {
        return [
            'status' => DemandStatus::class,
            'module_fields_schema' => 'array',
            'module_fields_data' => 'array',
        ];
    }

    public function moduleDisplayLabel(): ?string
    {
        if (! $this->module_key) {
            return null;
        }

        return $this->module_label
            ?? DemandModule::tryFrom($this->module_key)?->label()
            ?? $this->module_key;
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_user_id');
    }

    public function briefAuthor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'brief_author_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(DemandTask::class)->orderBy('created_at');
    }

    public function events(): HasMany
    {
        return $this->hasMany(DemandEvent::class)->latest();
    }

    public function deliveryEvidences(): HasMany
    {
        return $this->hasMany(DemandDeliveryEvidence::class)->orderByDesc('created_at')->orderByDesc('id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(DemandAttachment::class)->orderByDesc('created_at')->orderByDesc('id');
    }

    public function moduleSteps(): HasMany
    {
        return $this->hasMany(DemandModuleStep::class)->orderBy('position');
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
