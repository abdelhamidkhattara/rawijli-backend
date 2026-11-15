<?php

namespace App\Models;

class Milestone extends DomainModel
{
    protected $casts = ['amount' => 'float', 'due_date' => 'date:Y-m-d'];
}
