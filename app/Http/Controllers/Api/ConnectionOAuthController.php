<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Connection;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AuditTrail;
use App\Services\WorkspaceAccess;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ConnectionOAuthController extends Controller
{
    public function __construct(private WorkspaceAccess $access, private AuditTrail $audit) {}

    public function status()
    {
        return response()->json(['data' => [
            'meta' => filled(config('services.meta.app_id')) && filled(config('services.meta.app_secret')),
            'tiktok' => filled(config('services.tiktok.client_key')) && filled(config('services.tiktok.client_secret')),
            'ai' => filled(config('services.deepseek.key')),
            'driver' => (string) config('services.integrations.driver', 'auto'),
            'aiDriver' => (string) config('services.ai.driver', 'auto'),
        ]]);
    }

    public function redirect(Request $request, string $platform)
    {
        $data = $request->validate([
            'workspaceId' => ['required', 'uuid'],
            'kind' => ['required', Rule::in(['page', 'profile', 'ad_account', 'tiktok_account'])],
        ]);
        $workspace = $this->access->ownedWorkspace($request->user(), $data['workspaceId']);
        abort_if($platform === 'tiktok' && $data['kind'] !== 'tiktok_account', 422, 'TikTok accessibility uses the TikTok account connection.');
        abort_if($platform === 'meta' && $data['kind'] === 'tiktok_account', 422, 'Choose a Meta connection type.');

        if (! $this->configured($platform)) {
            return $this->back('error', Str::headline($platform).' OAuth is not configured yet. Add the app credentials to the backend environment.');
        }

        $state = Crypt::encryptString(json_encode([
            'platform' => $platform,
            'kind' => $data['kind'],
            'workspaceId' => $workspace->id,
            'userId' => $request->user()->id,
            'expires' => now()->addMinutes(20)->getTimestamp(),
        ]));

        if ($platform === 'meta') {
            $url = 'https://www.facebook.com/'.trim((string) config('services.meta.graph_version', 'v21.0'), '/').'/dialog/oauth?'.http_build_query([
                'client_id' => config('services.meta.app_id'),
                'redirect_uri' => config('services.meta.redirect_uri'),
                'state' => $state,
                'response_type' => 'code',
                'scope' => implode(',', config('services.meta.scopes', [])),
            ]);
        } else {
            $url = 'https://www.tiktok.com/v2/auth/authorize/?'.http_build_query([
                'client_key' => config('services.tiktok.client_key'),
                'redirect_uri' => config('services.tiktok.redirect_uri'),
                'state' => $state,
                'response_type' => 'code',
                'scope' => implode(',', config('services.tiktok.scopes', [])),
            ]);
        }

        return redirect()->away($url);
    }

    public function callback(Request $request, string $platform)
    {
        try {
            if ($request->filled('error')) {
                return $this->back('error', (string) ($request->query('error_description') ?? $request->query('error')));
            }

            $state = json_decode(Crypt::decryptString((string) $request->query('state')), true);
            abort_unless(is_array($state) && ($state['platform'] ?? null) === $platform, 422, 'Invalid OAuth state.');
            abort_if(($state['expires'] ?? 0) < now()->getTimestamp(), 422, 'This connection window expired. Start the connection again.');
            abort_unless(($state['userId'] ?? null) === $request->user()?->id, 403, 'This connection belongs to another user.');

            $workspace = $this->access->ownedWorkspace($request->user(), $state['workspaceId']);
            $kind = $state['kind'];
            $code = (string) ($request->query('code') ?? $request->query('auth_code'));
            abort_if($code === '', 422, 'The provider did not return an authorization code.');

            $count = $platform === 'meta'
                ? $this->connectMeta($request->user(), $workspace, $kind, $code)
                : $this->connectTikTok($request->user(), $workspace, $code);

            return $this->back('connected', $count.' '.Str::headline($platform).' account'.($count === 1 ? '' : 's').' connected.');
        } catch (\Throwable $e) {
            Log::warning('Integration OAuth callback failed', ['platform' => $platform, 'error' => $e->getMessage()]);

            return $this->back('error', Str::limit($e->getMessage(), 220));
        }
    }

    private function connectMeta(User $user, Workspace $workspace, string $kind, string $code): int
    {
        $token = $this->metaToken($code);
        $accounts = match ($kind) {
            'ad_account' => $this->metaAdAccounts($token),
            'page' => $this->metaPages($token),
            'profile' => $this->metaInstagramProfiles($token),
        };

        abort_if($accounts === [], 422, 'No available '.$kind.' was found on this Meta account. Check permissions and try again.');

        foreach ($accounts as $account) {
            $connection = $this->upsert($workspace, 'meta', $kind, $account['external_id'], $account['name']);
            $connection->update([
                'access_token' => $account['access_token'] ?? $token,
                'refresh_token' => null,
                'token_expires_at' => now()->addDays(50),
                'metadata' => $account['metadata'] ?? [],
                'scopes' => config('services.meta.scopes', []),
            ]);
            $this->audit->record($user, 'connection.connected', Connection::class, $connection->id, $workspace->id, null, ['platform' => 'meta', 'kind' => $kind, 'external_id' => $account['external_id']]);
        }

        return count($accounts);
    }

    private function connectTikTok(User $user, Workspace $workspace, string $code): int
    {
        $response = Http::asForm()->acceptJson()->timeout(30)
            ->post($this->tiktokContent('/v2/oauth/token/'), [
                'client_key' => config('services.tiktok.client_key'),
                'client_secret' => config('services.tiktok.client_secret'),
                'code' => $code,
                'grant_type' => 'authorization_code',
                'redirect_uri' => config('services.tiktok.redirect_uri'),
            ])
            ->throw();

        $token = (string) ($response->json('access_token') ?? data_get($response->json(), 'data.access_token'));
        abort_if($token === '', 422, 'TikTok did not return an access token.');
        $refresh = $response->json('refresh_token') ?? data_get($response->json(), 'data.refresh_token');
        $openId = (string) ($response->json('open_id') ?? data_get($response->json(), 'data.open_id') ?? 'tiktok');
        $expiresIn = (int) ($response->json('expires_in') ?? data_get($response->json(), 'data.expires_in') ?? 0);

        $name = 'TikTok account';
        try {
            $profile = Http::withToken($token)->acceptJson()->timeout(20)
                ->get($this->tiktokContent('/v2/user/info/'), ['fields' => 'open_id,display_name,avatar_url,follower_count'])
                ->throw();
            $name = (string) (data_get($profile->json(), 'data.user.display_name') ?? $name);
            $metadata = ['account' => data_get($profile->json(), 'data.user', [])];
        } catch (\Throwable) {
            $metadata = [];
        }

        $connection = $this->upsert($workspace, 'tiktok', 'tiktok_account', $openId, $name);
        $connection->update([
            'access_token' => $token,
            'refresh_token' => $refresh,
            'token_expires_at' => $expiresIn > 0 ? now()->addSeconds($expiresIn) : null,
            'metadata' => $metadata,
            'scopes' => config('services.tiktok.scopes', []),
        ]);
        $this->audit->record($user, 'connection.connected', Connection::class, $connection->id, $workspace->id, null, ['platform' => 'tiktok', 'kind' => 'tiktok_account', 'external_id' => $openId]);

        return 1;
    }

    private function metaToken(string $code): string
    {
        $response = Http::acceptJson()->timeout(30)->get($this->metaGraph('/oauth/access_token'), [
            'client_id' => config('services.meta.app_id'),
            'client_secret' => config('services.meta.app_secret'),
            'redirect_uri' => config('services.meta.redirect_uri'),
            'code' => $code,
        ])->throw();

        $short = (string) $response->json('access_token');
        abort_if($short === '', 422, 'Meta did not return an access token.');

        try {
            $long = Http::acceptJson()->timeout(30)->get($this->metaGraph('/oauth/access_token'), [
                'grant_type' => 'fb_exchange_token',
                'client_id' => config('services.meta.app_id'),
                'client_secret' => config('services.meta.app_secret'),
                'fb_exchange_token' => $short,
            ])->throw();

            return (string) ($long->json('access_token') ?? $short);
        } catch (\Throwable) {
            return $short;
        }
    }

    private function metaAdAccounts(string $token): array
    {
        $response = $this->metaGet($token, '/me/adaccounts', ['fields' => 'id,name,account_status,currency', 'limit' => 100]);
        return collect($response->json('data', []))->map(fn (array $row) => [
            'external_id' => (string) $row['id'],
            'name' => (string) ($row['name'] ?? $row['id']),
            'metadata' => ['account_status' => $row['account_status'] ?? null, 'currency' => $row['currency'] ?? null],
        ])->values()->all();
    }

    private function metaPages(string $token): array
    {
        $response = $this->metaGet($token, '/me/accounts', ['fields' => 'id,name,access_token,tasks', 'limit' => 100]);
        return collect($response->json('data', []))->map(fn (array $row) => [
            'external_id' => (string) $row['id'],
            'name' => (string) ($row['name'] ?? $row['id']),
            'access_token' => $row['access_token'] ?? $token,
            'metadata' => ['page_id' => $row['id'], 'tasks' => $row['tasks'] ?? []],
        ])->values()->all();
    }

    private function metaInstagramProfiles(string $token): array
    {
        $response = $this->metaGet($token, '/me/accounts', [
            'fields' => 'id,name,access_token,instagram_business_account{id,username}',
            'limit' => 100,
        ]);

        return collect($response->json('data', []))
            ->filter(fn (array $row) => ! empty($row['instagram_business_account']['id']))
            ->map(fn (array $row) => [
                'external_id' => (string) $row['instagram_business_account']['id'],
                'name' => (string) ($row['instagram_business_account']['username'] ?? $row['name'] ?? 'Instagram profile'),
                'access_token' => $row['access_token'] ?? $token,
                'metadata' => ['page_id' => $row['id'], 'page_name' => $row['name'] ?? null],
            ])->values()->all();
    }

    private function metaGet(string $token, string $path, array $query): Response
    {
        return Http::withToken($token)->acceptJson()->timeout(30)->get($this->metaGraph($path), $query)->throw();
    }

    private function metaGraph(string $path): string
    {
        return rtrim((string) config('services.meta.graph_url', 'https://graph.facebook.com'), '/')
            .'/'.trim((string) config('services.meta.graph_version', 'v21.0'), '/')
            .'/'.ltrim($path, '/');
    }

    private function tiktokContent(string $path): string
    {
        return rtrim((string) config('services.tiktok.content_base', 'https://open.tiktokapis.com'), '/').'/'.ltrim($path, '/');
    }

    private function upsert(Workspace $workspace, string $platform, string $kind, string $externalId, string $name): Connection
    {
        $connection = Connection::updateOrCreate(
            ['workspace_id' => $workspace->id, 'kind' => $kind, 'external_id' => $externalId],
            ['platform' => $platform, 'name' => $name, 'status' => 'connected', 'driver' => 'live', 'last_sync_at' => now(), 'error' => null],
        );
        $history = is_array($connection->sync_history) ? $connection->sync_history : [];
        array_unshift($history, ['status' => 'connected', 'at' => now()->toISOString(), 'driver' => 'live']);
        $connection->update(['sync_history' => array_slice($history, 0, 20)]);

        return $connection;
    }

    private function configured(string $platform): bool
    {
        return $platform === 'meta'
            ? filled(config('services.meta.app_id')) && filled(config('services.meta.app_secret'))
            : filled(config('services.tiktok.client_key')) && filled(config('services.tiktok.client_secret'));
    }

    private function back(string $key, string $message)
    {
        $url = rtrim((string) config('services.rawijli.frontend_url', config('app.url')), '/').'/app/connections?'.$key.'='.urlencode($message);

        return redirect()->away($url);
    }
}
