<?php

namespace App\Models;

class Transaction extends DomainModel
{
    public function workspace(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(Workspace::class); }
    public function contract(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(Contract::class); }
    public $timestamps = false;
    protected $casts = ['amount' => 'float', 'created_at' => 'datetime'];
}
