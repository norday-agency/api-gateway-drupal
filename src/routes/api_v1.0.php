<?php

// Sitemap
Route::get('/sitemap', 'Admin\ApiController@getSitemap');

// Clear cache
Route::post('cache/invalidate', 'Admin\CacheController@invalidate');

Route::group(
    [
        'prefix' => '{locale}',
        'where' => ['locale' => '(nl|en)'],
    ],
    function () {
        // Forms
        Route::post('form/{id}', 'IN10\ApiGatewayDrupal\Controllers\ApiController@handleForm');

        // Menu routes
        Route::get('menu/{type}', 'IN10\ApiGatewayDrupal\Controllers\ApiController@getMenu')->where('type', '.*');

        // Homepage
        Route::get('/homepage', 'IN10\ApiGatewayDrupal\Controllers\ApiController@getHomepage');

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
        Route::get('{slug?}', 'Admin\ApiController@getContentType')->name('pages')->where('slug', '.*');
    }
);
