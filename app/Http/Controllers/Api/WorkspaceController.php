<?php

namespace App\Http\Controllers\Api;

use App\Contracts\MetaConnector;
use App\Contracts\TikTokConnector;
use App\Http\Controllers\Controller;
use App\Models\Connection;
use App\Models\Grant;
use App\Models\User;
use App\Services\WorkspaceAccess;
use App\Support\ApiMapper;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WorkspaceController extends Controller
{
    public function clients(Request $request)
    {
        $user = $request->user();
        abort_unless($user->role === 'freelancer', 403);
        $ids = app(WorkspaceAccess::class)->workspaceIds($user);
        return response()->json(['data' => ApiMapper::camel(\App\Models\Workspace::whereIn('id', $ids)->get()->toArray())]);
    }

    public function accounts(Request $request, MetaConnector $meta, TikTokConnector $tiktok)
    {
        $kind = $request->validate(['kind' => ['required', Rule::in(['page', 'profile', 'ad_account', 'tiktok_account'])]])['kind'];
        return response()->json(['data' => $kind === 'tiktok_account' ? $tiktok->accounts() : $meta->accounts($kind)]);
    }

    public function connect(Request $request, MetaConnector $meta, TikTokConnector $tiktok, WorkspaceAccess $access)
    {
        $data = $request->validate(['workspaceId' => 'required|uuid', 'kind' => ['required', Rule::in(['page', 'profile', 'ad_account', 'tiktok_account'])], 'externalId' => 'required|string|max:190', 'name' => 'required|string|max:190']);
        $workspace = $access->ownedWorkspace($request->user(), $data['workspaceId']);
        $accounts = $data['kind'] === 'tiktok_account' ? $tiktok->accounts() : $meta->accounts($data['kind']);
        $account = collect($accounts)->firstWhere('external_id', $data['externalId']);
        abort_unless($account, 422, 'No connectable account was returned. Use the OAuth connection flow for live providers.');
        $connection = Connection::updateOrCreate(['workspace_id' => $workspace->id, 'kind' => $data['kind'], 'external_id' => $data['externalId']], ['platform' => $data['kind'] === 'tiktok_account' ? 'tiktok' : 'meta', 'name' => $data['name'], 'status' => 'connected', 'last_sync_at' => now(), 'scopes' => $account['scopes'], 'error' => null, 'sync_history' => [['status' => 'connected', 'at' => now()->toISOString(), 'driver' => 'sandbox']]]);
        return response()->json(['data' => ApiMapper::camel($connection->toArray())], 201);
    }

    public function connectionAction(Request $request, Connection $connection, WorkspaceAccess $access, MetaConnector $meta, TikTokConnector $tiktok)
    {
        $access->ownedWorkspace($request->user(), $connection->workspace_id);
        $data = $request->validate(['action' => ['required', Rule::in(['disconnect', 'reconnect', 'sync'])]]);
        if ($data['action'] === 'disconnect') {
            $connection->update(['status' => 'disconnected']);
        } else {
            try {
                $result = $connection->platform === 'meta' ? $meta->sync($connection) : $tiktok->sync($connection);
                $history = $connection->sync_history ?? [];
                array_unshift($history, $result);
                $connection->update(['status' => 'connected', 'last_sync_at' => now(), 'error' => null, 'sync_history' => array_slice($history, 0, 20)]);
            } catch (\Throwable $e) {
                return response()->json(['message' => $e->getMessage(), 'data' => ApiMapper::camel($connection->fresh()->toArray())], 422);
            }
        }
        return response()->json(['data' => ApiMapper::camel($connection->fresh()->toArray())]);
    }
}
