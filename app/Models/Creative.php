<?php

namespace App\Models;

class Creative extends DomainModel
{
    public function calendarItems(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(CalendarItem::class); }
    public function ads(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(Ad::class); }
}
