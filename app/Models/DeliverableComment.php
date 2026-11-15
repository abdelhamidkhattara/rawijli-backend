<?php

namespace App\Models;

class DeliverableComment extends DomainModel
{
    public $timestamps = false;
    protected $casts = ['created_at' => 'datetime'];
}
