<?php

namespace App\Services;

use App\Http\Resources\UserResource;
use App\Models\Ad;
use App\Models\AdSet;
use App\Models\Approval;
use App\Models\AuditLog;
use App\Models\CalendarItem;
use App\Models\Campaign;
use App\Models\ChatMessage;
use App\Models\Connection;
use App\Models\Contract;
use App\Models\Creative;
use App\Models\Deliverable;
use App\Models\DirectMessage;
use App\Models\Dispute;
use App\Models\FreelancerProfile;
use App\Models\Grant;
use App\Models\Invitation;
use App\Models\JobBrief;
use App\Models\MetricPoint;
use App\Models\Notification;
use App\Models\Proposal;
use App\Models\Report;
use App\Models\Subscription;
use App\Models\Thread;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Workspace;
use App\Support\ApiMapper;
use Illuminate\Support\Collection;

class BootstrapService
{
    public function __construct(private WorkspaceAccess $access) {}

    public function for(User $user): array
    {
        $user->load(['workspace', 'freelancerProfile']);
        $workspaceIds = $this->access->workspaceIds($user);
        $isAdmin = $user->isAdmin();
        $profileId = $user->freelancerProfile?->id;

        $contracts = Contract::with('milestones')->when(! $isAdmin, function ($q) use ($user, $workspaceIds, $profileId) {
            $q->where(fn ($q) => $q->whereIn('workspace_id', $workspaceIds)->when($profileId, fn ($q) => $q->orWhere('freelancer_id', $profileId)));
        })->get();
        $contractIds = $contracts->pluck('id');
        $ownerJobIds = JobBrief::when(! $isAdmin, fn ($q) => $q->whereIn('workspace_id', $workspaceIds))->pluck('id');

        $jobs = JobBrief::when(! $isAdmin, function ($q) use ($user, $workspaceIds, $profileId, $ownerJobIds) {
            if ($user->role === 'owner') $q->whereIn('workspace_id', $workspaceIds);
            elseif ($user->role === 'freelancer') $q->where(function ($q) use ($profileId, $ownerJobIds) {
                $q->where('status', 'open')->orWhereIn('id', $ownerJobIds)
                    ->orWhereHas('proposals', fn ($q) => $q->where('freelancer_id', $profileId));
            });
        })->get();
        $jobIds = $jobs->pluck('id');

        $threads = Thread::with('participants')->when(! $isAdmin, fn ($q) => $q->whereHas('participants', fn ($q) => $q->where('users.id', $user->id)))->get();
        $threadIds = $threads->pluck('id');
        $campaignWorkspaces = $user->role === 'freelancer'
            ? Grant::where('freelancer_id', $profileId)->where('access_enabled', true)->get()->filter(fn ($g) => $g->permissions['view_analytics'] ?? false)->pluck('workspace_id')->all()
            : $workspaceIds;
        $campaigns = Campaign::whereIn('workspace_id', $campaignWorkspaces)->get();
        $campaignIds = $campaigns->pluck('id');
        $sets = AdSet::whereIn('campaign_id', $campaignIds)->get();

        $profiles = FreelancerProfile::with(['user', 'packages', 'portfolio'])->where(function ($q) use ($isAdmin, $profileId, $contracts) {
            if ($isAdmin) return;
            $q->where(fn ($q) => $q->where('vetting', 'approved')->where('public_visible', true));
            if ($profileId) $q->orWhere('id', $profileId);
            if ($contracts->isNotEmpty()) $q->orWhereIn('id', $contracts->pluck('freelancer_id'));
        })->get();
        $userIds = $profiles->pluck('user_id')->merge([$user->id])->merge(Workspace::whereIn('id', $workspaceIds)->pluck('owner_id'))->merge($threads->flatMap(fn ($t) => $t->participants->pluck('id')))->unique()->values();
        $users = $isAdmin ? User::with(['workspace', 'freelancerProfile'])->get() : User::with(['workspace', 'freelancerProfile'])->whereIn('id', $userIds)->get();

        $state = [
            'version' => 2,
            'users' => $users->map(fn ($u) => (new UserResource($u))->resolve()),
            'workspaces' => Workspace::whereIn('id', $workspaceIds)->get()->toArray(),
            'connections' => Connection::whereIn('workspace_id', $workspaceIds)->get()->toArray(),
            'campaigns' => $campaigns->map(fn ($c) => $this->campaign($c)),
            'adSets' => $sets->map(fn ($s) => $this->metrics($s)),
            'ads' => Ad::whereIn('ad_set_id', $sets->pluck('id'))->get()->map(fn ($a) => $this->metrics($a)),
            'series' => MetricPoint::whereIn('workspace_id', $campaignWorkspaces)->orderBy('date')->get()
                ->groupBy('workspace_id')
                ->map(fn ($rows) => $rows->groupBy(fn ($p) => $p->date->toDateString())
                    ->map(fn ($group, $date) => ['date' => $date, 'spend' => round($group->sum('spend'), 2), 'revenue' => round($group->sum('revenue'), 2), 'conversions' => (int) $group->sum('conversions')])
                    ->values()->all())
                ->toArray(),
            'freelancers' => $profiles->map(fn ($f) => $this->profile($f)),
            'jobs' => $jobs->toArray(),
            'proposals' => Proposal::whereIn('job_id', $jobIds)->when($profileId && ! $isAdmin, fn ($q) => $q->where('freelancer_id', $profileId))->get()->toArray(),
            'invitations' => Invitation::when(! $isAdmin, function ($q) use ($user, $workspaceIds, $profileId) {
                $user->role === 'freelancer' ? $q->where('freelancer_id', $profileId) : $q->whereIn('workspace_id', $workspaceIds);
            })->get()->toArray(),
            'contracts' => $contracts->map(fn ($c) => $this->contract($c)),
            'grants' => Grant::whereIn('workspace_id', $workspaceIds)->when($profileId && ! $isAdmin, fn ($q) => $q->where('freelancer_id', $profileId))->get()->toArray(),
            'approvals' => Approval::whereIn('workspace_id', $workspaceIds)->when($profileId && ! $isAdmin, fn ($q) => $q->where('freelancer_id', $profileId))->latest()->get()->toArray(),
            'calendar' => CalendarItem::whereIn('workspace_id', $workspaceIds)->get()->toArray(),
            'creatives' => Creative::whereIn('workspace_id', $workspaceIds)->latest()->get()->toArray(),
            'chats' => ChatMessage::whereIn('workspace_id', $workspaceIds)->where('user_id', $user->id)->orderBy('created_at')->get()->toArray(),
            'threads' => $threads->map(function ($t) { $row = $t->toArray(); $row['participants'] = $t->participants->pluck('id')->all(); return $row; }),
            'messages' => DirectMessage::whereIn('thread_id', $threadIds)->orderBy('created_at')->get()->toArray(),
            'deliverables' => Deliverable::with(['versions', 'comments'])->whereIn('contract_id', $contractIds)->latest('submitted_at')->get()->toArray(),
            'disputes' => Dispute::with('events')->whereIn('contract_id', $contractIds)->get()->toArray(),
            'notifications' => Notification::where('user_id', $user->id)->latest('created_at')->get()->map(function ($n) { $row = $n->toArray(); $row['read'] = $n->read_at !== null; return $row; }),
            'subscriptions' => Subscription::whereIn('workspace_id', $workspaceIds)->get()->toArray(),
            'transactions' => Transaction::whereIn('workspace_id', $workspaceIds)->latest('created_at')->get()->toArray(),
            'reports' => Report::whereIn('workspace_id', $workspaceIds)->where('user_id', $user->id)->latest()->get()->toArray(),
            'auditLogs' => $isAdmin ? AuditLog::latest('created_at')->limit(200)->get()->toArray() : AuditLog::whereIn('workspace_id', $workspaceIds)->latest('created_at')->limit(100)->get()->toArray(),
        ];

        return ApiMapper::camel($state);
    }

    private function profile(FreelancerProfile $profile): array
    {
        $row = $profile->toArray();
        $row['name'] = $profile->user->name;
        $row['avatar'] = $profile->user->avatar;
        $row['packages'] = $profile->packages->toArray();
        $row['portfolio'] = $profile->portfolio->toArray();
        return $row;
    }

    private function campaign(Campaign $campaign): array
    {
        $row = $this->metrics($campaign);
        $row['createdBy'] = $campaign->created_by_role;
        $row['createdById'] = $campaign->created_by_id;
        unset($row['created_by_role']);
        return $row;
    }

    private function metrics($model): array
    {
        $row = $model->toArray();
        $row['roas'] = $model->spend > 0 ? round($model->revenue / $model->spend, 2) : 0;
        $row['ctr'] = $model->impressions > 0 ? round($model->clicks / $model->impressions * 100, 2) : 0;
        $row['cpc'] = $model->clicks > 0 ? round($model->spend / $model->clicks, 2) : 0;
        $row['cpm'] = $model->impressions > 0 ? round($model->spend / $model->impressions * 1000, 2) : 0;
        return $row;
    }

    private function contract(Contract $contract): array
    {
        $row = $contract->toArray();
        $row['milestones'] = $contract->milestones->map(function ($m) { $row = $m->toArray(); $row['done'] = in_array($m->status, ['approved', 'paid']); return $row; })->all();
        return $row;
    }
}
