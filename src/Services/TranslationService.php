<?php

namespace IN10\ApiGatewayDrupal\Services;

use App\Http\Controllers\Api\GlobalSettingsController;
use GuzzleHttp\Psr7\Request as OutgoingRequest;
use GuzzleHttp\Psr7\Uri;
use Http\Adapter\Guzzle7\Client as g7Client;
use Illuminate\Http\JsonResponse;
use WoohooLabs\Yang\JsonApi\Client\JsonApiClient;
use WoohooLabs\Yang\JsonApi\Hydrator\ClassDocumentHydrator;

class TranslationService
{
    /**
     * Get current page translations
     */
    public static function get(string $locale, string $type, string $slug)
    {

        if ($type === 'page') {
            $type = 'pages';
        }

        $supportedLocales = array_diff(config('app.supported_locales'), [$locale]);

        $translations = [
            $locale => ($locale === config('app.base_locale') ? '' : '/' . $locale) . $slug
        ];

        $query = [];

        $query['filter']['field_path'] = [
            'value' => $slug,
        ];

        $query = http_build_query($query);

        foreach ($supportedLocales as $supportedLocale) {
            $request_url = new Uri(
                config('cms.url') . '/' . $supportedLocale . config('cms.endpoint') . '/' . $type . '?' . $query
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
                $hydrator = new ClassDocumentHydrator();
                $hydratedData = $hydrator->hydrate($response->document());
                $prefix = $supportedLocale === config('app.base_locale') ? '' : '/' . $supportedLocale;
            }
            $translations[$supportedLocale] = (isset($hydratedData[0]) && $hydratedData[0]->langcode === $supportedLocale) ? ($prefix . ($hydratedData[0]->slug ?? $hydratedData[0]->field_path)) : null;
        }

        return $translations;
    }
}
