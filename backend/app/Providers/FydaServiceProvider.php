<?php

namespace App\Providers;

use App\Services\Fyda\FydaOidcClient;
use Illuminate\Support\ServiceProvider;

class FydaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FydaOidcClient::class, function ($app) {
            return new FydaOidcClient(
                config: $app['config']->get('fyda.oidc', []),
            );
        });
    }
}
