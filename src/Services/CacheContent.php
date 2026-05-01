<?php

namespace IN10\ApiGatewayDrupal\Services;

use Cache;
use Closure;

class CacheContent
{
    protected const TAG_INDEX_PREFIX = 'api_gateway_drupal_cache_tags:';

    /**
     * Get an item from the cache, or execute the given Closure and store the result.
     *
     * @param string $name
     * @param Closure $callback
     * @param array $tags
     * @param int|null $ttl
     * @return mixed
     */
    public static function remember(string $name, Closure $callback, ?array $tags = null, ?int $ttl = null)
    {
        $self = new static();
        $ttl = $ttl ?? (int) config('cms.cache.ttl');
        $tags = $self->normalizeTags($tags);

        if ($self->supportTags() && $tags) {
            return Cache::tags($tags)->remember($name, $ttl, $callback);
        }

        $value = Cache::remember($name, $ttl, $callback);

        if ($tags) {
            $self->trackTags($tags, $name);
        }

        return $value;
    }

    /**
     * Flush tagged cache entries. Falls back to a tracked key index for stores
     * without native tag support instead of flushing the entire cache.
     *
     * @param array|string|null $tags
     * @return bool
     */
    public static function flush($tags = null)
    {
        $self = new static();
        $flushAll = is_null($tags);
        $tags = $self->normalizeTags($tags);

        if ($self->supportTags() && $tags) {
            return Cache::tags($tags)->flush();
        }

        if ($tags) {
            return $self->flushTrackedTags($tags);
        }

        return $flushAll ? Cache::flush() : false;
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

    /**
     * @param array|string|null $tags
     */
    protected function normalizeTags($tags): array
    {
        if (is_null($tags)) {
            return [];
        }

        if (!is_array($tags)) {
            $tags = [$tags];
        }

        $normalized = [];

        foreach ($tags as $tag) {
            if (is_array($tag)) {
                continue;
            }

            $tag = trim((string) $tag);

            if ($tag !== '') {
                $normalized[] = $tag;
            }
        }

        return array_values(array_unique($normalized));
    }

    protected function trackTags(array $tags, string $name): void
    {
        foreach ($tags as $tag) {
            $indexKey = $this->tagIndexKey($tag);
            $keys = Cache::get($indexKey, []);

            if (!in_array($name, $keys, true)) {
                $keys[] = $name;
                Cache::forever($indexKey, $keys);
            }
        }
    }

    protected function flushTrackedTags(array $tags): bool
    {
        $flushed = false;

        foreach ($tags as $tag) {
            $indexKey = $this->tagIndexKey($tag);
            $keys = Cache::get($indexKey, []);

            foreach ($keys as $key) {
                $flushed = Cache::forget($key) || $flushed;
            }

            $flushed = Cache::forget($indexKey) || $flushed;
        }

        return $flushed;
    }

    protected function tagIndexKey(string $tag): string
    {
        return self::TAG_INDEX_PREFIX . sha1($tag);
    }
}
