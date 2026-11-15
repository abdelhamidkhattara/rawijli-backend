<?php

namespace App\Providers\Sandbox;

use App\Contracts\PaymentProvider;

class SandboxPaymentProvider implements PaymentProvider
{
    public function hold(float $amount, string $reference): array
    {
        return ['reference' => 'sandbox_hold_'.$reference, 'status' => 'held', 'amount' => $amount];
    }

    public function release(string $reference, ?float $amount = null): array
    {
        return ['reference' => $reference, 'status' => 'released', 'amount' => $amount];
    }

    public function refund(string $reference, ?float $amount = null): array
    {
        return ['reference' => $reference, 'status' => 'refunded', 'amount' => $amount];
    }

    public function subscribe(string $plan, string $reference): array
    {
        return ['reference' => 'sandbox_subscription_'.$reference, 'status' => 'active', 'plan' => $plan];
    }
}
