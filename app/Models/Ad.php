<?php

namespace App\Models;

class Ad extends DomainModel
{
    protected $casts = ['spend' => 'float', 'revenue' => 'float', 'impressions' => 'integer', 'clicks' => 'integer', 'conversions' => 'integer'];
}
