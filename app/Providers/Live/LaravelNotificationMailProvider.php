<?php

namespace App\Providers\Live;

use App\Contracts\NotificationMailProvider;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class LaravelNotificationMailProvider implements NotificationMailProvider
{
    public function send(User $user, string $subject, string $body): void
    {
        try {
            Mail::raw($body, function ($message) use ($user, $subject) {
                $message->to($user->email)->subject($subject);
            });
        } catch (\Throwable $e) {
            // Notifications are already persisted; email is best-effort.
            Log::warning('Notification email failed', ['user_id' => $user->id, 'subject' => $subject, 'error' => $e->getMessage()]);
        }
    }
}
