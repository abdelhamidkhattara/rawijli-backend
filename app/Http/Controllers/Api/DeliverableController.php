<?php

namespace App\Http\Controllers\Api;

use App\Contracts\MediaStorage;
use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\Deliverable;
use App\Models\DeliverableComment;
use App\Models\User;
use App\Services\Notifier;
use App\Support\ApiMapper;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DeliverableController extends Controller
{
    public function store(Request $request, MediaStorage $storage, Notifier $notifier)
    {
        $user = $request->user();
        abort_unless($user->role === 'freelancer' && $user->freelancerProfile, 403);
        $data = $this->validateData($request);
        $contract = Contract::whereKey($data['contractId'])->where('freelancer_id', $user->freelancerProfile->id)->where('status', 'active')->firstOrFail();
        $path = $request->hasFile('file') ? $storage->store($request->file('file'), 'deliverables/'.$contract->id) : null;
        $deliverable = Deliverable::create([
            'contract_id' => $contract->id,
            'workspace_id' => $contract->workspace_id,
            'freelancer_id' => $contract->freelancer_id,
            'title' => $data['title'],
            'notes' => $data['notes'] ?? '',
            'status' => 'submitted',
            'current_version' => 1,
            'submitted_at' => now(),
        ]);
        $deliverable->versions()->create(['version' => 1, 'notes' => $data['notes'] ?? '', 'file_path' => $path, 'link' => $data['link'] ?? null, 'created_at' => now()]);
        $notifier->send($contract->workspace->owner, 'Deliverable submitted', $deliverable->title, '/app/team');
        return response()->json(['data' => ApiMapper::camel($this->withRelations($deliverable))], 201);
    }

    public function resubmit(Request $request, Deliverable $deliverable, MediaStorage $storage, Notifier $notifier)
    {
        $user = $request->user();
        abort_unless($user->role === 'freelancer' && $deliverable->freelancer_id === $user->freelancerProfile?->id, 403);
        abort_if($deliverable->status === 'approved', 409, 'An approved deliverable cannot be resubmitted.');
        $data = $this->validateData($request, false);
        $path = $request->hasFile('file') ? $storage->store($request->file('file'), 'deliverables/'.$deliverable->contract_id) : null;
        $version = $deliverable->current_version + 1;
        $deliverable->update(['status' => 'submitted', 'current_version' => $version, 'notes' => $data['notes'] ?? $deliverable->notes, 'submitted_at' => now()]);
        $deliverable->versions()->create(['version' => $version, 'notes' => $data['notes'] ?? '', 'file_path' => $path, 'link' => $data['link'] ?? null, 'created_at' => now()]);
        $notifier->send($deliverable->contract->workspace->owner, 'Deliverable resubmitted', $deliverable->title, '/app/team');
        return response()->json(['data' => ApiMapper::camel($this->withRelations($deliverable->fresh()))]);
    }

    public function review(Request $request, Deliverable $deliverable, Notifier $notifier)
    {
        $data = $request->validate(['decision' => ['required', Rule::in(['approved', 'revision'])], 'comment' => ['nullable', 'string', 'max:3000']]);
        $this->authorizeOwner($request->user(), $deliverable);
        abort_if($deliverable->status === 'approved', 409, 'This deliverable is already approved.');
        $deliverable->update(['status' => $data['decision']]);
        if (! empty($data['comment'])) {
            DeliverableComment::create(['deliverable_id' => $deliverable->id, 'user_id' => $request->user()->id, 'body' => $data['comment'], 'created_at' => now()]);
        }
        $notifier->send($deliverable->contract->freelancer->user, 'Deliverable '.$data['decision'], $deliverable->title, '/pro/deliverables');
        return response()->json(['data' => ApiMapper::camel($this->withRelations($deliverable->fresh()))]);
    }

    public function comment(Request $request, Deliverable $deliverable, Notifier $notifier)
    {
        $user = $request->user();
        $isOwner = $deliverable->workspace_id === $user->workspace?->id;
        $isFreelancer = $deliverable->freelancer_id === $user->freelancerProfile?->id;
        abort_unless($isOwner || $isFreelancer, 403);
        $data = $request->validate(['body' => ['required', 'string', 'max:3000']]);
        $comment = DeliverableComment::create(['deliverable_id' => $deliverable->id, 'user_id' => $user->id, 'body' => $data['body'], 'created_at' => now()]);
        $recipient = $isOwner ? $deliverable->contract->freelancer->user : $deliverable->contract->workspace->owner;
        $notifier->send($recipient, 'New deliverable comment', $deliverable->title, $isOwner ? '/pro/deliverables' : '/app/team');
        return response()->json(['data' => ApiMapper::camel($comment->toArray())], 201);
    }

    private function authorizeOwner(User $user, Deliverable $deliverable): void
    {
        abort_unless($deliverable->workspace_id === $user->workspace?->id, 403, 'Only the workspace owner can review this deliverable.');
    }

    private function validateData(Request $request, bool $requireContract = true): array
    {
        return $request->validate([
            'contractId' => [$requireContract ? 'required' : 'nullable', 'uuid'],
            'title' => [$requireContract ? 'required' : 'sometimes', 'string', 'max:180'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'link' => ['nullable', 'url', 'max:2048'],
            'file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,gif,mp4,webm,mov,pdf,zip', 'max:51200'],
        ]);
    }

    private function withRelations(Deliverable $deliverable): array
    {
        $deliverable->load(['versions', 'comments']);
        return $deliverable->toArray();
    }
}
