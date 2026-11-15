<?php

namespace App\Models;

class Package extends DomainModel
{
    protected $casts = ['price' => 'float'];
}
