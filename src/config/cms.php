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
        // How long a 404/410 "not found" is cached (negative caching).
        'negative_ttl' => (int) env('DRUPAL_CACHE_NEGATIVE_TTL', 60),
    ],
    // Timeouts (seconds) for outgoing Drupal HTTP calls, so a slow upstream can't
    // pin a PHP-FPM worker indefinitely. rebuild_timeout is the batch index build.
    'http' => [
        'connect_timeout' => (int) env('CMS_HTTP_CONNECT_TIMEOUT', 2),
        'timeout' => (int) env('CMS_HTTP_TIMEOUT', 5),
        'rebuild_timeout' => (int) env('CMS_HTTP_REBUILD_TIMEOUT', 30),
    ],
    // Authoritative index of valid paths: when built, unknown detail paths get an
    // instant 404 without hitting Drupal. Disable to fall back to Drupal lookups.
    'route_index' => [
        'enabled' => filter_var(env('ROUTE_INDEX_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
    ],
    // Shared secret for the Drupal -> gateway webhooks (cache/invalidate +
    // route-index). When null the webhook auth middleware is a no-op.
    'webhook_secret' => env('GATEWAY_WEBHOOK_SECRET'),
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
    'langcode_filter' => [
        'enabled' => filter_var(env('CMS_LANGCODE_FILTER_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'except_types' => [],
    ],
];
