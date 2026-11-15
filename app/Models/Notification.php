<?php

namespace App\Models;

class Notification extends DomainModel
{
    public $timestamps = false;
    protected $casts = ['created_at' => 'datetime', 'read_at' => 'datetime'];
}
