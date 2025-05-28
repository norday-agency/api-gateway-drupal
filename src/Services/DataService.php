<?php

namespace IN10\ApiGatewayDrupal\Services;

use Carbon\Carbon;
use Drupal\media\OEmbed\Resource;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\Request as OutgoingRequest;
use GuzzleHttp\Psr7\Uri;
use Http\Adapter\Guzzle7\Client as G7Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Collection;
use WoohooLabs\Yang\JsonApi\Client\JsonApiClient;
use WoohooLabs\Yang\JsonApi\Hydrator\ClassDocumentHydrator;

class DataService
{
    public static function getParagraphType(Request $api_request, string $locale, string $id) // : ?AnonymousResourceCollection
    {
        $cache_id = hash('sha256', implode('.', array_filter([$locale, 'paragraph', $id])));
        $data = CacheContent::remember($cache_id, function () use ($locale, $id) {
            $query = [
                'filter' => [
                    'langcode' => [
                        'value' => $locale,
                    ],
                ],
            ];

            $query = http_build_query($query);

            $request_url = new Uri(
                config('cms.url') . '/' . $locale . config(
                    'cms.endpoint'
                ) . '/paragraph/component_form/' . $id . '?' . $query
            );

            $request = new OutgoingRequest('GET', $request_url);
            $request = $request
            ->withProtocolVersion('1.1')
            ->withHeader('Accept', 'application/vnd.api+json')
            ->withHeader('Content-Type', 'application/vnd.api+json');

            // Instantiate the Guzzle HTTP Client
            if (in_array(config('app.env'), ['local', 'test'])) {
                $guzzleClient = G7Client::createWithConfig(['verify' => false]);
            } else {
                $guzzleClient = G7Client::createWithConfig([]);
            }

            // Instantiate the synchronous JSON:API Client
            $client = new JsonApiClient($guzzleClient);

            // Send the request synchronously to retrieve the response
            $response = $client->sendRequest($request);

            if ($response->isSuccessfulDocument([200, 202])) {
                // Hydrate the document to an stdClass
                $hydrator = new ClassDocumentHydrator();
                $hydratedData = $hydrator->hydrate($response->document());

                if (empty($hydratedData)) {
                    return null;
                }

                return $hydratedData[0];
            } elseif (class_exists('\Bugsnag\BugsnagLaravel\Facades\Bugsnag')) {
                \Bugsnag\BugsnagLaravel\Facades\Bugsnag::notifyError('Drupal Error: ' . $response->getStatusCode(), $response->getReasonPhrase());
            }

            return null;
        }, ['paragraphs']);

        return $data;
    }

    public function getSettings(Request $api_request, string $locale)
    {
        $cache_id = hash('sha256', "{$locale}.settings");
        $data = CacheContent::remember($cache_id, function () use ($api_request, $locale) {
            $settings = [];

            foreach (config('cms.navigation') as $container => $menus) {
                foreach ($menus as $menu) {
                    $settings['navigation'][$container][$menu] = $this->getMenu($api_request, $locale, "{$container}-{$menu}");
                }
            }

            return $settings;
        }, ['settings']);

        return new JsonResponse($data);
    }

    public function getMenu(Request $api_request, string $locale, string $type)
    {
        $client = new GuzzleClient();

        $request = [
            'headers' => [
                'Accept' => 'application/hal+json',
                'Content-Type' => 'application/hal+json',
            ],
        ];

        if (config('cms.navigation-per-language')) {
            $type = $type . '-' . $locale;
        }

        $request_url = new Uri(
            config('cms.url') . '/' . $locale . config(
                'cms.endpoint'
            ) . '/menu_items/' . $type . '?locale=' . $locale
        );

        $response = $client->request('GET', $request_url, $request);

        if ($response->getStatusCode() == 200) {
            $result = json_decode((string) $response->getBody());

            if (empty($result)) {
                return null;
            }

            return $this->formatMenuLink($result);
        }

        return null;
    }

    private function formatMenuLink(?array $items): Collection
    {
        $menu = collect($items)->map(function ($link) {
            if (!empty($link->relative)) {
                $url = $link->relative;
                // Todo: get default locale from env for removing default locale in urls or use same functionality as in Link resource
                if (substr($url, 0, 3) === '/' . config('app.base_locale', 'nl')) {
                    $url = str_replace('/' . config('app.base_locale', 'nl'), '', $url);
                }
                $link->relative = $url;
            }

            // menu item description is used for relative url for other languages
            if (!empty($link->description)) {
                $link->relative = $link->description;
            }

            unset($link->description);

            // $link is object > cast to array else error
            if (array_key_exists('below', (array) $link)) {
                $link->below = $this->formatMenuLink($link->below);
            }

            // if the page is not translated, remove it from the menu
            if (!empty($link->relative) && strpos($link->relative, 'node/') !== false) {
                return false;
            }

            return $link;
        });

        return collect(array_values(array_filter($menu->toArray())));
    }

    /**
     * Call Drupal REST API for menu data
     */
    public static function getMenuJson(Request $api_request, string $locale, string $type): JsonResponse
    {
        $cache_id = hash('sha256', "{$locale}.menu.{$type}");
        $data = CacheContent::remember($cache_id, function () use ($locale, $type) {
            $client = new GuzzleClient();

            $request = [
                'headers' => [
                    'Accept' => 'application/hal+json',
                    'Content-Type' => 'application/hal+json',
                ],
            ];

            $request_url = new Uri(
                config('cms.url') . '/' . $locale . config(
                    'cms.endpoint'
                ) . '/menu_items/' . $type . '?locale=' . $locale
            );

            $response = $client->request('GET', $request_url, $request);

            if ($response->getStatusCode() == 200) {
                $result = json_decode((string) $response->getBody());

                if (empty($result)) {
                    $data['statusCode'] = 404;
                    $data['statusText'] = 'Menu not found';

                    return $data;
                }

                $data['data'] = $result;
            } else {
                $data['data'] = 'Incorrect data received';
            }

            $data['statusCode'] = $response->getStatusCode();
            $data['statusText'] = $response->getReasonPhrase();

            if ($response->getStatusCode() !== 200 && class_exists('\Bugsnag\BugsnagLaravel\Facades\Bugsnag')) {
                \Bugsnag\BugsnagLaravel\Facades\Bugsnag::notifyError('Drupal Error: ' . $response->getStatusCode(), $response->getReasonPhrase());
            }

            return $data;
        }, ['menus']);

        return new JsonResponse($data, $data['statusCode']);
    }

    /**
     * Call Drupal JSON API for contenttype data Preview call
     */
    public static function getContentTypePreviewJson(Request $api_request, string $locale, string $type): JsonResponse
    {
        $id = $api_request->get('nid');

        $query = ['resourceVersion' => 'id:' . $api_request->get('rid')];
        if ($api_request->get('rid') === 'working-copy') {
            $query = ['resourceVersion' => 'rel:working-copy'];
        }

        $query = http_build_query($query);

        $request_url = new Uri(
            config('cms.url') . '/' . $locale . config('cms.endpoint') . '/' . $type . '/' . $id . '?' . $query
        );

        $request = new OutgoingRequest('GET', $request_url);
        $request = $request
                ->withProtocolVersion('1.1')
                ->withHeader('Accept', 'application/vnd.api+json')
                ->withHeader('Content-Type', 'application/vnd.api+json');

        // Instantiate the Guzzle HTTP Client
        if (in_array(config('app.env'), ['local', 'test'])) {
            $guzzleClient = G7Client::createWithConfig(['verify' => false]);
        } else {
            $guzzleClient = G7Client::createWithConfig([]);
        }

        // Instantiate the synchronous JSON:API Client
        $client = new JsonApiClient($guzzleClient);

        // Send the request synchronously to retrieve the response
        $response = $client->sendRequest($request);

        $resource = config("cms.types.{$type}");

        if ($resource && $response->isSuccessfulDocument([200, 202])) {
            // Hydrate the document to an stdClass
            $hydrator = new ClassDocumentHydrator();
            $hydratedData = $hydrator->hydrate($response->document());

            if (empty($hydratedData)) {
                $data['statusCode'] = 404;
                $data['statusText'] = 'Page not found';

                return new JsonResponse($data, $data['statusCode']);
            }

            $data['data'] = new $resource($hydratedData[0], true);
        } else {
            $data['data'] = 'Incorrect data received';
        }

        $data['statusCode'] = $response->getStatusCode();
        $data['statusText'] = $response->getReasonPhrase();

        return new JsonResponse($data, $data['statusCode']);
    }

    /**
     * Call Drupal JSON API for contenttype data and return JsonResponse
     */
    public static function getContentTypeJson(Request $api_request, string $locale, string $type, ?string $slug = null): JsonResponse
    {
        // Check for redirect in Drupal
        if ($slug && $redirect = RedirectService::check($locale, $type, $slug)) {
            return $redirect;
        }

        $cache_id = hash('sha256', implode('.', array_filter([config('app.url'), $locale, $type, $slug, http_build_query($api_request->query())])));
        $data = CacheContent::remember($cache_id, function () use ($api_request, $locale, $type, $slug) {
            $peek_drupal = ($api_request->has('peek') && $api_request->get('peek') == 'drupal');

            $page = ($api_request->has('page') && is_numeric($api_request->get('page'))) ? (int) $api_request->get('page') : 1;
            $page_limit = (int) config('cms.pagination.limit');

            $query = [
                'page' => [
                    'offset' => ($page - 1) * $page_limit,
                    'limit' => $page_limit,
                ],
            ];

            $query['filter'] = [
                'langcode' => [
                    'value' => $locale,
                ],
                'datefilter' => [
                    'condition' => [
                        'path' => 'publish_date',
                        'operator' => '<=',
                        'value' => Carbon::now()->toISOString(),
                    ],
                ],
            ];

            if ($default_query = config('cms.default_query')) {
                $query['filter'] = array_merge($query['filter'], $default_query['filter']);
            }

            if ($slug) { // show content-type based on slug
                $path = '/' . $slug;

                if (!in_array($type, ['pages', 'site_pages', 'homepage']) && strpos(trans("routes.{$type}"), 'routes.') === false) {
                    $path = '/' . trans("routes.{$type}") . $path;
                }

                $query['filter']['field_path'] = [
                    'value' => $path,
                ];
            } else { // show content-type index
                if ($api_request->has('promote') && in_array($api_request->get('promote'), ['true', 'false'], true)) {
                    $query['filter']['promote'] = [
                        'value' => ($api_request->get('promote') === 'true'),
                    ];
                }

                if ($api_request->has('promote_overview') && in_array($api_request->get('promote_overview'), ['true', 'false'], true)) {
                    $query['filter']['promote_overview'] = [
                        'value' => ($api_request->get('promote_overview') === 'true'),
                    ];
                }

                if ($api_request->has('sticky') && in_array($api_request->get('sticky'), ['true', 'false'], true)) {
                    $query['filter']['sticky'] = [
                        'value' => ($api_request->get('sticky') === 'true'),
                    ];
                }

                $query['sort'] = '-sticky,-publish_date,title';
            }

            $query = http_build_query($query);

            $request_url = new Uri(
                config('cms.url') . '/' . $locale . config('cms.endpoint') . '/' . $type . '?' . $query
            );

            $request = new OutgoingRequest('GET', $request_url);
            $request = $request
                    ->withProtocolVersion('1.1')
                    ->withHeader('Accept', 'application/vnd.api+json')
                    ->withHeader('Content-Type', 'application/vnd.api+json');

            // Instantiate the Guzzle HTTP Client
            if (in_array(config('app.env'), ['local', 'test'])) {
                $guzzleClient = G7Client::createWithConfig(['verify' => false]);
            } else {
                $guzzleClient = G7Client::createWithConfig([]);
            }

            // Instantiate the synchronous JSON:API Client
            $client = new JsonApiClient($guzzleClient);

            // Send the request synchronously to retrieve the response
            $response = $client->sendRequest($request);

            $resource = config("cms.types.{$type}");

            if ($resource && $response->isSuccessfulDocument([200, 202])) {
                // Hydrate the document to an stdClass
                $hydrator = new ClassDocumentHydrator();
                $hydratedData = $hydrator->hydrate($response->document());

                if (!empty($hydratedData) && $slug) {
                    // Set content-type detail based on type and slug
                    $data['data'] = ($peek_drupal) ? $hydratedData[0] : new $resource($hydratedData[0], true);
                } else {
                    // Set content-type collection
                    $data['data'] = ($peek_drupal) ? $hydratedData : $resource::collection($hydratedData);

                    // Set paging properties
                    $data['pagination'] = [
                        'page' => $page,
                        'limit' => $page_limit,
                        'total' => $response->document()->hasMeta() ? (int) $response->document()->meta(
                        )['count'] : null,
                    ];
                }
            } else {
                $data['data'] = 'Incorrect data received';
            }

            $data['statusCode'] = $response->getStatusCode();
            $data['statusText'] = $response->getReasonPhrase();

            if (!$response->isSuccessfulDocument([200, 202]) && class_exists('\Bugsnag\BugsnagLaravel\Facades\Bugsnag')) {
                \Bugsnag\BugsnagLaravel\Facades\Bugsnag::notifyError('Drupal Error: ' . $data['statusCode'], $data['statusText']);
            }

            return $data;
        }, [$type]);

        return new JsonResponse($data, $data['statusCode']);
    }

    /**
     * Call Drupal JSON API for contenttype data and return resource collection
     */
    public static function getContentType(Request $api_request, string $locale, string $type, ?string $slug = null)
    {
        $cache_id = hash('sha256', implode('.', array_filter([$locale, $type, $slug, http_build_query($api_request->query())])));
        $data = CacheContent::remember($cache_id, function () use ($api_request, $locale, $type, $slug) {
            $peek_drupal = ($api_request->has('peek') && $api_request->get('peek') == 'drupal');

            $query = [];

            if ($slug) { // show content-type based on slug
                $path = '/' . $slug;

                if ($type !== 'pages') {
                    $path = '/' . trans("routes.{$type}") . $path;
                }

                $query['filter']['field_path'] = [
                    'value' => $path,
                ];
            } else { // show content-type index
                if ($api_request->has('promote') && in_array($api_request->get('promote'), ['true', 'false'], true)) {
                    $query['filter']['promote'] = [
                        'value' => ($api_request->get('promote') === 'true'),
                    ];
                }

                if ($api_request->has('promote_overview') && in_array($api_request->get('promote_overview'), ['true', 'false'], true)) {
                    $query['filter']['promote_overview'] = [
                        'value' => ($api_request->get('promote_overview') === 'true'),
                    ];
                }

                if ($api_request->has('sticky') && in_array($api_request->get('sticky'), ['true', 'false'], true)) {
                    $query['filter']['sticky'] = [
                        'value' => ($api_request->get('sticky') === 'true'),
                    ];
                }

                $query['sort'] = '-sticky,-publish_date,title';
            }

            $query = http_build_query($query);

            $request_url = new Uri(
                config('cms.url') . '/' . $locale . config('cms.endpoint') . '/' . $type . '?' . $query
            );

            $request = new OutgoingRequest('GET', $request_url);
            $request = $request
                    ->withProtocolVersion('1.1')
                    ->withHeader('Accept', 'application/vnd.api+json')
                    ->withHeader('Content-Type', 'application/vnd.api+json');

            // Instantiate the Guzzle HTTP Client
            if (in_array(config('app.env'), ['local', 'test'])) {
                $guzzleClient = G7Client::createWithConfig(['verify' => false]);
            } else {
                $guzzleClient = G7Client::createWithConfig([]);
            }

            // Instantiate the synchronous JSON:API Client
            $client = new JsonApiClient($guzzleClient);

            // Send the request synchronously to retrieve the response
            $response = $client->sendRequest($request);

            $resource = config("cms.types.{$type}");

            if ($resource && $response->isSuccessfulDocument([200, 202])) {
                // Hydrate the document to an stdClass
                $hydrator = new ClassDocumentHydrator();
                $hydratedData = $hydrator->hydrate($response->document());

                if (empty($hydratedData)) {
                    return null;
                }

                if ($slug) {
                    // Set content-type detail based on type and slug
                    $data = ($peek_drupal) ? $hydratedData[0] : new $resource($hydratedData[0], true);
                } else {
                    // Set content-type collection
                    $data = ($peek_drupal) ? $hydratedData : $resource::collection($hydratedData);
                }

                return $data;
            }

            if (!$response->isSuccessfulDocument([200, 202]) && class_exists('\Bugsnag\BugsnagLaravel\Facades\Bugsnag')) {
                \Bugsnag\BugsnagLaravel\Facades\Bugsnag::notifyError('Drupal Error: ' . $response->getStatusCode(), $response->getReasonPhrase());
            }

            return null;
        }, [$type]);

        return $data;
    }

    /**
     * Call Drupal JSON API for contenttype data based on cutom query and return resource collection
     */
    public static function getContentTypeByQuery(array $query, string $locale, string $type): ?AnonymousResourceCollection
    {
        $cache_id = hash('sha256', implode('.', array_filter([$locale, $type, http_build_query($query)])));
        $data = CacheContent::remember($cache_id, function () use ($query, $locale, $type) {
            if (!array_key_exists('sort', $query)) {
                $query['sort'] = '-sticky,-publish_date,title';
            }

            $query = http_build_query($query);

            $request_url = new Uri(
                config('cms.url') . '/' . $locale . config('cms.endpoint') . '/' . $type . '?' . $query
            );

            $request = new OutgoingRequest('GET', $request_url);
            $request = $request
                    ->withProtocolVersion('1.1')
                    ->withHeader('Accept', 'application/vnd.api+json')
                    ->withHeader('Content-Type', 'application/vnd.api+json');

            // Instantiate the Guzzle HTTP Client
            if (in_array(config('app.env'), ['local', 'test'])) {
                $guzzleClient = G7Client::createWithConfig(['verify' => false]);
            } else {
                $guzzleClient = G7Client::createWithConfig([]);
            }

            // Instantiate the synchronous JSON:API Client
            $client = new JsonApiClient($guzzleClient);

            // Send the request synchronously to retrieve the response
            $response = $client->sendRequest($request);

            $resource = config("cms.types.{$type}");

            if ($resource && $response->isSuccessfulDocument([200, 202])) {
                // Hydrate the document to an stdClass
                $hydrator = new ClassDocumentHydrator();
                $hydratedData = $hydrator->hydrate($response->document());

                if (empty($hydratedData)) {
                    return null;
                }

                return $resource::collection($hydratedData);
            }

            if (!$response->isSuccessfulDocument([200, 202]) && class_exists('\Bugsnag\BugsnagLaravel\Facades\Bugsnag')) {
                \Bugsnag\BugsnagLaravel\Facades\Bugsnag::notifyError('Drupal Error: ' . $response->getStatusCode(), $response->getReasonPhrase());
            }

            return null;
        }, [$type]);

        return $data;
    }

    /**
     * Call Drupal JSON API for translated node data
     *
     * @return array|null
     */
    public static function getTranslation(string $locale, string $type, int $nid): ?string
    {
        // $cache_id = hash('sha256', implode('.', array_filter([$locale, $type, $slug, http_build_query($api_request->query())])));
        // $data = CacheContent::remember($cache_id, function () use ($api_request, $locale, $type, $slug) {
        // $peek_drupal = ($api_request->has('peek') && $api_request->get('peek') == 'drupal');

        $query = [
            'filter' => [
                'langcode' => [
                    'value' => $locale,
                ],
                'datefilter' => [
                    'condition' => [
                        'path' => 'publish_date',
                        'operator' => '<=',
                        'value' => Carbon::now()->toISOString(),
                    ],
                ],
            ],
        ];

        $query['filter']['drupal_internal__nid'] = [
            'value' => $nid,
        ];

        $query = http_build_query($query);

        $request_url = new Uri(config('cms.url') . '/' . $locale . config('cms.endpoint') . '/' . $type . '?' . $query);

        $request = new OutgoingRequest('GET', $request_url);
        $request = $request
            ->withProtocolVersion('1.1')
            ->withHeader('Accept', 'application/vnd.api+json')
            ->withHeader('Content-Type', 'application/vnd.api+json');

        // Instantiate the Guzzle HTTP Client
        if (in_array(config('app.env'), ['local', 'test'])) {
            $guzzleClient = G7Client::createWithConfig(['verify' => false]);
        } else {
            $guzzleClient = G7Client::createWithConfig([]);
        }

        // Instantiate the synchronous JSON:API Client
        $client = new JsonApiClient($guzzleClient);

        // Send the request synchronously to retrieve the response
        $response = $client->sendRequest($request);

        $resource = config("cms.types.{$type}");

        if ($resource && $response->isSuccessfulDocument([200, 202])) {
            // Hydrate the document to an stdClass
            $hydrator = new ClassDocumentHydrator();
            $hydratedData = $hydrator->hydrate($response->document());

            if (!empty($hydratedData)) {
                return '/' . $hydratedData[0]->langcode . $hydratedData[0]->slug; // new $resource($hydratedData[0]);
            }
        }

        //     return $translation;
        // }, [$type]);

        if (!$response->isSuccessfulDocument([200, 202]) && class_exists('\Bugsnag\BugsnagLaravel\Facades\Bugsnag')) {
            \Bugsnag\BugsnagLaravel\Facades\Bugsnag::notifyError('Drupal Error: ' . $response->getStatusCode(), $response->getReasonPhrase());
        }

        return null;
    }
}
