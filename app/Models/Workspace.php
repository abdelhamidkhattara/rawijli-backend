<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workspace extends DomainModel
{
    public function owner(): BelongsTo { return $this->belongsTo(User::class, 'owner_id'); }
    public function campaigns(): HasMany { return $this->hasMany(Campaign::class); }
    public function connections(): HasMany { return $this->hasMany(Connection::class); }
    public function grants(): HasMany { return $this->hasMany(Grant::class); }
}
