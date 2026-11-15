<?php

namespace App\Models;

class AuditLog extends DomainModel
{
    public function actor(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(User::class, 'actor_id'); }
    public function workspace(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(Workspace::class); }
    public $timestamps = false;
    protected $casts = ['before' => 'array', 'after' => 'array', 'created_at' => 'datetime'];
}
