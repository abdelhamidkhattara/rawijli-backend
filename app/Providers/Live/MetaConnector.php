<?php

namespace App\Providers\Live;

use App\Contracts\MetaConnector as MetaConnectorContract;
use App\Models\Connection;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class MetaConnector implements MetaConnectorContract
{
    public function accounts(string $kind): array
    {
        // Live accounts are discovered during OAuth, never guessed from a shared token.
        return [];
    }

    public function sync(Connection $connection): array
    {
        if (! $connection->hasToken()) {
            $connection->update(['status' => 'needs_reauth', 'error' => 'No Meta access token. Reconnect the account.']);
            return ['status' => 'needs_reauth', 'synced_at' => now()->toISOString(), 'campaigns' => 0, 'driver' => 'meta'];
        }

        try {
            if ($connection->kind === 'ad_account') {
                $response = $this->client($connection->access_token)
                    ->get($this->url($connection->external_id.'/insights'), [
                        'date_preset' => 'last_30d',
                        'fields' => 'spend,impressions,clicks,actions,account_currency',
                    ])
                    ->throw();
                $row = $response->json('data.0', []);
                $spend = (float) ($row['spend'] ?? 0);
                $connection->update([
                    'status' => 'connected',
                    'last_sync_at' => now(),
                    'spend_30d' => $spend,
                    'error' => null,
                    'metadata' => array_merge($connection->metadata ?? [], [
                        'insights' => $row,
                        'currency' => $row['account_currency'] ?? null,
                    ]),
                ]);

                return ['status' => 'connected', 'synced_at' => now()->toISOString(), 'campaigns' => 0, 'spend_30d' => $spend, 'driver' => 'meta'];
            }

            $fields = $connection->kind === 'profile'
                ? 'id,name,username,followers_count,media_count'
                : 'id,name,fan_count,followers_count';
            $response = $this->client($connection->access_token)
                ->get($this->url($connection->external_id), ['fields' => $fields])
                ->throw();
            $connection->update([
                'status' => 'connected',
                'last_sync_at' => now(),
                'error' => null,
                'metadata' => array_merge($connection->metadata ?? [], ['account' => $response->json()]),
            ]);

            return ['status' => 'connected', 'synced_at' => now()->toISOString(), 'campaigns' => 0, 'driver' => 'meta'];
        } catch (RequestException $e) {
            $this->flagError($connection, $e);
            throw $e;
        }
    }

    public function refresh(Connection $connection): bool
    {
        if (! $connection->hasToken()) return false;

        try {
            $response = Http::acceptJson()->get($this->graph().'/oauth/access_token', [
                'grant_type' => 'fb_exchange_token',
                'client_id' => config('services.meta.app_id'),
                'client_secret' => config('services.meta.app_secret'),
                'fb_exchange_token' => $connection->access_token,
            ])->throw();

            $connection->update([
                'access_token' => $response->json('access_token'),
                'token_expires_at' => now()->addDays(50),
                'status' => 'connected',
                'error' => null,
            ]);

            return true;
        } catch (\Throwable $e) {
            $connection->update(['status' => 'needs_reauth', 'error' => $e->getMessage()]);
            return false;
        }
    }

    public function publish(Connection $connection, array $payload): array
    {
        if (! $connection->hasToken()) {
            throw new RuntimeException('Meta connection needs reconnection before publishing.');
        }

        $caption = (string) ($payload['caption'] ?? $payload['title'] ?? '');

        if ($connection->kind === 'page') {
            $response = $this->client($connection->access_token)
                ->asForm()
                ->post($this->url($connection->external_id.'/feed'), [
                    'message' => $caption,
                    'link' => $payload['link'] ?? null,
                ])
                ->throw();

            return ['reference' => (string) $response->json('id'), 'status' => 'published', 'driver' => 'meta'];
        }

        if ($connection->kind === 'profile') {
            $imageUrl = $payload['image_url'] ?? $this->publicUrl($payload['path'] ?? null);
            if (! $imageUrl) {
                throw new RuntimeException('Instagram publishing needs a public image URL or an uploaded image creative.');
            }

            $container = $this->client($connection->access_token)
                ->asForm()
                ->post($this->url($connection->external_id.'/media'), [
                    'image_url' => $imageUrl,
                    'caption' => $caption,
                ])
                ->throw();

            $published = $this->client($connection->access_token)
                ->asForm()
                ->post($this->url($connection->external_id.'/media_publish'), [
                    'creation_id' => $container->json('id'),
                ])
                ->throw();

            return ['reference' => (string) $published->json('id'), 'status' => 'published', 'driver' => 'meta'];
        }

        throw new RuntimeException('Meta ad accounts cannot publish organic calendar posts.');
    }

    private function client(string $token): PendingRequest
    {
        return Http::withToken($token)->acceptJson()->timeout(30);
    }

    private function graph(): string
    {
        return rtrim((string) config('services.meta.graph_url', 'https://graph.facebook.com'), '/')
            .'/'.trim((string) config('services.meta.graph_version', 'v21.0'), '/');
    }

    private function url(string $path): string
    {
        return $this->graph().'/'.ltrim($path, '/');
    }

    private function publicUrl(?string $path): ?string
    {
        if (! $path) return null;

        return Storage::disk(config('services.rawijli.media_disk', 'public'))->url($path);
    }

    private function flagError(Connection $connection, RequestException $e): void
    {
        $status = $e->response?->status();
        $needsReauth = in_array($status, [401, 403], true)
            || (int) data_get($e->response?->json(), 'error.code') === 190;

        $connection->update([
            'status' => $needsReauth ? 'needs_reauth' : 'connected',
            'error' => $e->getMessage(),
        ]);
    }
}
