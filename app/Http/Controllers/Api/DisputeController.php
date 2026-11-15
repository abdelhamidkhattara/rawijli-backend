<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\Dispute;
use App\Models\DisputeEvent;
use App\Models\User;
use App\Services\AuditTrail;
use App\Services\Notifier;
use App\Support\ApiMapper;
use Illuminate\Http\Request;

class DisputeController extends Controller
{
    public function store(Request $request, Contract $contract, Notifier $notifier, AuditTrail $audit)
    {
        $user = $request->user();
        $contract->load(['workspace.owner', 'freelancer.user']);

        $isOwner = $contract->workspace?->owner_id === $user->id;
        $isFreelancer = $user->role === 'freelancer' && $contract->freelancer_id === $user->freelancerProfile?->id;
        abort_unless($isOwner || $isFreelancer, 403, 'Only the contract parties can open a dispute.');
        abort_unless(in_array($contract->status, ['active', 'disputed'], true), 409, 'Only active contracts can be disputed.');
        abort_if(Dispute::where('contract_id', $contract->id)->where('status', 'open')->exists(), 409, 'A dispute is already open on this contract.');

        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:5000']]);

        $dispute = Dispute::create([
            'contract_id' => $contract->id,
            'opened_by' => $user->id,
            'reason' => $data['reason'],
            'status' => 'open',
        ]);
        DisputeEvent::create([
            'dispute_id' => $dispute->id,
            'actor_id' => $user->id,
            'status' => 'open',
            'comment' => $data['reason'],
            'created_at' => now(),
        ]);
        $contract->update(['status' => 'disputed']);

        $recipient = $isOwner ? $contract->freelancer->user : $contract->workspace->owner;
        $notifier->send($recipient, 'Dispute opened', $contract->title.' — '.$data['reason'], $isOwner ? '/pro/contracts' : '/app/team');
        $audit->record($user, 'contract.disputed', Dispute::class, $dispute->id, $contract->workspace_id, null, ['reason' => $data['reason']]);

        return response()->json(['data' => ApiMapper::camel($dispute->fresh()->toArray())], 201);
    }
}
