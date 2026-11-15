<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FreelancerProfile extends DomainModel
{
    protected $casts = ['skills' => 'array', 'platforms' => 'array', 'badges' => 'array', 'verified' => 'boolean', 'public_visible' => 'boolean', 'hourly_rate' => 'float', 'rating' => 'float'];
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function packages(): HasMany { return $this->hasMany(Package::class); }
    public function portfolio(): HasMany { return $this->hasMany(PortfolioItem::class)->orderBy('sort_order'); }
}
