<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Workspace;
use App\Services\WorkspaceAccess;

class WorkspacePolicy
{
    public function view(User $user, Workspace $workspace): bool
    {
        return app(WorkspaceAccess::class)->canView($user, $workspace);
    }

    public function manage(User $user, Workspace $workspace): bool
    {
        return $user->isAdmin() || $workspace->owner_id === $user->id;
    }
}
