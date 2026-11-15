<?php

namespace App\Providers\Sandbox;

use App\Contracts\MetaConnector;
use App\Models\Connection;

class SandboxMetaConnector implements MetaConnector
{
    public function accounts(string $kind): array
    {
        $names = ['page' => 'Sandbox Facebook Page', 'profile' => 'Sandbox Instagram Profile', 'ad_account' => 'Sandbox Meta Ad Account'];
        abort_unless(isset($names[$kind]), 422, 'Unsupported Meta connection type.');
        return [['external_id' => 'sandbox_meta_'.$kind, 'name' => $names[$kind], 'scopes' => ['read_insights', 'publish_content']]];
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
        return ['reference' => 'sandbox_meta_'.uniqid(), 'status' => 'published', 'channel' => $connection->kind, 'driver' => 'sandbox'];
    }
}
