<?php

namespace App\Models;

class CalendarItem extends DomainModel
{
    protected $casts = ['date' => 'date:Y-m-d', 'published_at' => 'datetime'];
}
