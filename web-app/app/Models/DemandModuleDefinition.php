<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DemandModuleDefinition extends Model
{
    protected $fillable = ['organization_id', 'created_by', 'updated_by', 'key', 'label', 'description', 'config_version', 'fields', 'workflow_steps', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'config_version' => 'integer', 'fields' => 'array', 'workflow_steps' => 'array'];
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
}
