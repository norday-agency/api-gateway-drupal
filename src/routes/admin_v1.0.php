<?php

use IN10\ApiGatewayDrupal\Middleware\VerifyWebhookSecret;

// Drupal -> gateway webhooks (cache invalidation + route-index upkeep).
// Guarded by a shared secret (no-op when none is configured).
Route::middleware(VerifyWebhookSecret::class)->group(function () {
    // Clear cache
    Route::post('cache/invalidate', 'IN10\ApiGatewayDrupal\Controllers\CacheController@invalidate');

    // Route index upkeep: add on publish/path-change, remove on unpublish/delete
    Route::post('route-index/add', 'IN10\ApiGatewayDrupal\Controllers\RouteIndexController@add');
    Route::post('route-index/remove', 'IN10\ApiGatewayDrupal\Controllers\RouteIndexController@remove');
});

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
