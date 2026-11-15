<?php

namespace App\Models;

class DeliverableVersion extends DomainModel
{
    public $timestamps = false;
    protected $casts = ['created_at' => 'datetime'];
}
