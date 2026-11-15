<?php

namespace App\Http\Controllers\Api;

use App\Contracts\MediaStorage;
use App\Http\Controllers\Controller;
use App\Models\DirectMessage;
use App\Models\Thread;
use App\Models\ThreadParticipant;
use App\Models\User;
use App\Models\Workspace;
use App\Services\MarketplaceService;
use App\Services\Notifier;
use App\Services\WorkspaceAccess;
use App\Support\ApiMapper;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function storeThread(Request $request, WorkspaceAccess $access, MarketplaceService $marketplace)
    {
        $data = $request->validate(['workspaceId' => 'required|uuid', 'recipientId' => 'required|uuid', 'jobId' => 'nullable|uuid']);
        $workspace = Workspace::with('owner')->findOrFail($data['workspaceId']);
        $recipient = User::findOrFail($data['recipientId']);
        $actor = $request->user();
        abort_if($actor->id === $recipient->id, 422);
        if ($actor->role === 'owner') {
            $access->ownedWorkspace($actor, $workspace->id);
            abort_unless($recipient->role === 'freelancer', 422);
        } else {
            abort_unless($actor->role === 'freelancer' && $recipient->id === $workspace->owner_id, 403);
            $profileId = $actor->freelancerProfile?->id;
            $related = \App\Models\Proposal::where('freelancer_id', $profileId)->whereHas('job', fn ($q) => $q->where('workspace_id', $workspace->id))->exists()
                || \App\Models\Invitation::where('freelancer_id', $profileId)->where('workspace_id', $workspace->id)->exists()
                || \App\Models\Contract::where('freelancer_id', $profileId)->where('workspace_id', $workspace->id)->exists();
            abort_unless($related, 403, 'A proposal, invitation, or contract is required before messaging this owner.');
        }
        $thread = $marketplace->conversation($actor, $recipient, $workspace, $data['jobId'] ?? null);
        return response()->json(['data' => ApiMapper::camel($thread->toArray())], 201);
    }

    public function send(Request $request, Thread $thread, MediaStorage $storage, Notifier $notifier)
    {
        $this->participant($request->user(), $thread);
        $data = $request->validate(['body' => 'required_without:file|nullable|string|max:10000', 'file' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf,mp4,webm,mov|max:51200']);
        $path = $request->hasFile('file') ? $storage->store($request->file('file'), 'messages/'.$thread->id) : null;
        $message = DirectMessage::create(['thread_id' => $thread->id, 'from_id' => $request->user()->id, 'body' => $data['body'] ?? 'Attachment', 'attachment_path' => $path, 'created_at' => now()]);
        $thread->update(['last_message' => $message->body, 'updated_at' => now()]);
        $recipients = $thread->participants()->where('users.id', '!=', $request->user()->id)->get();
        foreach ($recipients as $recipient) $notifier->send($recipient, 'New message from '.$request->user()->name, $message->body, $recipient->role === 'owner' ? '/app/messages' : '/pro/messages');
        return response()->json(['data' => ApiMapper::camel($message->toArray())], 201);
    }

    public function read(Request $request, Thread $thread)
    {
        $participant = $this->participant($request->user(), $thread);
        $participant->update(['last_read_at' => now()]);
        DirectMessage::where('thread_id', $thread->id)->where('from_id', '!=', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);
        return response()->json(['data' => ['read' => true]]);
    }

    private function participant(User $user, Thread $thread): ThreadParticipant
    {
        return ThreadParticipant::where('thread_id', $thread->id)->where('user_id', $user->id)->firstOrFail();
    }
}
