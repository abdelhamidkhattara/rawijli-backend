<?php

namespace App\Contracts;

use App\Models\Connection;

interface TikTokConnector
{
    public function accounts(): array;
    public function sync(Connection $connection): array;
    public function refresh(Connection $connection): bool;
    public function publish(Connection $connection, array $payload): array;
}
