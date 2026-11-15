<?php

namespace App\Models;

class ChatMessage extends DomainModel
{
    public $timestamps = false;
    protected $casts = ['cards' => 'array', 'created_at' => 'datetime'];
}
