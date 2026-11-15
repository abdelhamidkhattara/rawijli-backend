<?php

namespace App\Models;

class Grant extends DomainModel
{
    protected $casts = ['access_enabled' => 'boolean', 'permissions' => 'array'];
    public function contract(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(Contract::class); }
    public function workspace(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(Workspace::class); }
    public function freelancer(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(FreelancerProfile::class, 'freelancer_id'); }
}
