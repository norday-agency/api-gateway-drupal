<?php

use IN10\ApiGatewayDrupal\Resources\News;
use IN10\ApiGatewayDrupal\Resources\Page;
use IN10\ApiGatewayDrupal\Resources\Story;

return [
    'url' => env('CMS_URL'),
    'endpoint' => env('CMS_ENDPOINT'),
    'types' => [
        'news' => News::class,
        'pages' => Page::class,
        'stories' => Story::class,
    ],
    'navigation-per-language' => false, // true if you want to have a different navigation per language in the CMS
    'navigation' => [
        'header' => ['primary'],
        'footer' => ['primary'],
    ],
    'pagination' => [
        'limit' => env('CMS_PAGINATION_LIMIT', 12),
    ],
    'fallback-on-pages' => [], // Add types that should fallback on pages
    // 'oauth' => [
    //     'clientId' => env('DRUPAL_OAUTH_CLIENT_ID'),
    //     'clientSecret' => env('DRUPAL_OAUTH_CLIENT_SECRET'),
    //     'apiScope' => env('DRUPAL_OAUTH_SCOPE')
    // ],
    // 'securedPaths' => explode(',', env('DRUPAL_SECURED_PATHS'))
];
