<?php

namespace App\Providers;

use App\Contracts\ImageGenerator;
use App\Contracts\MarketingAI;
use App\Contracts\MediaStorage;
use App\Contracts\MetaConnector;
use App\Contracts\NotificationMailProvider;
use App\Contracts\PaymentProvider;
use App\Contracts\TikTokConnector;
use App\Models\Workspace;
use App\Policies\WorkspacePolicy;
use App\Providers\Live\DeepSeekMarketingAI;
use App\Providers\Live\DiskMediaStorage;
use App\Providers\Live\LaravelNotificationMailProvider;
use App\Providers\Live\MetaConnector as LiveMetaConnector;
use App\Providers\Live\TikTokConnector as LiveTikTokConnector;
use App\Providers\Sandbox\SandboxMarketingAI;
use App\Providers\Sandbox\SandboxMetaConnector;
use App\Providers\Sandbox\SandboxPaymentProvider;
use App\Providers\Sandbox\SandboxTikTokConnector;
use App\Providers\Sandbox\SvgImageGenerator;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(MetaConnector::class, function () {
            $configured = filled(config('services.meta.app_id')) && filled(config('services.meta.app_secret'));

            return $this->useLive('integrations', $configured) ? new LiveMetaConnector : new SandboxMetaConnector;
        });

        $this->app->bind(TikTokConnector::class, function () {
            $configured = filled(config('services.tiktok.client_key')) && filled(config('services.tiktok.client_secret'));

            return $this->useLive('integrations', $configured) ? new LiveTikTokConnector : new SandboxTikTokConnector;
        });

        $this->app->bind(MarketingAI::class, function () {
            $configured = filled(config('services.deepseek.key'));
            $driver = (string) config('services.ai.driver', 'auto');
            $live = $driver === 'deepseek' || ($driver === 'auto' && $configured);

            return $live ? $this->app->make(DeepSeekMarketingAI::class) : $this->app->make(SandboxMarketingAI::class);
        });

        $this->app->bind(ImageGenerator::class, SvgImageGenerator::class);
        $this->app->bind(MediaStorage::class, DiskMediaStorage::class);
        $this->app->bind(PaymentProvider::class, SandboxPaymentProvider::class);
        $this->app->bind(NotificationMailProvider::class, LaravelNotificationMailProvider::class);
    }

    public function boot(): void
    {
        Gate::policy(Workspace::class, WorkspacePolicy::class);

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });
    }

    private function useLive(string $group, bool $configured): bool
    {
        $driver = (string) config('services.'.$group.'.driver', 'auto');

        return $driver === 'live' || ($driver === 'auto' && $configured);
    }
}
