<?php

// Clear cache
Route::post('cache/invalidate', 'IN10\ApiGatewayDrupal\Controllers\CacheController@invalidate');

// Sitemap
Route::get('/sitemap', 'IN10\ApiGatewayDrupal\Controllers\DrupalController@getSitemap')->name('api-gateway-drupal::sitemap');

Route::group(
    [
        'prefix' => '{locale}',
        'where' => ['locale' => '(nl|en)'],
    ],
    function () {
        // Menu routes
        Route::get('menu/{type}', 'IN10\ApiGatewayDrupal\Controllers\DrupalController@getMenu')->where('type', '.*');

        // Content type routes
        foreach (config('app.supported_locales') as $language) {
            foreach (array_keys(config('cms.types')) as $type) {
                Route::prefix(trans("routes.{$type}", [], $language))->group(
                    function () use ($type) {
                        Route::get('{slug?}', 'IN10\ApiGatewayDrupal\Controllers\DrupalController@getContentType')->name($type)->where('slug', '.*');
                    }
                );
            }
        }
    }
);
