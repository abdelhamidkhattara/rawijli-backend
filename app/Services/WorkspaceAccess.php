<?php

namespace App\Services;

use App\Models\Grant;
use App\Models\User;
use App\Models\Workspace;
use Symfony\Component\HttpKernel\Exception\HttpException;

class WorkspaceAccess
{
    public const PERMISSIONS = ['view_analytics', 'upload_creatives', 'draft_campaigns', 'adjust_budgets', 'schedule_posts'];

    public function ownedWorkspace(User $user, ?string $workspaceId = null): Workspace
    {
        $workspace = $user->workspace;
        abort_if(! $workspace || ($workspaceId && $workspace->id !== $workspaceId), 403, 'You do not own this workspace.');
        return $workspace;
    }

    public function grant(User $user, string $workspaceId): ?Grant
    {
        if ($user->role !== 'freelancer') return null;
        $profile = $user->freelancerProfile;
        if (! $profile) return null;
        return Grant::where('workspace_id', $workspaceId)
            ->where('freelancer_id', $profile->id)
            ->where('access_enabled', true)
            ->whereHas('contract', fn ($q) => $q->where('status', 'active'))
            ->first();
    }

    public function workspace(User $user, string $workspaceId, ?string $permission = null): Workspace
    {
        $workspace = Workspace::findOrFail($workspaceId);
        if ($user->isAdmin() || $workspace->owner_id === $user->id) return $workspace;
        $grant = $this->grant($user, $workspaceId);
        if (! $grant || ($permission && ! ($grant->permissions[$permission] ?? false))) {
            throw new HttpException(403, 'Workspace permission denied.');
        }
        return $workspace;
    }

    public function canView(User $user, Workspace $workspace): bool
    {
        if ($user->isAdmin() || $workspace->owner_id === $user->id) return true;
        $grant = $this->grant($user, $workspace->id);
        return (bool) ($grant && ($grant->permissions['view_analytics'] ?? false));
    }

    public function workspaceIds(User $user): array
    {
        if ($user->isAdmin()) return Workspace::pluck('id')->all();
        if ($user->role === 'owner') return $user->workspace ? [$user->workspace->id] : [];
        return Grant::where('freelancer_id', $user->freelancerProfile?->id)
            ->where('access_enabled', true)
            ->whereHas('contract', fn ($q) => $q->where('status', 'active'))
            ->pluck('workspace_id')->all();
    }
}
