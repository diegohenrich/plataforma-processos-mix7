<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DemandReviewResponse extends Model
{
    public $timestamps = false;

    protected $fillable = ['demand_review_link_id', 'reviewer_name', 'type', 'comment', 'anchor_type', 'anchor_data', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime', 'anchor_data' => 'array'];
    }

    public function reviewLink(): BelongsTo
    {
        return $this->belongsTo(DemandReviewLink::class, 'demand_review_link_id');
    }
}
