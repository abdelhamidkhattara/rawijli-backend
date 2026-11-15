<?php

namespace App\Providers\Sandbox;

use App\Contracts\NotificationMailProvider;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class LogNotificationMailProvider implements NotificationMailProvider
{
    public function send(User $user, string $subject, string $body): void
    {
        Log::info('Sandbox notification email', ['user_id' => $user->id, 'subject' => $subject]);
    }
}
