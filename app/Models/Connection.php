<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Connection extends DomainModel
{
    protected $hidden = ['access_token', 'refresh_token'];

    protected $casts = [
        'scopes' => 'array',
        'metadata' => 'array',
        'sync_history' => 'array',
        'last_sync_at' => 'datetime',
        'token_expires_at' => 'datetime',
        'spend_30d' => 'float',
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function hasToken(): bool
    {
        return is_string($this->access_token) && $this->access_token !== '';
    }

    public function tokenExpired(): bool
    {
        return $this->token_expires_at !== null && $this->token_expires_at->isPast();
    }

    public function needsRefresh(): bool
    {
        return $this->hasToken() && $this->token_expires_at !== null && $this->token_expires_at->isBefore(now()->addDay());
    }
}
