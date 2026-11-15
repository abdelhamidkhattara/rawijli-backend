<?php

namespace App\Models;

class DisputeEvent extends DomainModel
{
    public $timestamps = false;
    protected $casts = ['created_at' => 'datetime'];
}
