<?php

namespace App\Jobs;

use App\Models\CalendarItem;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PublishDueCalendarItems implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        CalendarItem::where('status', 'scheduled')->cursor()->each(function ($item) {
            $due = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $item->date->toDateString().' '.$item->time, $item->timezone);
            if ($due->lessThanOrEqualTo(now())) PublishCalendarItem::dispatch($item->id);
        });
    }
}
