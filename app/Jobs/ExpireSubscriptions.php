<?php

namespace App\Jobs;

use App\Models\Subscription;
use App\Services\Notifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExpireSubscriptions implements ShouldQueue
{
    use Queueable;

    public function handle(Notifier $notifier): void
    {
        Subscription::with('workspace.owner')
            ->where('status', 'trialing')
            ->whereNotNull('current_period_ends_at')
            ->where('current_period_ends_at', '<', now())
            ->cursor()
            ->each(function (Subscription $subscription) use ($notifier) {
                $subscription->update(['status' => 'cancelled']);
                if ($subscription->workspace?->owner) {
                    $notifier->send(
                        $subscription->workspace->owner,
                        'Trial ended',
                        'Your Rawijli trial has ended. Choose a plan to keep campaigns, connections, and approvals running.',
                        '/pricing',
                    );
                }
            });
    }
}
