<?php

namespace App\Contracts;

interface PaymentProvider
{
    public function hold(float $amount, string $reference): array;
    public function release(string $reference, ?float $amount = null): array;
    public function refund(string $reference, ?float $amount = null): array;
    public function subscribe(string $plan, string $reference): array;
}
