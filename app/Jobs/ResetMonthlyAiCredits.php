<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ResetMonthlyAiCredits implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $reset = User::where('ai_credits_used', '>', 0)->update(['ai_credits_used' => 0]);
        Log::info('Monthly AI credits reset', ['users' => $reset]);
    }
}
