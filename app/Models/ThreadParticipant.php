<?php

namespace App\Models;

class ThreadParticipant extends DomainModel
{
    public $timestamps = false;
    protected $casts = ['last_read_at' => 'datetime'];
}
