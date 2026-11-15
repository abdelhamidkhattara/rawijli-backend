<?php

namespace App\Providers\Live;

use App\Contracts\TikTokConnector as TikTokConnectorContract;
use App\Models\Connection;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class TikTokConnector implements TikTokConnectorContract
{
    public function accounts(): array
    {
        return [];
    }

    public function sync(Connection $connection): array
    {
        if (! $connection->hasToken()) {
            $connection->update(['status' => 'needs_reauth', 'error' => 'No TikTok access token. Reconnect the account.']);
            return ['status' => 'needs_reauth', 'synced_at' => now()->toISOString(), 'campaigns' => 0, 'driver' => 'tiktok'];
        }

        try {
            $response = $this->client($connection->access_token)
                ->get($this->content('/v2/user/info/'), [
                    'fields' => 'open_id,union_id,avatar_url,display_name,bio_description,profile_deep_link,is_verified,follower_count,following_count,likes_count,video_count',
                ])
                ->throw();

            $connection->update([
                'status' => 'connected',
                'last_sync_at' => now(),
                'error' => null,
                'metadata' => array_merge($connection->metadata ?? [], ['account' => $response->json('data.user', $response->json('data', []))]),
            ]);

            return ['status' => 'connected', 'synced_at' => now()->toISOString(), 'campaigns' => 0, 'driver' => 'tiktok'];
        } catch (RequestException $e) {
            $connection->update([
                'status' => in_array($e->response?->status(), [401, 403], true) ? 'needs_reauth' : 'connected',
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function refresh(Connection $connection): bool
    {
        if (! $connection->refresh_token) return false;

        try {
            $response = Http::asForm()->acceptJson()->timeout(30)
                ->post($this->content('/v2/oauth/token/'), [
                    'client_key' => config('services.tiktok.client_key'),
                    'client_secret' => config('services.tiktok.client_secret'),
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $connection->refresh_token,
                ])
                ->throw();

            $expiresIn = (int) ($response->json('expires_in') ?? 0);
            $connection->update([
                'access_token' => $response->json('access_token'),
                'refresh_token' => $response->json('refresh_token', $connection->refresh_token),
                'token_expires_at' => $expiresIn > 0 ? now()->addSeconds($expiresIn) : null,
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
            throw new RuntimeException('TikTok connection needs reconnection before publishing.');
        }

        $videoUrl = $payload['video_url'] ?? $this->publicUrl($payload['path'] ?? null);
        if (! $videoUrl) {
            throw new RuntimeException('TikTok publishing needs a public video URL. Upload a video creative first.');
        }

        $response = $this->client($connection->access_token)
            ->post($this->content('/v2/post/publish/video/init/'), [
                'post_info' => [
                    'title' => (string) ($payload['caption'] ?? $payload['title'] ?? ''),
                    'privacy_level' => 'PUBLIC_TO_EVERYONE',
                    'disable_comment' => false,
                    'disable_duet' => false,
                    'disable_stitch' => false,
                ],
                'source_info' => [
                    'source' => 'PULL_FROM_URL',
                    'video_url' => $videoUrl,
                ],
            ])
            ->throw();

        return [
            'reference' => (string) data_get($response->json(), 'data.publish_id', uniqid('tiktok_')),
            'status' => 'published',
            'driver' => 'tiktok',
        ];
    }

    private function client(string $token): PendingRequest
    {
        return Http::withToken($token)->acceptJson()->timeout(30);
    }

    private function content(string $path): string
    {
        return rtrim((string) config('services.tiktok.content_base', 'https://open.tiktokapis.com'), '/').'/'.ltrim($path, '/');
    }

    private function publicUrl(?string $path): ?string
    {
        if (! $path) return null;

        return Storage::disk(config('services.rawijli.media_disk', 'public'))->url($path);
    }
}
