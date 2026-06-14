<?php

namespace IN10\ApiGatewayDrupal\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Guards the cache-invalidate webhook. That endpoint can wipe the whole cache
 * (empty body = full flush), so a stray public POST could cold-start the cache
 * and stampede Drupal — exactly the outage we're avoiding.
 *
 * Compares a shared secret sent in the X-Gateway-Secret header against
 * config('cms.webhook_secret'). If no secret is configured the middleware is a
 * no-op, so existing setups keep working until the secret is set on both sides.
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
