<?php

return [
    'url' => env('DRUPAL_URL'),
    'endpoint' => env('DRUPAL_ENDPOINT'),
    'types' => [
        //'pages' => Page::class,
    ],
    'pagination' => [
        'limit' => env('DRUPAL_PAGINATION_LIMIT', 9),
    ],
    'oauth' => [
        'clientId' => env('DRUPAL_OAUTH_CLIENT_ID'),
        'clientSecret' => env('DRUPAL_OAUTH_CLIENT_SECRET'),
        'apiScope' => env('DRUPAL_OAUTH_SCOPE')
    ],
    'securedPaths' => explode(',', env('DRUPAL_SECURED_PATHS'))
];
