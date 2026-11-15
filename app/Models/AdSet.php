<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class AdSet extends DomainModel
{
    protected $table = 'ad_sets';
    protected $casts = ['daily_budget' => 'float', 'spend' => 'float', 'revenue' => 'float', 'impressions' => 'integer', 'clicks' => 'integer', 'conversions' => 'integer'];
    public function ads(): HasMany { return $this->hasMany(Ad::class); }
    public function campaign(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(Campaign::class); }
}
