<?php

namespace App\Services;

use App\Contracts\PaymentProvider;
use App\Models\Contract;
use App\Models\DirectMessage;
use App\Models\FreelancerProfile;
use App\Models\Grant;
use App\Models\Invitation;
use App\Models\JobBrief;
use App\Models\Milestone;
use App\Models\Proposal;
use App\Models\Thread;
use App\Models\ThreadParticipant;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

class MarketplaceService
{
    public function __construct(private WorkspaceAccess $access, private Notifier $notifier, private PaymentProvider $payments, private AuditTrail $audit) {}

    public function postJob(User $owner, array $data, ?string $jobId = null): JobBrief
    {
        $workspace = $this->access->ownedWorkspace($owner, $data['workspaceId']);
        $attributes = ['workspace_id' => $workspace->id, 'owner_id' => $owner->id, 'title' => $data['title'], 'description' => $data['description'], 'skills' => $data['skills'], 'platforms' => $data['platforms'] ?? [], 'budget' => $data['budget'], 'duration_days' => $data['durationDays'], 'ai_generated' => $data['aiGenerated'] ?? false];
        $job = $jobId ? JobBrief::where('workspace_id', $workspace->id)->findOrFail($jobId) : new JobBrief;
        abort_if($job->exists && $job->status === 'hired', 409, 'A hired brief cannot be edited.');
        $job->fill($attributes)->save();
        return $job;
    }

    public function jobStatus(User $owner, JobBrief $job, string $status): JobBrief
    {
        $this->access->ownedWorkspace($owner, $job->workspace_id);
        abort_unless(in_array($status, ['open', 'closed']), 422);
        abort_if($job->status === 'hired', 409, 'A hired brief cannot be reopened.');
        $job->update(['status' => $status]);
        return $job;
    }

    public function propose(User $user, JobBrief $job, array $data): Proposal
    {
        $profile = $user->freelancerProfile;
        abort_unless($user->role === 'freelancer' && $profile, 403);
        abort_if($profile->vetting !== 'approved' || $user->status !== 'active', 403, 'Complete freelancer vetting before submitting proposals.');
        abort_if($job->status !== 'open', 409, 'This brief is not accepting proposals.');
        return Proposal::updateOrCreate(['job_id' => $job->id, 'freelancer_id' => $profile->id], ['cover' => $data['cover'], 'bid' => $data['bid'], 'days' => $data['days'], 'attachments' => $data['attachments'] ?? [], 'status' => 'sent']);
    }

    public function proposalStatus(User $actor, Proposal $proposal, string $status): Proposal
    {
        $job = $proposal->job;
        if ($actor->role === 'freelancer') {
            abort_unless($proposal->freelancer_id === $actor->freelancerProfile?->id && $status === 'withdrawn', 403);
        } else {
            $this->access->ownedWorkspace($actor, $job->workspace_id);
            abort_unless(in_array($status, ['shortlisted', 'declined']), 422);
        }
        abort_if($job->status === 'hired', 409);
        $proposal->update(['status' => $status]);
        return $proposal;
    }

    public function hire(User $owner, array $data): Contract
    {
        $workspace = $this->access->ownedWorkspace($owner, $data['workspaceId']);
        $freelancer = FreelancerProfile::with('user')->where('vetting', 'approved')->findOrFail($data['freelancerId']);
        abort_if($freelancer->user->status !== 'active', 422, 'This freelancer is not active.');
        $job = ! empty($data['jobId']) ? JobBrief::where('workspace_id', $workspace->id)->findOrFail($data['jobId']) : null;
        abort_if($job && $job->status === 'hired', 409, 'This brief already has a contract.');
        abort_if(Contract::where('workspace_id', $workspace->id)->where('freelancer_id', $freelancer->id)->where('status', 'active')->when($job, fn ($q) => $q->where('job_id', $job->id))->exists(), 409, 'An active contract already exists for this engagement.');

        return DB::transaction(function () use ($owner, $workspace, $freelancer, $job, $data) {
            $contract = Contract::create(['workspace_id' => $workspace->id, 'freelancer_id' => $freelancer->id, 'job_id' => $job?->id, 'title' => $data['title'], 'amount' => $data['amount'], 'terms' => $data['terms'] ?? null, 'status' => 'active', 'started_at' => now()]);
            foreach ($data['milestones'] as $milestone) $contract->milestones()->create(['title' => $milestone['title'], 'amount' => $milestone['amount'], 'due_date' => $milestone['dueDate'] ?? null, 'status' => 'pending']);
            if ($job) {
                $job->update(['status' => 'hired']);
                $job->proposals()->update(['status' => 'declined']);
                $job->proposals()->where('freelancer_id', $freelancer->id)->update(['status' => 'accepted']);
            }
            Grant::create(['workspace_id' => $workspace->id, 'freelancer_id' => $freelancer->id, 'contract_id' => $contract->id, 'updated_by_id' => $owner->id, 'access_enabled' => true, 'permissions' => ['view_analytics' => true, 'draft_campaigns' => true, 'upload_creatives' => true, 'schedule_posts' => false, 'adjust_budgets' => false]]);
            $payment = $this->payments->hold((float) $contract->amount, $contract->id);
            Transaction::create(['workspace_id' => $workspace->id, 'contract_id' => $contract->id, 'amount' => $contract->amount, 'type' => 'escrow_hold', 'status' => 'held', 'provider_reference' => $payment['reference'], 'created_at' => now()]);
            $thread = $this->conversation($owner, $freelancer->user, $workspace, $job?->id, $contract->id);
            DirectMessage::create(['thread_id' => $thread->id, 'from_id' => $owner->id, 'body' => 'Contract started: '.$contract->title.'. Workspace permissions can be changed at any time.', 'created_at' => now()]);
            $this->notifier->send($freelancer->user, 'You were hired', $contract->title.' — review your new client workspace.', '/pro/contracts');
            $this->audit->record($owner, 'contract.hired', Contract::class, $contract->id, $workspace->id, null, ['amount' => $contract->amount]);
            return $contract->load('milestones');
        });
    }

    public function respondInvitation(User $user, Invitation $invitation, bool $accept): Invitation
    {
        $profile = $user->freelancerProfile;
        abort_unless($profile && $invitation->freelancer_id === $profile->id && $invitation->status === 'open', 403);
        $invitation->update(['status' => $accept ? 'accepted' : 'declined']);
        if ($accept) {
            $workspace = Workspace::with('owner')->findOrFail($invitation->workspace_id);
            $this->conversation($workspace->owner, $user, $workspace, $invitation->job_id);
            if ($invitation->job_id) {
                $job = JobBrief::find($invitation->job_id);
                if ($job?->status === 'open') Proposal::updateOrCreate(['job_id' => $job->id, 'freelancer_id' => $profile->id], ['cover' => 'I accepted your AI-matched invitation. '.$invitation->reason, 'bid' => $job->budget, 'days' => $job->duration_days, 'status' => 'sent']);
            }
            $this->notifier->send($workspace->owner, 'Invitation accepted', $user->name.' accepted your invitation. You can now review the proposal and conversation.', '/app/jobs');
        }
        return $invitation;
    }

    public function conversation(User $a, User $b, Workspace $workspace, ?string $jobId = null, ?string $contractId = null): Thread
    {
        $existing = Thread::where('workspace_id', $workspace->id)->whereHas('participants', fn ($q) => $q->where('users.id', $a->id))->whereHas('participants', fn ($q) => $q->where('users.id', $b->id))->first();
        if ($existing) return $existing;
        $thread = Thread::create(['workspace_id' => $workspace->id, 'job_id' => $jobId, 'contract_id' => $contractId, 'last_message' => null, 'updated_at' => now()]);
        ThreadParticipant::create(['thread_id' => $thread->id, 'user_id' => $a->id]);
        ThreadParticipant::create(['thread_id' => $thread->id, 'user_id' => $b->id]);
        return $thread;
    }

    public function milestone(User $actor, Milestone $milestone, string $action): Milestone
    {
        $contract = Contract::with(['workspace.owner', 'freelancer.user'])->findOrFail($milestone->contract_id);
        if ($action === 'submit') {
            abort_unless($actor->role === 'freelancer' && $contract->freelancer_id === $actor->freelancerProfile?->id && $contract->status === 'active', 403);
            abort_unless($milestone->status === 'pending', 409);
            $milestone->update(['status' => 'submitted']);
            $this->notifier->send($contract->workspace->owner, 'Milestone submitted', $milestone->title, '/app/contracts');
        } else {
            $this->access->ownedWorkspace($actor, $contract->workspace_id);
            abort_unless(in_array($action, ['approve', 'pay']), 422);
            if ($action === 'approve') {
                abort_unless($milestone->status === 'submitted', 409);
                $milestone->update(['status' => 'approved']);
            } else {
                abort_unless(in_array($milestone->status, ['approved', 'submitted']), 409, 'Approve the milestone before releasing payment.');
                $hold = Transaction::where('contract_id', $contract->id)->where('type', 'escrow_hold')->firstOrFail();
                $alreadyReleased = (float) Transaction::where('contract_id', $contract->id)->where('type', 'escrow_release')->sum('amount');
                abort_if($alreadyReleased + (float) $milestone->amount > (float) $hold->amount + 0.01, 409, 'This milestone exceeds the remaining escrow balance.');
                $released = $this->payments->release($hold->provider_reference, (float) $milestone->amount);
                Transaction::create(['workspace_id' => $contract->workspace_id, 'contract_id' => $contract->id, 'amount' => $milestone->amount, 'type' => 'escrow_release', 'status' => 'released', 'provider_reference' => $released['reference'].'_'.$milestone->id, 'created_at' => now()]);

                $feePercent = max(0, (float) config('services.rawijli.marketplace_fee_percent', 10));
                if ($feePercent > 0) {
                    $fee = round((float) $milestone->amount * $feePercent / 100, 2);
                    Transaction::create(['workspace_id' => $contract->workspace_id, 'contract_id' => $contract->id, 'amount' => $fee, 'type' => 'marketplace_fee', 'status' => 'paid', 'provider_reference' => $released['reference'].'_fee_'.$milestone->id, 'created_at' => now()]);
                }

                $milestone->update(['status' => 'paid']);
                if ($contract->milestones()->where('status', '!=', 'paid')->count() === 0) {
                    $contract->update(['status' => 'completed']);
                    $hold->update(['status' => 'released']);
                }
            }
            $message = match ($action) {
                'approve' => 'Milestone approved',
                'pay' => 'Milestone payment released',
                default => 'Milestone updated',
            };
            $this->notifier->send($contract->freelancer->user, $message, $milestone->title, '/pro/contracts');
        }
        return $milestone;
    }
}
