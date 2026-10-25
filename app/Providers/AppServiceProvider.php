<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
        $this->app->singleton(PaymentGateway::class, fn () => match (config('orders.gateway.driver')) {
            'sandbox' => new SandboxGateway(),
            default => throw new \RuntimeException('Unknown PAYMENT_GATEWAY "'.config('orders.gateway.driver').'".'),
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
