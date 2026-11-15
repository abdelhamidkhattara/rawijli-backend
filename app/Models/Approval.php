<?php

namespace App\Models;

class Approval extends DomainModel
{
    protected $casts = ['before_payload' => 'array', 'after_payload' => 'array', 'payload' => 'array', 'decided_at' => 'datetime'];
    public function freelancer(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(FreelancerProfile::class, 'freelancer_id'); }
    public function workspace(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(Workspace::class); }
}
