<?php

namespace App\Models;

class MetricPoint extends DomainModel
{
    protected $casts = ['date' => 'date:Y-m-d', 'spend' => 'float', 'revenue' => 'float', 'impressions' => 'integer', 'clicks' => 'integer', 'conversions' => 'integer'];
}
