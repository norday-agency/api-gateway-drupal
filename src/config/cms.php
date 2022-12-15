<?php

use IN10\ApiGatewayDrupal\Resources\News;
use IN10\ApiGatewayDrupal\Resources\Page;

return [
    'url' => env('CMS_URL'),
    'endpoint' => env('CMS_ENDPOINT'),
    'types' => [
        'news' => News::class,
        'pages' => Page::class,
    ],
    'navigation' => [
        'header' => ['primary'],
        'footer' => ['primary'],
    ],
    'pagination' => [
        'limit' => env('CMS_PAGINATION_LIMIT', 12),
    ],
    // 'oauth' => [
    //     'clientId' => env('DRUPAL_OAUTH_CLIENT_ID'),
    //     'clientSecret' => env('DRUPAL_OAUTH_CLIENT_SECRET'),
    //     'apiScope' => env('DRUPAL_OAUTH_SCOPE')
    // ],
    // 'securedPaths' => explode(',', env('DRUPAL_SECURED_PATHS'))
];
