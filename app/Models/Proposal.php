<?php

namespace App\Models;

class Proposal extends DomainModel
{
    protected $casts = ['bid' => 'float', 'attachments' => 'array'];
    public function job(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(JobBrief::class, 'job_id'); }
    public function freelancer(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(FreelancerProfile::class, 'freelancer_id'); }
}
