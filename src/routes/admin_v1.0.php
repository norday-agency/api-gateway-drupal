<?php

// Clear cache — guarded by the shared webhook secret (no-op when unset).
Route::post('cache/invalidate', 'IN10\ApiGatewayDrupal\Controllers\CacheController@invalidate')
    ->middleware(\IN10\ApiGatewayDrupal\Middleware\VerifyWebhookSecret::class);

// Sitemap
Route::get('/sitemap', 'IN10\ApiGatewayDrupal\Controllers\DrupalController@getSitemap')->name('api-gateway-drupal::sitemap');

Route::group(
    [
        'prefix' => '{locale}',
        'where' => ['locale' => config('cms.supported_locales', '(nl|en)')],
    ],
    function () {
        // Global settins
        Route::get('settings', 'IN10\ApiGatewayDrupal\Controllers\DrupalController@getSettings')->name('settings');

        // Menu routes
        Route::get('menu/{type}', 'IN10\ApiGatewayDrupal\Controllers\DrupalController@getMenu')->where('type', '.*')->name('menu');

        // Content type routes
        foreach (config('app.supported_locales', []) as $language) {
            foreach (array_keys(config('cms.types', [])) as $type) {
                Route::prefix(trans("routes.{$type}", [], $language))->group(
                    function () use ($type) {
                        Route::get('{slug?}', 'IN10\ApiGatewayDrupal\Controllers\DrupalController@getContentType')->name($type)->where('slug', '.*');
                    }
                );
            }
        }
    }
);
