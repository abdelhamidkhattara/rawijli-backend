<?php

namespace App\Services;

use App\Jobs\PublishCalendarItem;
use App\Models\Approval;
use App\Models\CalendarItem;
use App\Models\Connection;
use App\Models\Creative;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CalendarService
{
    public function __construct(private WorkspaceAccess $access, private Notifier $notifier) {}

    public function create(User $user, array $data): CalendarItem
    {
        $workspace = $this->access->workspace($user, $data['workspaceId'], 'schedule_posts');
        $this->validateItem($workspace->id, $workspace->timezone, $data);
        $freelancer = $user->role === 'freelancer';
        $item = DB::transaction(function () use ($user, $workspace, $data, $freelancer) {
            $item = CalendarItem::create($this->attributes($data, $workspace->id, $workspace->timezone, $user->id, $freelancer ? 'pending_approval' : ($data['status'] ?? 'scheduled')));
            if ($freelancer) {
                Approval::create(['workspace_id' => $workspace->id, 'freelancer_id' => $user->freelancerProfile->id, 'created_by_id' => $user->id, 'type' => 'schedule_post', 'title' => 'Schedule: '.$item->title, 'summary' => $user->name.' requested '.$item->channel.' publication on '.$item->date->toDateString().' at '.$item->time.'.', 'after_payload' => $data, 'payload' => ['itemId' => $item->id], 'status' => 'pending']);
                $this->notifier->send($workspace->owner, 'Post approval needed', $item->title, '/app/approvals');
            }
            return $item;
        });
        if ($item->status === 'scheduled') $this->dispatchIfDue($item);
        return $item;
    }

    public function update(User $user, CalendarItem $item, array $data): CalendarItem
    {
        $workspace = $this->access->workspace($user, $item->workspace_id, 'schedule_posts');
        $this->validateItem($workspace->id, $workspace->timezone, $data);
        abort_if($data['workspaceId'] !== $item->workspace_id, 422);
        if ($user->role === 'freelancer') {
            $before = $item->toArray();
            Approval::create(['workspace_id' => $item->workspace_id, 'freelancer_id' => $user->freelancerProfile->id, 'created_by_id' => $user->id, 'type' => 'schedule_post', 'title' => 'Edit schedule: '.$item->title, 'summary' => $user->name.' requested changes to a scheduled item.', 'before_payload' => $before, 'after_payload' => $data, 'payload' => ['itemId' => $item->id], 'status' => 'pending']);
            $this->notifier->send($workspace->owner, 'Post edit approval needed', $item->title, '/app/approvals');
            return $item;
        }
        $item->update($this->attributes($data, $item->workspace_id, $workspace->timezone, $item->created_by_id, $data['status'] ?? 'scheduled'));
        if ($item->status === 'scheduled') $this->dispatchIfDue($item);
        return $item;
    }

    public function cancel(User $user, CalendarItem $item): CalendarItem
    {
        $this->access->ownedWorkspace($user, $item->workspace_id);
        abort_if($item->status === 'published', 409, 'Published items cannot be cancelled.');
        $item->update(['status' => 'cancelled']);
        return $item;
    }

    public function applyApproved(User $owner, CalendarItem $item, array $data): void
    {
        $item->update($this->attributes($data, $item->workspace_id, $item->timezone, $item->created_by_id, 'scheduled'));
        $this->dispatchIfDue($item);
    }

    private function validateItem(string $workspaceId, string $timezone, array $data): void
    {
        $date = Carbon::createFromFormat('Y-m-d H:i', $data['date'].' '.$data['time'], $timezone);
        abort_if($date->lessThan(now()->subMinute()) && ($data['status'] ?? 'scheduled') !== 'draft', 422, 'Scheduled posts cannot be in the past.');
        if (! empty($data['creativeId'])) abort_if(! Creative::where('workspace_id', $workspaceId)->whereKey($data['creativeId'])->exists(), 422, 'Creative not found in this workspace.');
        if (($data['status'] ?? 'scheduled') === 'scheduled') {
            $kind = $data['channel'] === 'instagram' ? 'profile' : ($data['channel'] === 'facebook' ? 'page' : 'tiktok_account');
            abort_if(! Connection::where('workspace_id', $workspaceId)->where('kind', $kind)->where('status', 'connected')->exists(), 422, 'Connect the required social account first, or save this item as a draft.');
        }
    }

    private function attributes(array $data, string $workspaceId, string $timezone, ?string $userId, string $status): array
    {
        return ['workspace_id' => $workspaceId, 'creative_id' => $data['creativeId'] ?? null, 'created_by_id' => $userId, 'date' => $data['date'], 'time' => $data['time'], 'timezone' => $timezone, 'channel' => $data['channel'], 'kind' => $data['kind'], 'title' => $data['title'], 'caption' => $data['caption'], 'status' => $status, 'last_error' => null];
    }

    private function dispatchIfDue(CalendarItem $item): void
    {
        $due = Carbon::parse($item->date->toDateString().' '.$item->time, $item->timezone);
        if ($due->lessThanOrEqualTo(now())) PublishCalendarItem::dispatch($item->id)->afterCommit();
    }
}
