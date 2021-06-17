<?php

namespace IN10\ApiGatewayDrupal;

use Exception;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ApiGatewayDrupalServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadRoutesFrom(__DIR__ . '/routes/admin.php');
        $this->publishes(
            [
                __DIR__ . '/config/cms.php' => config_path('cms.php'),
            ],
            'ApiGatewayDrupal'
        );
    }
}
