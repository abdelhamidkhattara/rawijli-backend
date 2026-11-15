<?php

namespace App\Services;

use App\Models\Approval;
use App\Models\CalendarItem;
use App\Models\Campaign;
use App\Models\Grant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ApprovalService
{
    public function __construct(private WorkspaceAccess $access, private CampaignService $campaigns, private CalendarService $calendar, private Notifier $notifier, private AuditTrail $audit) {}

    public function decide(User $owner, Approval $approval, string $decision): Approval
    {
        $this->access->ownedWorkspace($owner, $approval->workspace_id);
        abort_unless(in_array($decision, ['approved', 'rejected']), 422);
        return DB::transaction(function () use ($owner, $approval, $decision) {
            $approval = Approval::whereKey($approval->id)->lockForUpdate()->firstOrFail();
            abort_if($approval->status !== 'pending', 409, 'This approval has already been decided.');
            $grant = Grant::where('workspace_id', $approval->workspace_id)->where('freelancer_id', $approval->freelancer_id)->where('access_enabled', true)->whereHas('contract', fn ($q) => $q->where('status', 'active'))->first();
            abort_if($decision === 'approved' && ! $grant, 409, 'Access or the underlying contract is no longer active. Reject this stale request.');

            if ($decision === 'approved') $this->apply($owner, $approval);
            else $this->reject($approval);
            $approval->update(['status' => $decision, 'decided_by_id' => $owner->id, 'decided_at' => now()]);
            $freelancerUser = $approval->freelancer->user;
            $this->notifier->send($freelancerUser, 'Request '.$decision, $approval->title, '/pro');
            $this->notifier->send($owner, 'Request '.$decision, $approval->title, '/app/approvals');
            $this->audit->record($owner, 'approval.'.$decision, Approval::class, $approval->id, $approval->workspace_id, ['status' => 'pending'], ['status' => $decision]);
            return $approval->fresh();
        });
    }

    private function apply(User $owner, Approval $approval): void
    {
        $payload = $approval->payload;
        if (in_array($approval->type, ['new_campaign', 'campaign_edit'])) {
            $campaign = Campaign::where('workspace_id', $approval->workspace_id)->findOrFail($payload['campaignId']);
            abort_if($approval->type === 'new_campaign' && $campaign->status !== 'pending_approval', 409, 'Campaign state changed before approval.');
            $this->campaigns->applyApprovedEdit($owner, $campaign, $approval->after_payload);
        } elseif ($approval->type === 'budget_change') {
            $campaign = Campaign::where('workspace_id', $approval->workspace_id)->findOrFail($payload['campaignId']);
            abort_if((float) $campaign->daily_budget !== (float) $payload['from'], 409, 'Campaign budget changed after this request. Reject and request a new review.');
            $campaign->update(['daily_budget' => $payload['to']]);
        } elseif ($approval->type === 'schedule_post') {
            $item = CalendarItem::where('workspace_id', $approval->workspace_id)->findOrFail($payload['itemId']);
            $this->calendar->applyApproved($owner, $item, $approval->after_payload);
        }
    }

    private function reject(Approval $approval): void
    {
        if ($approval->type === 'new_campaign') Campaign::whereKey($approval->payload['campaignId'])->where('status', 'pending_approval')->update(['status' => 'draft']);
        if ($approval->type === 'schedule_post') CalendarItem::whereKey($approval->payload['itemId'])->where('status', 'pending_approval')->update(['status' => 'draft']);
    }
}
