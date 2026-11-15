<?php

namespace App\Jobs;

use App\Contracts\MetaConnector;
use App\Contracts\TikTokConnector;
use App\Models\Connection;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SyncConnectedAccounts implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;

    public function handle(MetaConnector $meta, TikTokConnector $tiktok): void
    {
        Connection::where('status', 'connected')->where('driver', 'live')->cursor()->each(function (Connection $connection) use ($meta, $tiktok) {
            try {
                $result = $connection->platform === 'meta' ? $meta->sync($connection) : $tiktok->sync($connection);
                $history = is_array($connection->sync_history) ? $connection->sync_history : [];
                array_unshift($history, $result);
                $connection->update(['sync_history' => array_slice($history, 0, 20)]);
            } catch (\Throwable $e) {
                Log::warning('Connection sync failed', ['connection_id' => $connection->id, 'platform' => $connection->platform, 'error' => $e->getMessage()]);
            }
        });
    }
}
