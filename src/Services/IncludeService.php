<?php

namespace IN10\ApiGatewayDrupal\Services;

use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Support\Str;
use Throwable;

/**
 * The include paths a frontend may pass as ?include=, per content type.
 * Drupal lists them (drupal_laravel_cache: /{locale}/gateway/includes) as the default_include of each resource.
 */
class IncludeService
{
    /**
     * Include paths keyed by resource path, which is the type name in cms.types. Null when Drupal has no list.
     */
    public static function all(): ?array
    {
        $cache_id = hash('sha256', implode('.', [config('cms.url'), 'gateway-includes']));
        $data = CacheContent::remember($cache_id, fn () => self::fetch(), ['includes']);

        return $data['statusCode'] === 200 ? $data['data'] : null;
    }

    /**
     * Null when Drupal has no list for this type; the request then keeps Drupal's default includes.
     */
    public static function forType(string $type): ?array
    {
        return self::all()[$type] ?? null;
    }

    /**
     * Splits "a, b,c" into ['a', 'b', 'c']. An empty value means: include nothing.
     */
    public static function parse($raw): array
    {
        if (!is_string($raw)) {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $raw)), fn ($path) => $path !== ''));
    }

    /**
     * The requested paths that are not allowed. A path is allowed when it is in the list or is the start of one,
     * so "header_image" is fine when "header_image.image.image" is listed.
     */
    public static function invalid(array $requested, array $allowed): array
    {
        return array_values(array_filter($requested, function (string $path) use ($allowed) {
            foreach ($allowed as $allowed_path) {
                if ($allowed_path === $path || Str::startsWith($allowed_path, $path . '.')) {
                    return false;
                }
            }

            return true;
        }));
    }

    private static function fetch(): array
    {
        $options = [
            'http_errors' => false,
            'headers' => ['Accept' => 'application/json'],
            'connect_timeout' => config('cms.http.connect_timeout', 5),
            'timeout' => config('cms.http.timeout', 30),
            'verify' => !in_array(config('app.env'), ['local', 'test']),
        ];

        if (config('cms.useBasicAuthentication') && config('cms.BasicAuthBase64String')) {
            $options['headers']['Authorization'] = 'Basic ' . config('cms.BasicAuthBase64String');
        }

        try {
            $response = (new GuzzleClient())->request(
                'GET',
                config('cms.url') . '/' . config('app.base_locale', 'nl') . '/gateway/includes',
                $options
            );
        } catch (Throwable $exception) {
            return ['statusCode' => 503];
        }

        $includes = json_decode((string) $response->getBody(), true);

        // A 404 means an older drupal_laravel_cache without the endpoint; CacheContent keeps that for a minute.
        if ($response->getStatusCode() !== 200 || !is_array($includes)) {
            return ['statusCode' => $response->getStatusCode() === 200 ? 502 : $response->getStatusCode()];
        }

        return ['data' => $includes, 'statusCode' => 200];
    }
}
