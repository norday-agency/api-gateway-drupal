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
        $tag = $request->input('tag');

        if (!is_array($tag)) {
            $tag = [$tag];
        }

        if (empty(array_filter($tag))) {
            return new JsonResponse(false, 400);
        }

        $result = CacheContent::flush($tag);

        return new JsonResponse($result);
    }
}
