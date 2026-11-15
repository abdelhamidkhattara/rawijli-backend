<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Dispute extends DomainModel
{
    public function events(): HasMany { return $this->hasMany(DisputeEvent::class)->orderBy('created_at'); }
    public function contract(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(Contract::class); }
    public function opener(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(User::class, 'opened_by'); }
}
