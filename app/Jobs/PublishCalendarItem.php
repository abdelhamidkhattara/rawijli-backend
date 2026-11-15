<?php

namespace App\Jobs;

use App\Contracts\MetaConnector;
use App\Contracts\TikTokConnector;
use App\Models\CalendarItem;
use App\Models\Connection;
use App\Models\PublishingAttempt;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class PublishCalendarItem implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 30;

    public function __construct(public string $calendarItemId) {}

    public function handle(MetaConnector $meta, TikTokConnector $tiktok): void
    {
        $item = CalendarItem::find($this->calendarItemId);
        if (! $item || $item->status !== 'scheduled') return;
        $key = 'calendar:'.$item->id.':'.$item->date.':'.$item->time;
        $attempt = PublishingAttempt::firstOrCreate(['idempotency_key' => $key], ['calendar_item_id' => $item->id, 'status' => 'processing']);
        if ($attempt->status === 'published') return;
        try {
            $kind = $item->channel === 'instagram' ? 'profile' : ($item->channel === 'facebook' ? 'page' : 'tiktok_account');
            $connection = Connection::where('workspace_id', $item->workspace_id)->where('kind', $kind)->where('status', 'connected')->firstOrFail();
            $result = $item->channel === 'tiktok' ? $tiktok->publish($connection, $item->toArray()) : $meta->publish($connection, $item->toArray());
            DB::transaction(function () use ($item, $attempt) {
                $item->update(['status' => 'published', 'published_at' => now(), 'last_error' => null]);
                $attempt->update(['status' => 'published', 'error' => null]);
            });
        } catch (\Throwable $e) {
            $item->update(['status' => 'failed', 'last_error' => $e->getMessage()]);
            $attempt->update(['status' => 'failed', 'error' => $e->getMessage()]);
            throw $e;
        }
    }
}
