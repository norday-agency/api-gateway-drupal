<?php

namespace IN10\ApiGatewayDrupal\Controllers;

use App\Http\Controllers\Controller;
use IN10\ApiGatewayDrupal\Services\DataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravelium\Sitemap\Sitemap;

class DrupalController extends Controller
{
    /**
     * Handle form post
     *
     * @return void
     */
    public function handleForm(Request $request, string $locale, string $id)
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
     * Load data for menu
     */
    public function getMenu(Request $request, string $locale, string $type): JsonResponse
    {
        return DataService::getMenu($request, $locale, $type);
    }

    /**
     * Load specific Drupal content
     */
    public function getContentType(Request $request, string $locale, ?string $slug = null): JsonResponse
    {
        $type = $request->route()->getName();

        return DataService::getContentTypeJson($request, $locale, $type, $slug);
    }

    /**
     * Load drupal complete sitemap
     */
    public function getSitemap(Request $request, Sitemap $sitemap)
    {
        $sitemap->setCache('sitemap', 3600);

        if (!$sitemap->isCached()) {
            foreach (config('app.supported_locales') as $locale) {
                foreach (array_keys(config('admin.types')) as $type) {
                    if ($data = DataService::getContentType($request, $locale, $type)) {
                        $data->each(function ($item) use ($locale, $sitemap) {
                            $url = config('app.website_url') . (($locale === 'nl') ? $item->slug : '/' . $locale . $item->slug);
                            $lastModified = $item->edit_date;
                            $sitemap->add($url, $lastModified, '1.0', 'weekly');
                        });
                    }
                }
            }
        }

        return $sitemap->render('xml');
    }
}
