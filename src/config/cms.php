<?php

use IN10\ApiGatewayDrupal\Resources\News;
use IN10\ApiGatewayDrupal\Resources\Page;
use IN10\ApiGatewayDrupal\Resources\Story;

return [
    'url' => env('CMS_URL', env('DRUPAL_URL')),
    'endpoint' => env('CMS_ENDPOINT', env('DRUPAL_ENDPOINT')),
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
        'limit' => (int) env('CMS_PAGINATION_LIMIT', env('DRUPAL_PAGINATION_LIMIT', 12)),
    ],
    'cache' => [
        'ttl' => (int) env('DRUPAL_CACHE_TTL', 86400),
    ],
    'oauth' => [
        'clientId' => env('DRUPAL_OAUTH_CLIENT_ID'),
        'clientSecret' => env('DRUPAL_OAUTH_CLIENT_SECRET'),
        'apiScope' => env('DRUPAL_OAUTH_SCOPE')
    ],
    'securedPaths' => array_filter(explode(',', env('DRUPAL_SECURED_PATHS', ''))),
    'sitemap' => [
        'folder' => env('SITEMAP_FOLDER'),
    ],
    'publish_date_filter' => [
        'enabled' => filter_var(env('CMS_PUBLISH_DATE_FILTER_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'field' => env('CMS_PUBLISH_DATE_FIELD', 'publish_date'),
        'except_types' => [],
    ],
    'sort' => [
        'default' => 'title',
    ],
];
