<?php

namespace App\Services;

use App\Models\Ad;
use App\Models\AdSet;
use App\Models\Approval;
use App\Models\Campaign;
use App\Models\Connection;
use App\Models\Creative;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CampaignService
{
    public function __construct(private WorkspaceAccess $access, private Notifier $notifier, private AuditTrail $audit) {}

    public function create(User $user, array $data): Campaign
    {
        $workspace = $this->access->workspace($user, $data['workspaceId'], 'draft_campaigns');
        $this->validateAssets($workspace->id, $data);
        $freelancer = $user->role === 'freelancer';
        return DB::transaction(function () use ($user, $data, $workspace, $freelancer) {
            $status = $freelancer ? 'pending_approval' : (($data['publish'] ?? false) ? 'active' : 'draft');
            $campaign = new Campaign(['workspace_id' => $workspace->id, 'created_by_id' => $user->id, 'created_by_role' => $freelancer ? 'freelancer' : 'owner', 'status' => $status]);
            $this->fillAndSave($campaign, $data);
            $this->syncHierarchy($campaign, $data['adSets'], $status);
            if ($freelancer) {
                Approval::create(['workspace_id' => $workspace->id, 'freelancer_id' => $user->freelancerProfile->id, 'created_by_id' => $user->id, 'type' => 'new_campaign', 'title' => $campaign->name, 'summary' => $user->name.' submitted a '.$campaign->platform.' campaign at $'.$campaign->daily_budget.'/day.', 'after_payload' => $data, 'payload' => ['campaignId' => $campaign->id], 'status' => 'pending']);
                $this->notifier->send($workspace->owner, 'Campaign approval needed', $user->name.' submitted '.$campaign->name, '/app/approvals');
            }
            $this->audit->record($user, 'campaign.created', Campaign::class, $campaign->id, $workspace->id, null, ['status' => $status]);
            return $campaign->load('adSets.ads');
        });
    }

    public function update(User $user, Campaign $campaign, array $data): Campaign
    {
        $this->access->workspace($user, $campaign->workspace_id, 'draft_campaigns');
        $this->validateAssets($campaign->workspace_id, $data);
        if ($user->role === 'freelancer') {
            DB::transaction(function () use ($user, $campaign, $data) {
                $before = $campaign->load('adSets.ads')->toArray();
                Approval::create(['workspace_id' => $campaign->workspace_id, 'freelancer_id' => $user->freelancerProfile->id, 'created_by_id' => $user->id, 'type' => 'campaign_edit', 'title' => 'Edit '.$campaign->name, 'summary' => $user->name.' requested a full campaign edit.', 'before_payload' => $before, 'after_payload' => $data, 'payload' => ['campaignId' => $campaign->id], 'status' => 'pending']);
                $this->notifier->send($campaign->workspace()->first()->owner, 'Campaign edit approval needed', $user->name.' requested changes to '.$campaign->name, '/app/approvals');
            });
            return $campaign->fresh('adSets.ads');
        }
        return DB::transaction(function () use ($user, $campaign, $data) {
            $before = $campaign->toArray();
            $this->fillAndSave($campaign, $data);
            if (($data['publish'] ?? false) && $campaign->status === 'draft') $campaign->update(['status' => 'active']);
            $campaign->adSets()->delete();
            $this->syncHierarchy($campaign, $data['adSets'], $campaign->status);
            $this->audit->record($user, 'campaign.updated', Campaign::class, $campaign->id, $campaign->workspace_id, $before, $campaign->fresh()->toArray());
            return $campaign->fresh('adSets.ads');
        });
    }

    public function applyApprovedEdit(User $owner, Campaign $campaign, array $data): void
    {
        $this->fillAndSave($campaign, $data);
        $campaign->adSets()->delete();
        $this->syncHierarchy($campaign, $data['adSets'], $campaign->status === 'pending_approval' ? 'active' : $campaign->status);
        $campaign->update(['status' => 'active']);
    }

    public function budgetRequest(User $user, Campaign $campaign, float $to, string $reason): Approval
    {
        $this->access->workspace($user, $campaign->workspace_id, 'adjust_budgets');
        abort_unless($user->role === 'freelancer', 422, 'Owners can edit campaign budgets directly.');
        return DB::transaction(function () use ($user, $campaign, $to, $reason) {
            $approval = Approval::create(['workspace_id' => $campaign->workspace_id, 'freelancer_id' => $user->freelancerProfile->id, 'created_by_id' => $user->id, 'type' => 'budget_change', 'title' => 'Budget: '.$campaign->name, 'summary' => $user->name.' requested $'.$campaign->daily_budget.' → $'.$to.'/day. '.$reason, 'before_payload' => ['dailyBudget' => $campaign->daily_budget], 'after_payload' => ['dailyBudget' => $to], 'payload' => ['campaignId' => $campaign->id, 'from' => $campaign->daily_budget, 'to' => $to, 'reason' => $reason], 'status' => 'pending']);
            $this->notifier->send($campaign->workspace()->first()->owner, 'Budget approval needed', $approval->title, '/app/approvals');
            return $approval;
        });
    }

    public function changeStatus(User $user, Campaign $campaign, string $status): Campaign
    {
        $this->access->ownedWorkspace($user, $campaign->workspace_id);
        abort_unless(in_array($status, ['active', 'paused', 'archived']), 422, 'Unsupported campaign status.');
        if ($status === 'active') {
            abort_if($campaign->status === 'pending_approval', 409, 'This campaign requires approval.');
            abort_if($campaign->adSets()->count() === 0, 422, 'Add at least one ad set before publishing.');
        }
        $before = $campaign->status;
        $campaign->update(['status' => $status]);
        $campaign->adSets()->where('status', '!=', 'archived')->update(['status' => $status]);
        $this->audit->record($user, 'campaign.status_changed', Campaign::class, $campaign->id, $campaign->workspace_id, ['status' => $before], ['status' => $status]);
        return $campaign;
    }

    private function fillAndSave(Campaign $campaign, array $data): void
    {
        $campaign->fill(['connection_id' => $data['connectionId'] ?? null, 'name' => $data['name'], 'platform' => $data['platform'], 'objective' => $data['objective'], 'daily_budget' => $data['dailyBudget'], 'lifetime_budget' => $data['lifetimeBudget'] ?? null, 'starts_at' => $data['startsAt'] ?? null, 'ends_at' => $data['endsAt'] ?? null]);
        $campaign->save();
    }

    private function syncHierarchy(Campaign $campaign, array $sets, string $status): void
    {
        foreach ($sets as $setData) {
            $set = $campaign->adSets()->create(['name' => $setData['name'], 'targeting' => $setData['targeting'], 'daily_budget' => $setData['dailyBudget'], 'status' => $status]);
            foreach ($setData['ads'] as $adData) {
                $set->ads()->create(['creative_id' => $adData['creativeId'] ?? null, 'name' => $adData['name'], 'format' => $adData['format'], 'headline' => $adData['headline'], 'primary_text' => $adData['primaryText'], 'destination_url' => $adData['destinationUrl'] ?? null, 'status' => $status]);
            }
        }
    }

    private function validateAssets(string $workspaceId, array $data): void
    {
        if (! empty($data['connectionId'])) {
            $connection = Connection::where('workspace_id', $workspaceId)->findOrFail($data['connectionId']);
            abort_if($connection->platform !== $data['platform'], 422, 'The connection platform does not match the campaign.');
            if (($data['publish'] ?? false)) abort_if($connection->status !== 'connected', 422, 'Reconnect the selected account before publishing.');
        }
        $creativeIds = collect($data['adSets'])->flatMap(fn ($s) => collect($s['ads'])->pluck('creativeId'))->filter()->values();
        if ($creativeIds->isNotEmpty()) abort_if(Creative::where('workspace_id', $workspaceId)->whereIn('id', $creativeIds)->count() !== $creativeIds->unique()->count(), 422, 'A selected creative is not available in this workspace.');
    }
}
