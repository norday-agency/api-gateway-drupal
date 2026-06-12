<?php

namespace IN10\ApiGatewayDrupal\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use IN10\ApiGatewayDrupal\Services\RouteIndex;

/**
 * Webhook target for Drupal to keep the route index fresh: add a path on
 * publish / path change, remove it on unpublish / delete.
 *
 * Guarded by the VerifyWebhookSecret middleware. Both endpoints accept a single
 * "path" or a list of "paths", and a "locale".
 */
class RouteIndexController extends Controller
{
    public function add(Request $request): JsonResponse
    {
        return $this->apply($request, 'add');
    }

    public function remove(Request $request): JsonResponse
    {
        return $this->apply($request, 'remove');
    }

    protected function apply(Request $request, string $op): JsonResponse
    {
        $locale = $request->input('locale');
        if (! is_string($locale) || $locale === '') {
            return new JsonResponse(['error' => 'locale is required'], 422);
        }

        $paths = $this->paths($request);
        if ($paths === []) {
            return new JsonResponse(['error' => 'no path(s) provided'], 422);
        }

        $built = RouteIndex::exists($locale);
        $changed = [];
        foreach ($paths as $path) {
            if (RouteIndex::{$op}($locale, $path)) {
                $changed[] = $path;
            }
        }

        return new JsonResponse([
            'op' => $op,
            'locale' => $locale,
            // When the index isn't built yet, add/remove are intentionally no-ops;
            // surface that so a caller isn't surprised nothing changed.
            'index_built' => $built,
            'changed' => $changed,
        ]);
    }

    /**
     * @return string[]
     */
    protected function paths(Request $request): array
    {
        $paths = $request->input('paths', $request->input('path'));

        if (is_null($paths)) {
            return [];
        }

        if (! is_array($paths)) {
            $paths = [$paths];
        }

        return array_values(array_filter(
            $paths,
            static fn ($path) => is_string($path) && trim($path) !== ''
        ));
    }
}
