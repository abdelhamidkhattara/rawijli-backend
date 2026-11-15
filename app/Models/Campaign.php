<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends DomainModel
{
    protected $casts = ['daily_budget' => 'float', 'lifetime_budget' => 'float', 'spend' => 'float', 'revenue' => 'float', 'impressions' => 'integer', 'clicks' => 'integer', 'conversions' => 'integer'];
    public function adSets(): HasMany { return $this->hasMany(AdSet::class); }
    public function metricPoints(): HasMany { return $this->hasMany(MetricPoint::class); }
    public function workspace(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(Workspace::class); }
}
