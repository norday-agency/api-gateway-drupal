<?php

namespace IN10\ApiGatewayDrupal\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use IN10\ApiGatewayDrupal\Services\CacheContent;

class CacheController extends Controller
{
    /**
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function invalidate(Request $request)
    {
        // Accept 'tags' (preferred) or 'tag' (legacy) and normalize to an array.
        $tags = $request->input('tags', $request->input('tag'));

        if (is_null($tags)) {
            $tags = [];
        }

        if (!is_array($tags)) {
            $tags = [$tags];
        }

        // Drop empties / non-scalars so a stray null can never trigger a full flush.
        $tags = array_values(array_filter($tags, function ($tag) {
            return is_string($tag) && trim($tag) !== '';
        }));

        if (empty($tags)) {
            // Fallback: flush everything if no usable tags were provided.
            // Some projects rely on calling this endpoint with no body to wipe the cache.
            CacheContent::flush();
            return new JsonResponse(false, 400);
        }

        $result = CacheContent::flush($tags);

        return new JsonResponse($result);
    }
}
