<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Contract extends DomainModel
{
    protected $casts = ['amount' => 'float', 'started_at' => 'datetime'];
    public function milestones(): HasMany { return $this->hasMany(Milestone::class); }
    public function workspace(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(Workspace::class); }
    public function freelancer(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(FreelancerProfile::class, 'freelancer_id'); }
    public function job(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(JobBrief::class, 'job_id'); }
}
