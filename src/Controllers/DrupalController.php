<?php

namespace IN10\ApiGatewayDrupal\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use IN10\ApiGatewayDrupal\Services\DataService;

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
     * Load drupal complete sitemap based on .env vars
     */
    public function getSitemap()
    {
        $sitemapUrl = 'https://' . config('filesystems.disks.s3.url') . '/xmlsitemap/' . config('cms.sitemap.folder') . '/1.xml';
        $sitemapData = file_get_contents($sitemapUrl);
        return response($sitemapData)->header('Content-Type', 'text/xml');
    }

    private function fetchPageForType(int $page = 1, Request $request, $locale, $type)
    {
        $request->request->set('page', $page);

        if ($data = DataService::getContentType($request, $locale, $type)) {
            return $data;
        } else {
            return NULL;
        }
    }  
       
}
