<?php

namespace IN10\ApiGatewayDrupal\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use IN10\ApiGatewayDrupal\Services\DataService;

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
    public function getSitemap(Request $request)
    {
        $cacheKey = 'items';

        Cache::remember($cacheKey, 3600, function () use (&$request) {
            return $this->fillMap($request);
        });

        return response()->view('api-gateway-drupal::sitemap', [
            'posts' => Cache::get($cacheKey),
        ])->header('Content-Type', 'text/xml');
    }

    private function formatDate($carbonObject)
    {
        if ($carbonObject instanceof \Illuminate\Support\Carbon or $carbonObject instanceof Carbon) {
            return $carbonObject->tz('Europe/Berlin')->format('Y-m-d\Th:i:sP');
        }
    }

    private function fillMap($request)
    {
        $items = [];
        foreach (config('app.supported_locales') as $locale) {
            foreach (array_keys(config('cms.types')) as $type) {
                $page = 1;

                while (1) {
                    if ($data = $this->fetchPageForType($page, $request, $locale, $type)) {
                        $data->each(function ($item) use ($locale, &$items) {
                            $url = config('app.website_url') . (($locale === config('app.base_locale', 'nl')) ? $item->slug : '/' . $locale . $item->slug);
                            if (Carbon::now()->timestamp >= strtotime($item->publish_date)) {
                                $lastModified = $item->edit_date;

                                $items[] = [$url, $this->formatDate($lastModified), 1.0, 'weekly'];
                            }
                        });
                    } else {
                        break;
                    }

                    ++$page;
                }
            }
        }

        return $items;
    }

    private function fetchPageForType(int $page = 1, Request $request, $locale, $type)
    {
        $request->request->set('page', $page);

        if ($data = DataService::getContentType($request, $locale, $type)) {
            return $data;
        } else {
            return null;
        }
    }
}
