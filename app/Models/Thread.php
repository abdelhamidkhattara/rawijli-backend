<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Thread extends DomainModel
{
    public $timestamps = false;
    protected $casts = ['updated_at' => 'datetime'];
    public function participants(): BelongsToMany { return $this->belongsToMany(User::class, 'thread_participants')->withPivot('last_read_at'); }
    public function messages(): HasMany { return $this->hasMany(DirectMessage::class)->orderBy('created_at'); }
}
