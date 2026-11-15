<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Deliverable extends DomainModel
{
    protected $casts = ['submitted_at' => 'datetime'];
    public function versions(): HasMany { return $this->hasMany(DeliverableVersion::class)->orderByDesc('version'); }
    public function comments(): HasMany { return $this->hasMany(DeliverableComment::class)->orderBy('created_at'); }
    public function contract(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(Contract::class); }
    public function workspace(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(Workspace::class); }
    public function freelancer(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(FreelancerProfile::class, 'freelancer_id'); }
}
