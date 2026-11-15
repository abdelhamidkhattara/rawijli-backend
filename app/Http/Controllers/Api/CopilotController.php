<?php

namespace App\Http\Controllers\Api;

use App\Contracts\MarketingAI;
use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\Creative;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AuditTrail;
use App\Services\WorkspaceAccess;
use App\Support\ApiMapper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CopilotController extends Controller
{
    public function ask(Request $request, WorkspaceAccess $access, MarketingAI $ai, AuditTrail $audit)
    {
        $data = $request->validate(['workspaceId' => 'required|uuid', 'content' => 'required|string|min:1|max:5000']);
        $workspace = $access->workspace($request->user(), $data['workspaceId'], 'view_analytics');
        $result = DB::transaction(function () use ($request, $workspace, $data, $ai, $audit) {
            $user = User::whereKey($request->user()->id)->lockForUpdate()->first();
            abort_if($user->ai_credits_used >= $user->ai_credits_limit, 429, 'AI credit limit reached. Ask an admin to adjust your plan or limit.');
            $response = $ai->respond($workspace, $user, $data['content']);
            $user->increment('ai_credits_used');
            $userMessage = ChatMessage::create(['workspace_id' => $workspace->id, 'user_id' => $user->id, 'role' => 'user', 'content' => $data['content'], 'created_at' => now()]);
            $botMessage = ChatMessage::create(['workspace_id' => $workspace->id, 'user_id' => $user->id, 'role' => 'assistant', 'content' => $response['content'], 'cards' => $response['cards'] ?? [], 'created_at' => now()]);
            if (! empty($response['creative'])) {
                $creative = Creative::create(array_merge($response['creative'], ['workspace_id' => $workspace->id, 'created_by_id' => $user->id, 'processing_status' => 'ready']));
                $botMessage->update(['cards' => array_merge($botMessage->cards ?? [], [['kind' => 'creative', 'title' => $creative->title, 'body' => $creative->caption, 'creativeId' => $creative->id]])]);
            }
            foreach ($response['plan'] ?? [] as $item) {
                \App\Models\CalendarItem::create(array_merge($item, ['workspace_id' => $workspace->id, 'created_by_id' => $user->id, 'timezone' => $workspace->timezone, 'status' => 'draft']));
            }
            $audit->record($user, 'copilot.asked', Workspace::class, $workspace->id, $workspace->id, null, ['creditsUsed' => $user->ai_credits_used]);
            return ['userMsg' => $userMessage->toArray(), 'botMsg' => $botMessage->fresh()->toArray()];
        });
        return response()->json(['data' => ApiMapper::camel($result)], 201);
    }
}
