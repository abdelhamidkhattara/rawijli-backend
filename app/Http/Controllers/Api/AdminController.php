<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Dispute;
use App\Models\DisputeEvent;
use App\Models\FreelancerProfile;
use App\Models\User;
use App\Services\AuditTrail;
use App\Support\ApiMapper;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    public function updateUser(Request $request, User $user, AuditTrail $audit)
    {
        $this->authorizeAdmin($request);
        $data = $request->validate([
            'status' => ['sometimes', Rule::in(['active', 'pending', 'suspended'])],
            'plan' => ['sometimes', Rule::in(['starter', 'growth', 'scale'])],
            'aiCreditsLimit' => ['sometimes', 'integer', 'min:0', 'max:1000000'],
            'aiCreditsUsed' => ['sometimes', 'integer', 'min:0', 'max:1000000'],
        ]);
        abort_if($data === [], 422, 'Provide at least one field to update.');
        abort_if(isset($data['plan']) && $user->role !== 'owner', 422, 'Only owner accounts have a subscription plan.');
        abort_if(($data['status'] ?? 'active') !== 'active' && $user->id === $request->user()->id, 422, 'You cannot suspend your own admin account.');

        $before = $user->only(['status', 'plan', 'ai_credits_limit', 'ai_credits_used']);
        $attributes = [];
        if (array_key_exists('status', $data)) $attributes['status'] = $data['status'];
        if (array_key_exists('plan', $data)) $attributes['plan'] = $data['plan'];
        if (array_key_exists('aiCreditsLimit', $data)) $attributes['ai_credits_limit'] = $data['aiCreditsLimit'];
        if (array_key_exists('aiCreditsUsed', $data)) $attributes['ai_credits_used'] = $data['aiCreditsUsed'];
        $user->update($attributes);

        $audit->record($request->user(), 'admin.user_updated', User::class, $user->id, $user->workspace?->id, $before, $attributes);
        return response()->json(['data' => ApiMapper::camel($user->fresh()->toArray())]);
    }

    public function vet(Request $request, FreelancerProfile $freelancer, AuditTrail $audit)
    {
        $this->authorizeAdmin($request);
        $data = $request->validate(['decision' => ['required', Rule::in(['approved', 'rejected'])]]);
        $before = ['vetting' => $freelancer->vetting, 'public_visible' => $freelancer->public_visible];
        $freelancer->update([
            'vetting' => $data['decision'],
            'public_visible' => $data['decision'] === 'approved',
            'vetting_reason' => $data['decision'] === 'approved' ? null : ($freelancer->vetting_reason ?? 'Rejected during admin review.'),
        ]);
        $freelancer->user->update(['status' => $data['decision'] === 'approved' ? 'active' : 'suspended']);
        $audit->record($request->user(), 'admin.freelancer_vetted', FreelancerProfile::class, $freelancer->id, null, $before, ['vetting' => $data['decision']]);
        return response()->json(['data' => ApiMapper::camel($freelancer->fresh()->toArray())]);
    }

    public function resolveDispute(Request $request, Dispute $dispute, AuditTrail $audit)
    {
        $this->authorizeAdmin($request);
        abort_if($dispute->status === 'resolved', 409, 'This dispute is already resolved.');
        $data = $request->validate(['resolution' => ['nullable', 'string', 'max:3000']]);
        $resolution = $data['resolution'] ?? 'Resolved from the admin workspace. Review escrow in the contract record before releasing funds.';
        $before = ['status' => $dispute->status];
        $dispute->update(['status' => 'resolved', 'resolution' => $resolution]);
        DisputeEvent::create([
            'dispute_id' => $dispute->id,
            'actor_id' => $request->user()->id,
            'status' => 'resolved',
            'comment' => $resolution,
            'created_at' => now(),
        ]);
        $audit->record($request->user(), 'admin.dispute_resolved', Dispute::class, $dispute->id, $dispute->contract?->workspace_id, $before, ['status' => 'resolved']);
        return response()->json(['data' => ApiMapper::camel($dispute->fresh()->toArray())]);
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Admin access required.');
    }
}
