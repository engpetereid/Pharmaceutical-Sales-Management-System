<?php

namespace App\Providers;

use App\Services\AuditService;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFour();

        // تتبع أحداث الدخول والخروج في سجل التدقيق
        Event::listen(Login::class, function (Login $event) {
            AuditService::logAuth('login_success', $event->user?->email, 'success');
        });

        Event::listen(Failed::class, function (Failed $event) {
            $email = $event->credentials['email'] ?? null;
            AuditService::logAuth('login_failed', $email, 'failed');
        });

        Event::listen(Logout::class, function (Logout $event) {
            if ($event->user) {
                AuditService::logAuth('logout', $event->user->email, 'success');
            }
        });
    }
}
