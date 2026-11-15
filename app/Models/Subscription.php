<?php

namespace App\Models;

class Subscription extends DomainModel
{
    public function workspace(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(Workspace::class); }
    protected $casts = ['amount' => 'float', 'current_period_ends_at' => 'datetime'];
}
