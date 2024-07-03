<?php

namespace IN10\ApiGatewayDrupal\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use GuzzleHttp\Psr7\Request as OutgoingRequest;
use GuzzleHttp\Psr7\Uri;
use Illuminate\Support\Facades\Cache;
use Http\Adapter\Guzzle7\Client as G7Client;
use WoohooLabs\Yang\JsonApi\Client\JsonApiClient;
use WoohooLabs\Yang\JsonApi\Hydrator\ClassDocumentHydrator;

class DrupalController extends Controller
{
    /**
     * Load drupal complete sitemap
     */
    public function get()
    {   
        $cacheKey = 'items';
        
        Cache::remember($cacheKey, 14400, function () {
            $sitemap = [];
            
            foreach (config('cms.types') as $type => $class) {
                foreach (config('app.supported_locales') as $locale) {
                    $items = $this->getSitemapForType($type, $locale);
                    if (!empty($items)) {
                        foreach($items as $key => $item) {
                            $url = config('app.website_url') . (($locale === 'nl') ? ($item->slug ?? $item->field_path) : '/' . $locale . ($item->slug ?? $item->field_path));

                            $priority = 0.5;
                            if (strpos($url, 'homepage') !== false) {
                                $url = str_replace('/homepage', '', $url);
                                $priority = 1.0;                        
                            }
                        
                            if (!empty($item->publish_date)) {
                                $lastModified = Carbon::createFromTimestamp(strtotime($item->publish_date));
                            } else if (!empty($item->edit_date)) {
                                $lastModified = Carbon::createFromTimestamp(strtotime($item->edit_date));
                            } else if (!empty($item->changed)) {
                                $lastModified = Carbon::createFromTimestamp(strtotime($item->changed));
                            } else {
                                $lastModified = Carbon::now();
                            }
                            
                            $sitemap[] = [$url, $this->formatDate($lastModified), $priority, 'weekly'];
                        }
                    }
                }
            }
            return $sitemap;
        });

        return response()->view('api-gateway-drupal::sitemap', [
            'posts' => Cache::get($cacheKey),
        ])->header('Content-Type', 'text/xml');
    }

    private function formatDate($carbonObject)
    {
        if ($carbonObject instanceof \Illuminate\Support\Carbon or $carbonObject instanceof Carbon) {
            return $carbonObject->tz('Europe/Amsterdam')->format('Y-m-d\Th:i:sP');
        }
    }

    /**
     * Get the sitemap for a specific type
     *
     * @param string $type
     * @param string $locale
     */
    private function getSitemapForType(string $type, string $locale)
    {
        $sitemapData = [];
        $count = $this->getCount($type, $locale);
        $offset = 0;
        $limit = 50;
        while ($offset <= $count) {
            $query = [];
            $query['include']= '';
            $query['page']['limit'] = $limit;
            $query['page']['offset'] = $offset;
            $query = http_build_query($query);
        
            $request_url = new Uri(
                config('cms.url') . '/' . $locale . '/api/' . $type . '?' . $query
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
            $response->getReasonPhrase();

            // Send the request synchronously to retrieve the response
            $response = $client->sendRequest($request);

            $resource = config("cms.types.{$type}");

            if ($resource && $response->isSuccessfulDocument([200, 202])) {
                // Hydrate the document to an stdClass
                $hydrator = new ClassDocumentHydrator();
                $hydratedData = $hydrator->hydrate($response->document());
                $sitemapData = array_merge($sitemapData, $hydratedData);
            }
            $offset += $limit;
        }
        
        if (empty($sitemapData)) {
            return null;
        }
        
        // Set content-type collection
        $data = $resource::collection($sitemapData);
        return $data;
    }

    private function getCount($type, $locale)
    {
        $query['include']= '';

        $query = http_build_query($query);

        $request_url = new Uri(
            config('cms.url') . '/' . $locale . '/api/' . $type . '?' . $query
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
        $response->getReasonPhrase();

       // Send the request synchronously to retrieve the response
       $response = $client->sendRequest($request);
       $document = $response->document();

       return $document->meta()['count'];
    }
}
