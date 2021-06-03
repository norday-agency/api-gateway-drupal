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
        Route::bind(
            'source',
            function ($name) {
                $prefix = ['website', 'app', 'gpass', 'unittest'];
                if (!in_array($name, $prefix)) {
                    //handle wrong prefixes
                    throw new Exception('API source not allowed');
                }
            }
        );

        $this->loadRoutesFrom(__DIR__ . '/routes/api.php');
        $this->publishes(
            [
                __DIR__ . '/config/cms.php' => config_path('cms.php'),
            ],
            'ApiGatewayDrupal'
        );
    }
}
