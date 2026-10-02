<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DemandAttachment extends Model
{
    protected $fillable = ['organization_id', 'demand_id', 'uploaded_by', 'file_path', 'original_name', 'mime_type', 'file_size'];

    public function demand(): BelongsTo
    {
        return $this->belongsTo(Demand::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
