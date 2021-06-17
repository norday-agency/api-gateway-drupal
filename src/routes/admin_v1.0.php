<?php

// Sitemap
Route::get('/sitemap', 'IN10\ApiGatewayDrupal\Controllers\DrupalController@getSitemap');

// Clear cache
Route::post('cache/invalidate', 'IN10\ApiGatewayDrupal\Controllers\CacheController@invalidate');

Route::group(
    [
        'prefix' => '{locale}',
        'where' => ['locale' => '(nl|en)'],
    ],
    function () {
        // Forms
        Route::post('form/{id}', 'IN10\ApiGatewayDrupal\Controllers\DrupalController@handleForm');

        // Menu routes
        Route::get('menu/{type}', 'IN10\ApiGatewayDrupal\Controllers\DrupalController@getMenu')->where('type', '.*');

        // Homepage
        Route::get('/homepage', 'IN10\ApiGatewayDrupal\Controllers\DrupalController@getHomepage');

        // Content type routes
        foreach (config('app.supported_locales') as $language) {
            foreach (array_keys(config('admin.types')) as $type) {
                Route::prefix(trans("routes.{$type}", [], $language))->group(
                    function () use ($type) {
                        Route::get('{slug?}', 'IN10\ApiGatewayDrupal\Controllers\DrupalController@getContentType')->name($type)->where('slug', '.*');
                    }
                );
            }
        }

        // Default page routes
        Route::get('{slug?}', 'IN10\ApiGatewayDrupal\Controllers\DrupalController@getContentType')->name('pages')->where('slug', '.*');
    }
);
