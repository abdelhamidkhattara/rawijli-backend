<?php

namespace App\Contracts;

use App\Models\User;
use App\Models\Workspace;

interface MarketingAI
{
    public function respond(Workspace $workspace, User $user, string $message): array;
    public function jobBrief(Workspace $workspace): array;
}
