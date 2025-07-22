<?php

namespace IN10\ApiGatewayDrupal\Services;

use Cache;
use Closure;

class CacheContent
{
    /**
     * Default minutes for caching
     *
     * @var integer
     */
    protected $limit = 86400;

    /**
     * Get an item from the cache, or execute the given Closure and store the result.
     *
     * @param string $name
     * @param Closure $callback
     * @param array $tags
     * @return mixed
     */
    public static function remember(string $name, Closure $callback, array $tags = null)
    {
        $self = new static();

        if ($self->supportTags() && !is_null($tags)) {
            return Cache::tags($tags)->remember($name, $self->limit, $callback);
        }

        return Cache::remember($name, $self->limit, $callback);
    }

    public static function flush(array $tags = null)
    {
        $self = new static();

        if ($self->supportTags() && $tags) {
            return Cache::tags($tags)->flush();
        }

        return Cache::flush();
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
}
