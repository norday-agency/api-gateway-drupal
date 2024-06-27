<?php

namespace IN10\ApiGatewayDrupal\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use IN10\ApiGatewayDrupal\Services\DataService;
use GuzzleHttp\Psr7\Request as OutgoingRequest;
use GuzzleHttp\Psr7\Uri;
use Http\Adapter\Guzzle7\Client as G7Client;
use WoohooLabs\Yang\JsonApi\Client\JsonApiClient;
use WoohooLabs\Yang\JsonApi\Hydrator\ClassDocumentHydrator;

class DrupalController extends Controller
{
    /**
     * Handle form post
     *
     * @return void
     */
    public function handleForm(Request $request, string $locale, string $id) // Todo: check if still used
    {
        if ($settings = DataService::getParagraphType($request, $locale, $id)) {
            $class = config('forms.types.' . $settings->form_type);
            $form = new $class($request, $settings);

            return $form->send();
        } else {
            abort(404);
        }
    }

    /**
     * Load data for settings
     */
    public function getSettings(Request $request, string $locale): JsonResponse
    {
        $dataservice = new DataService();

        return $dataservice->getSettings($request, $locale);
    }

    /**
     * Load data for menu
     */
    public function getMenu(Request $request, string $locale, string $type): JsonResponse
    {
        return DataService::getMenuJson($request, $locale, $type);
    }

    /**
     * Load specific Drupal content
     */
    public function getContentType(Request $request, string $locale, ?string $slug = null): JsonResponse
    {
        $type = $request->route()->getName();

        if ($request->has('nid') && $request->has('rid')) {
            // Preview node based on specific revision id
            return DataService::getContentTypePreviewJson($request, $locale, $type);
        }

        return DataService::getContentTypeJson($request, $locale, $type, $slug);
    }

    public function getComponent(Request $request, string $locale, string $type, string $id): JsonResponse
    {
        return DataService::getComponent($request, $locale, $type, $id);
    }

    /**
     * Load drupal complete sitemap
     */
    public function getSitemap()
    {
        $sitemap = [];

        foreach (config('cms.types') as $type => $class) {
            foreach (config('app.supported_locales') as $locale) {
                $items = $this->getSitemapForType($type, $locale);
                if (!empty($items)) {
                    foreach($items as $key => $item) {
                        $url = config('app.website_url') . (($locale === 'nl') ? ($item->slug ?? $item->field_path) : '/' . $locale . ($item->slug ?? $item->field_path));
                    
                        if (!empty($item->publish_date)) {
                            $lastModified = Carbon::createFromTimestamp(strtotime($item->publish_date));
                        } else if (!empty($item->edit_date)) {
                            $lastModified = Carbon::createFromTimestamp(strtotime($item->edit_date));
                        } else if (!empty($item->changed)) {
                            $lastModified = Carbon::createFromTimestamp(strtotime($item->changed));
                        } else {
                            $lastModified = Carbon::now()->timestamp;
                        }
                        
                        $sitemap[] = [$url, $this->formatDate($lastModified), 1.0, 'weekly'];
                    }
                }
            }
        }

        return response()->view('api-gateway-drupal::sitemap', [
            'posts' =>  $sitemap
        ])->header('Content-Type', 'text/xml');
    }

    private function formatDate($carbonObject)
    {
        if ($carbonObject instanceof \Illuminate\Support\Carbon or $carbonObject instanceof Carbon) {
            return $carbonObject->tz('Europe/Berlin')->format('Y-m-d\Th:i:sP');
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
            // dd($offset, $count, $sitemapData);
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
