<?php

namespace App\Models;

class Report extends DomainModel
{
    protected $casts = ['date_from' => 'date:Y-m-d', 'date_to' => 'date:Y-m-d', 'payload' => 'array'];
}
