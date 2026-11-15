<?php

namespace App\Jobs;

use App\Contracts\MetaConnector;
use App\Contracts\TikTokConnector;
use App\Models\Connection;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RefreshConnectionTokens implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;

    public function handle(MetaConnector $meta, TikTokConnector $tiktok): void
    {
        Connection::where('status', 'connected')
            ->where('driver', 'live')
            ->whereNotNull('token_expires_at')
            ->where('token_expires_at', '<=', now()->addDays(2))
            ->cursor()
            ->each(function (Connection $connection) use ($meta, $tiktok) {
                $connection->platform === 'meta' ? $meta->refresh($connection) : $tiktok->refresh($connection);
            });
    }
}
