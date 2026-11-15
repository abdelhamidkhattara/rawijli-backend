<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;

class AuditTrail
{
    public function record(?User $actor, string $action, string $subjectType, string $subjectId, ?string $workspaceId = null, $before = null, $after = null): void
    {
        AuditLog::create([
            'actor_id' => $actor?->id,
            'workspace_id' => $workspaceId,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'before' => $before,
            'after' => $after,
            'ip_address' => request()?->ip(),
            'created_at' => now(),
        ]);
    }
}
