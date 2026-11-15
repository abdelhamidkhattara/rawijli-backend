<?php

namespace App\Models;

class JobBrief extends DomainModel
{
    public function workspace(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(Workspace::class); }
    public function owner(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(User::class, 'owner_id'); }
    protected $table = 'job_briefs';
    protected $casts = ['skills' => 'array', 'platforms' => 'array', 'attachments' => 'array', 'budget' => 'float', 'ai_generated' => 'boolean'];
    public function proposals(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(Proposal::class, 'job_id'); }
}
