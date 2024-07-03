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
}
