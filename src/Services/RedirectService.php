<?php

namespace IN10\ApiGatewayDrupal\Services;

use GuzzleHttp\Psr7\Request as OutgoingRequest;
use GuzzleHttp\Psr7\Uri;
use Http\Adapter\Guzzle6\Client as g6Client;
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
        $redirect_path = $slug;
        if ($type !== 'page') {
            $redirect_path = trans("routes.{$type}") . '/' . $redirect_path;
        }

        if (class_exists('App\Http\Controllers\Api\GlobalSettingsController')) {
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
            $guzzleClient = g6Client::createWithConfig(['verify' => false]);
        } else {
            $guzzleClient = g6Client::createWithConfig([]);
        }

        // Instantiate the synchronous JSON:API Client
        $client = new JsonApiClient($guzzleClient);
        $redirectResponse = $client->sendRequest($redirectRequest);

        if ($redirectResponse->getStatusCode() < 300) {
            $contents = json_decode($redirectResponse->getBody()->getContents(), true);

            return new JsonResponse($contents, $contents['statusCode']);
        }

        return null;
    }
}
