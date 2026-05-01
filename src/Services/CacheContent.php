<?php

namespace IN10\ApiGatewayDrupal\Services;

use Cache;
use Closure;
use Illuminate\Support\Facades\Artisan;

class CacheContent
{
     /**
     * Default seconds for caching
     *
     * @var integer
     */
    protected $limit = 86400; // 24 hours in seconds

    /**
     * Get an item from the cache, or execute the given Closure and store the result.
     *
     * Only persists values whose HTTP status is below 400 when a status can be resolved
     * (array key statusCode, or object with getStatusCode()). Unsolvable response codes are also cached.
     *
     * @param string $name
     * @param Closure $callback
     * @param array $tags
     * @return mixed
     */
    public static function remember(string $name, Closure $callback, array $tags = null)
    {
        $self = new static();
        $useTags = $self->supportTags() && !is_null($tags);

        $cached = $useTags ? Cache::tags($tags)->get($name) : Cache::get($name);
        if (!is_null($cached)) {
            return $cached;
        }

        $value = $callback();

        if ($self->shouldCache($value)) {
            if ($useTags) {
                Cache::tags($tags)->put($name, $value, $self->limit);
            } else {
                Cache::put($name, $value, $self->limit);
            }
        }

        return $value;
    }

    public static function flush(array $tags = null)
    {
        $self = new static();

        if ($self->supportTags() && $tags) {
            return Cache::tags($tags)->flush();
        }

        Artisan::call('cache:clear'); // clear all cache
        return Cache::flush();
    }

    /**
     * @param mixed $value
     * @return bool
     */
    protected function shouldCache($value)
    {
        $code = $this->resolveResponseStatusCode($value);
        if ($code === null || $code < 400) {
            return true;
        }

        return false;
    }

    /**
     * @param mixed $value
     * @return int|null
     */
    protected function resolveResponseStatusCode($value)
    {
        if (is_array($value) && array_key_exists('statusCode', $value)) {
            return (int) $value['statusCode'];
        }
        if (is_object($value) && method_exists($value, 'getStatusCode')) {
            return (int) $value->getStatusCode();
        }

        return null;
    }

    /**
     * Check if cache tags are supported.
     *
     * @return bool
     */
    protected function supportTags()
    {
        return Cache::getStore() instanceof \Illuminate\Cache\TaggableStore;
    }

     public static function forget(array $keys)
    {
        foreach ($keys as $key) {
            if (Cache::has($key)) {
                Cache::forget($key);
            }
        }
    }
}
