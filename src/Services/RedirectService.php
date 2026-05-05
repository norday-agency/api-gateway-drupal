<?php

namespace IN10\ApiGatewayDrupal\Services;

use GuzzleHttp\Psr7\Request as OutgoingRequest;
use GuzzleHttp\Psr7\Uri;
use Http\Adapter\Guzzle7\Client as g7Client;
use Illuminate\Http\JsonResponse;
use WoohooLabs\Yang\JsonApi\Client\JsonApiClient;
use App\Http\Controllers\Api\GlobalSettingsController;

class RedirectService
{
    /**
     * Check if current route exists in redirects
     */
    public static function check(string $locale, string $type, string $slug): ?JsonResponse
    {
        $cacheId = hash('sha256', implode('.', ['redirect', $locale, $type, $slug]));

        $cached = CacheContent::remember(
            $cacheId,
            function () use ($locale, $type, $slug) {
                $redirect = self::resolve($locale, $type, $slug);

                if (is_null($redirect)) {
                    return ['response' => null];
                }

                return [
                    'response' => [
                        'data' => $redirect->getData(true),
                        'statusCode' => $redirect->getStatusCode(),
                    ],
                ];
            },
            ['redirects'],
            3600
        );

        if (!is_array($cached) || !array_key_exists('response', $cached) || is_null($cached['response'])) {
            return null;
        }

        return new JsonResponse($cached['response']['data'], (int) $cached['response']['statusCode']);
    }

    protected static function resolve(string $locale, string $type, string $slug): ?JsonResponse
    {
        $redirect_path = $slug;

        if ($type !== 'pages') {
            $redirect_path = trans("routes.{$type}") . '/' . $redirect_path;
        }

        if (config('website.account') === 'rotterdampas' && class_exists('App\Http\Controllers\Api\GlobalSettingsController')) {
            $globalSettings = (new GlobalSettingsController())->getGlobalSettingsPage();

            // maintenance mypass
            if ($globalSettings[0]->maintenance_mypass && in_array(
                    substr($redirect_path,strpos($redirect_path, '/') + 1),
                    config('admin.maintenance_mypass.redirects')
                )) {
                $redirect = [
                    "language" => "nl",
                    "source" => $redirect_path,
                    "url" => config("admin.maintenance_mypass.redirect_url"),
                    "statusCode" => "307"
                ];
                return new JsonResponse($redirect, 307);
            }
            // maintenance shop
            if ($globalSettings[0]->maintenance_shop && in_array(
                    substr($redirect_path,strpos($redirect_path, '/') + 1),
                    config('admin.maintenance_shop.redirects')
                )) {
                $redirect = [
                    "language" => "nl",
                    "source" => $redirect_path,
                    "url" => config("admin.maintenance_shop.redirect_url"),
                    "statusCode" => "307"
                ];
                return new JsonResponse($redirect, 307);
            }
            // maintenance partners
            if ($globalSettings[0]->maintenance_partners && in_array(
                    substr($redirect_path,strpos($redirect_path, '/') + 1),
                    config(
                        'admin.maintenance_partners.redirects'
                    )
                )) {
                $redirect = [
                    "language" => "nl",
                    "source" => $redirect_path,
                    "url" => config("admin.maintenance_partners.redirect_url"),
                    "statusCode" => "307"
                ];
                return new JsonResponse($redirect, 307);
            }
        }

        $query = [
            'language' => $locale,
            'source' => $redirect_path,
        ];

        $query = http_build_query($query);

        $redirectRequest = new OutgoingRequest(
            'GET',
            new Uri(config('cms.url') . '/' . $locale . config('cms.endpoint') . '/redirects/getRedirect?' . $query)
        );

        // Instantiate the Guzzle HTTP Client
        if (in_array(config('app.env'), ['local', 'test'])) {
            $guzzleClient = g7Client::createWithConfig(['verify' => false]);
        } else {
            $guzzleClient = g7Client::createWithConfig([]);
        }

        // Instantiate the synchronous JSON:API Client
        $client = new JsonApiClient($guzzleClient);
        $redirectResponse = $client->sendRequest($redirectRequest);

        if ($redirectResponse->getStatusCode() < 300) {
            $contents = json_decode($redirectResponse->getBody()->getContents(), true);

            if ($locale === config('app.base_locale') && strpos($contents['url'], '/' . $locale) === 0) {
                $contents['url'] = substr($contents['url'], 3);
            }

            return new JsonResponse($contents, $contents['statusCode']);
        }

        return null;
    }
}
