<?php

namespace App\Providers\Sandbox;

use App\Contracts\TikTokConnector;
use App\Models\Connection;

class SandboxTikTokConnector implements TikTokConnector
{
    public function accounts(): array
    {
        return [['external_id' => 'sandbox_tiktok_account', 'name' => 'Sandbox TikTok Account', 'scopes' => ['user.info', 'video.publish']]];
    }

    public function sync(Connection $connection): array
    {
        return ['status' => 'connected', 'synced_at' => now()->toISOString(), 'campaigns' => 0, 'driver' => 'sandbox'];
    }

    public function refresh(Connection $connection): bool
    {
        return true;
    }

    public function publish(Connection $connection, array $payload): array
    {
        return ['reference' => 'sandbox_tiktok_'.uniqid(), 'status' => 'published', 'driver' => 'sandbox'];
    }
}
