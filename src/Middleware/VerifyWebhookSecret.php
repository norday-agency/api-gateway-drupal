<?php

namespace IN10\ApiGatewayDrupal\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Guards the Drupal->gateway webhooks (cache invalidation and route-index
 * upkeep). These control what the site treats as a valid page / what gets
 * flushed, so they must not be callable by just anyone.
 *
 * Compares a shared secret sent in the X-Gateway-Secret header against
 * config('cms.webhook_secret'). If no secret is configured the middleware is a
 * no-op, so existing projects that haven't set one keep working unchanged.
 */
class VerifyWebhookSecret
{
    public function handle(Request $request, Closure $next)
    {
        $expected = config('cms.webhook_secret');

        if (empty($expected)) {
            return $next($request);
        }

        $provided = $request->header('X-Gateway-Secret');

        if (! is_string($provided) || ! hash_equals((string) $expected, $provided)) {
            return new JsonResponse(['error' => 'unauthorized'], 401);
        }

        return $next($request);
    }
}
