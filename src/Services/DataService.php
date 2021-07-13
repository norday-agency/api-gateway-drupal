<?php

namespace IN10\ApiGatewayDrupal\Services;

use Carbon\Carbon;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\Request as OutgoingRequest;
use GuzzleHttp\Psr7\Uri;
use Http\Adapter\Guzzle6\Client as G6Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use WoohooLabs\Yang\JsonApi\Client\JsonApiClient;
use WoohooLabs\Yang\JsonApi\Hydrator\ClassDocumentHydrator;

class DataService
{
    public static function getParagraphType(
        Request $api_request,
        string $locale,
        string $id
    )//: ?AnonymousResourceCollection
    {
        // $cache_id = hash('sha256', implode('.', array_filter([$locale, 'paragraph', $id])));
        // $data = CacheContent::remember($cache_id, function () use ($api_request, $locale, $type, $slug) {

        $query = [
            'filter' => [
                'langcode' => [
                    'value' => $locale,
                    // Todo: je kunt nog steed een nl pagina opvragen op bijv. engelse slug --> CHECK!
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
            $guzzleClient = G6Client::createWithConfig(['verify' => false]);
        } else {
            $guzzleClient = G6Client::createWithConfig([]);
        }

        // Instantiate the synchronous JSON:API Client
        $client = new JsonApiClient($guzzleClient);

        // Send the request synchronously to retrieve the response
        $response = $client->sendRequest($request);
        $response->getReasonPhrase();

        if ($response->isSuccessfulDocument([200, 202])) {
            // Hydrate the document to an stdClass
            $hydrator = new ClassDocumentHydrator();
            $hydratedData = $hydrator->hydrate($response->document());

            if (empty($hydratedData)) {
                return null;
            }

            $data = $hydratedData[0];
        } else {
            return null;
        }

        //     return $data;
        // }, [$type]);

        return $data;
    }

    /**
     * Call Drupal REST API for menu data
     */
    public static function getMenu(Request $api_request, string $locale, string $type): JsonResponse
    {
        $cache_id = hash('sha256', "{$locale}.menu.{$type}");
        $data = CacheContent::remember(
            $cache_id,
            function () use ($locale, $type) {
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
                    $result = json_decode((string)$response->getBody());

                    if (empty($result)) {
                        $data['statusCode'] = 404;
                        $data['statusText'] = 'Menu not found';

                        return new JsonResponse($data, $data['statusCode']);
                    }

                    $data['data'] = $result;
                } else {
                    $data['data'] = 'Incorrect data received';
                }

                $data['statusCode'] = $response->getStatusCode();
                $data['statusText'] = $response->getReasonPhrase();

                return $data;
            },
            ['menus']
        );

        return new JsonResponse($data, $data['statusCode']);
    }

    /**
     * Call Drupal JSON API for contenttype data
     */
    public static function getContentTypeJson(
        Request $api_request,
        string $locale,
        string $type,
        string $slug = null
    ): JsonResponse {
        // Check for redirect in Drupal
        if ($slug && $redirect = RedirectService::check($locale, $type, $slug)) {
            return $redirect;
        }

        // $cache_id = hash('sha256', implode('.', array_filter([$locale, $type, $slug, http_build_query($api_request->query())])));
        // $data = CacheContent::remember($cache_id, function () use ($api_request, $locale, $type, $slug) {
        $peek_drupal = ($api_request->has('peek') && $api_request->get('peek') == 'drupal');

        $page = ($api_request->has('page') && is_numeric($api_request->get('page'))) ? (int)$api_request->get(
            'page'
        ) : 1;
        $page_limit = (int)config('cms.pagination.limit');

        $query = [
            'page' => [
                'offset' => ($page - 1) * $page_limit,
                'limit' => $page_limit,
            ]
        ];
        if (in_array($type, config('cms.hasDate'))) {
            $query['filter'] = [
                'langcode' => [
                    'value' => $locale,
                    // Todo: je kunt nog steed een nl pagina opvragen op bijv. engelse slug --> CHECK!
                ],
                'datefilter' => [
                    'condition' => [
                        'path' => 'publish_date',
                        'operator' => '<=',
                        'value' => Carbon::now()->toISOString(),
                    ],
                ],
            ];
        }

        if ($slug) { // show content-type based on slug
            $path = '/' . $slug;
            $query['filter']['field_path'] = [
                'value' => $path,
            ];
        } else { // show content-type index
            if ($api_request->has('contributor') && is_numeric($api_request->get('contributor'))) {
                $query['filter']['contributor.drupal_internal__nid'] = [
                    'value' => $api_request->get('contributor'),
                ];
            }

            if ($api_request->has('promote') && in_array($api_request->get('promote'), ['true', 'false'], true)) {
                $query['filter']['promote'] = [
                    'value' => ($api_request->get('promote') === 'true'),
                ];
            }

            if ($api_request->has('promote_overview') && in_array(
                    $api_request->get('promote_overview'),
                    ['true', 'false'],
                    true
                )) {
                $query['filter']['promote_overview'] = [
                    'value' => ($api_request->get('promote_overview') === 'true'),
                ];
            }

            if ($api_request->has('sticky') && in_array($api_request->get('sticky'), ['true', 'false'], true)) {
                $query['filter']['sticky'] = [
                    'value' => ($api_request->get('sticky') === 'true'),
                ];
            }

            if (in_array($type, config('cms.hasDate'))) {
                $query['sort'] = '-sticky,-publish_date,title';
            }
        }

        $query = http_build_query($query);

        $request_url = new Uri(config('cms.url') . '/' . $locale . config('cms.endpoint') . '/' . $type . '?' . $query);

        $request = new OutgoingRequest('GET', $request_url);
        $request = $request
            ->withProtocolVersion('1.1')
            ->withHeader('Accept', 'application/vnd.api+json')
            ->withHeader('Content-Type', 'application/vnd.api+json');

        // Instantiate the Guzzle HTTP Client
        if (in_array(config('app.env'), ['local', 'test'])) {
            $guzzleClient = G6Client::createWithConfig(['verify' => false]);
        } else {
            $guzzleClient = G6Client::createWithConfig([]);
        }

        // Instantiate the synchronous JSON:API Client
        $client = new JsonApiClient($guzzleClient);

        // Send the request synchronously to retrieve the response
        $response = $client->sendRequest($request);
        $response->getReasonPhrase();

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

            if ($slug || $type === 'homepage') {
                // Set content-type detail based on type and slug
                $data['data'] = ($peek_drupal) ? $hydratedData[0] : new $resource($hydratedData[0], true);
            } else {
                // Set content-type collection page based on type
                // $page = self::getContentType($api_request, $locale, 'pages', trans("routes.{$type}"));
                // $data['page'] = ($page->getStatusCode() == 200) ? $page->getData()->data : null;

                // Set content-type collection
                $data['data'] = ($peek_drupal) ? $hydratedData : $resource::collection($hydratedData);

                // Set paging properties
                $data['pagination'] = [
                    'page' => $page,
                    'limit' => $page_limit,
                    'total' => $response->document()->hasMeta() ? (int)$response->document()->meta()['count'] : null,
                ];
            }
        } else {
            $data['data'] = 'Incorrect data received';
        }

        $data['statusCode'] = $response->getStatusCode();
        $data['statusText'] = $response->getReasonPhrase();

        //     return $data;
        // }, [$type]);

        return new JsonResponse($data, $data['statusCode']);
    }

    public static function getContentType(
        Request $api_request,
        string $locale,
        string $type,
        string $slug = null
    )//: ?AnonymousResourceCollection
    {
        // $cache_id = hash('sha256', implode('.', array_filter([$locale, $type, $slug, http_build_query($api_request->query())])));
        // $data = CacheContent::remember($cache_id, function () use ($api_request, $locale, $type, $slug) {
        $peek_drupal = ($api_request->has('peek') && $api_request->get('peek') == 'drupal');

        // $page = ($api_request->has('page') && is_numeric($api_request->get('page'))) ? (int) $api_request->get('page') : 1;
        // $page_limit = (int) config('cms.pagination.limit');

        $query = [
            // 'page' => [
            //     'offset' => ($page - 1) * $page_limit,
            //     'limit' => $page_limit,
            // ],
            'filter' => [
                'langcode' => [
                    'value' => $locale,
                    // Todo: je kunt nog steed een nl pagina opvragen op bijv. engelse slug --> CHECK!
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

        if ($slug) { // show content-type based on slug
            $path = '/' . $slug;
            if ($type !== 'pages') {
                $path = '/' . trans("routes.{$type}") . $path;
            }

            $query['filter']['field_path'] = [
                'value' => $path,
            ];
        } else { // show content-type index
            if ($api_request->has('contributor') && is_numeric($api_request->get('contributor'))) {
                $query['filter']['contributor.drupal_internal__nid'] = [
                    'value' => $api_request->get('contributor'),
                ];
            }

            if ($api_request->has('promote') && in_array($api_request->get('promote'), ['true', 'false'], true)) {
                $query['filter']['promote'] = [
                    'value' => ($api_request->get('promote') === 'true'),
                ];
            }

            if ($api_request->has('promote_overview') && in_array(
                    $api_request->get('promote_overview'),
                    ['true', 'false'],
                    true
                )) {
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

        $request_url = new Uri(config('cms.url') . '/' . $locale . config('cms.endpoint') . '/' . $type . '?' . $query);

        $request = new OutgoingRequest('GET', $request_url);
        $request = $request
            ->withProtocolVersion('1.1')
            ->withHeader('Accept', 'application/vnd.api+json')
            ->withHeader('Content-Type', 'application/vnd.api+json');

        // Instantiate the Guzzle HTTP Client
        if (in_array(config('app.env'), ['local', 'test'])) {
            $guzzleClient = G6Client::createWithConfig(['verify' => false]);
        } else {
            $guzzleClient = G6Client::createWithConfig([]);
        }

        // Instantiate the synchronous JSON:API Client
        $client = new JsonApiClient($guzzleClient);

        // Send the request synchronously to retrieve the response
        $response = $client->sendRequest($request);
        $response->getReasonPhrase();

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
                // Set content-type collection page based on type
                // $page = self::getContentType($api_request, $locale, 'pages', trans("routes.{$type}"));
                // $data['page'] = ($page->getStatusCode() == 200) ? $page->getData()->data : null;

                // Set content-type collection
                $data = ($peek_drupal) ? $hydratedData : $resource::collection($hydratedData);

                // Set paging properties
                // $data['pagination'] = [
                //     'page' => $page,
                //     'limit' => $page_limit,
                //     'total' => $response->document()->hasMeta() ? (int) $response->document()->meta()['count'] : null,
                // ];
            }
        } else {
            return null;
        }

        //     return $data;
        // }, [$type]);

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
                    // Todo: je kunt nog steed een nl pagina opvragen op bijv. engelse slug --> CHECK!
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
            $guzzleClient = G6Client::createWithConfig(['verify' => false]);
        } else {
            $guzzleClient = G6Client::createWithConfig([]);
        }

        // Instantiate the synchronous JSON:API Client
        $client = new JsonApiClient($guzzleClient);

        // Send the request synchronously to retrieve the response
        $response = $client->sendRequest($request);
        $response->getReasonPhrase();

        $resource = config("cms.types.{$type}");

        if ($resource && $response->isSuccessfulDocument([200, 202])) {
            // Hydrate the document to an stdClass
            $hydrator = new ClassDocumentHydrator();
            $hydratedData = $hydrator->hydrate($response->document());
            if (!empty($hydratedData)) {
                return '/' . $hydratedData[0]->langcode . $hydratedData[0]->slug; //new $resource($hydratedData[0]);
            }
        }

        //     return $translation;
        // }, [$type]);

        return null;
    }
}
