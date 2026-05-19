<?php

declare(strict_types=1);

namespace App\Providers;

use Domain\Contact\Contracts\TelephonyGateway;
use Domain\Contact\Gateways\FakeTelephonyGateway;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TelephonyGateway::class, FakeTelephonyGateway::class);
    }
}
