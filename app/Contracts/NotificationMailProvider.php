<?php

namespace App\Contracts;

use App\Models\User;

interface NotificationMailProvider
{
    public function send(User $user, string $subject, string $body): void;
}
