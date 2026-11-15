<?php

namespace App\Services;

use App\Contracts\NotificationMailProvider;
use App\Models\Notification;
use App\Models\User;

class Notifier
{
    public function __construct(private NotificationMailProvider $mail) {}

    public function send(User $user, string $title, string $body, string $href): void
    {
        Notification::create(['user_id' => $user->id, 'title' => $title, 'body' => $body, 'href' => $href, 'created_at' => now()]);
        $this->mail->send($user, $title, $body);
    }
}
