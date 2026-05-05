<?php

namespace IN10\ApiGatewayDrupal\Services;

use Cache;
use Closure;

/**
 * Cache wrapper that supports tag-based invalidation even on cache stores
 * that don't natively support tags (like the file driver).
 *
 * On Redis/Memcached: delegates to Laravel's native Cache::tags(...) API.
 * On file/database/etc: keeps a manual "index" per tag — a list of cache
 * keys that belong to that tag — so flushing a tag only clears the tracked
 * keys instead of nuking the entire cache.
 */
class CacheContent
{
    /**
     * Prefix for the per-tag index entries we store in the cache.
     * Each tag gets its own index entry under: {prefix}{sha1(tag)}
     * The sha1 keeps the key safe for any cache driver (incl. file paths).
     */
    protected const TAG_INDEX_PREFIX = 'api_gateway_drupal_cache_tags:';

    /**
     * Get an item from the cache, or execute the given Closure and store the result.
     *
     * @param string       $name     Cache key for the value itself.
     * @param Closure      $callback Produces the value on a cache miss.
     * @param array|null   $tags     Optional tags this entry belongs to.
     * @param int|null     $ttl      TTL in seconds; falls back to config('cms.cache.ttl').
     * @return mixed
     */
    public static function remember(string $name, Closure $callback, ?array $tags = null, ?int $ttl = null)
    {
        $self = new static();

        // Default TTL comes from cms.cache.ttl (default 86400s = 24h).
        $ttl = $ttl ?? (int) config('cms.cache.ttl');

        // Clean up tags: drop nulls/empties/duplicates, coerce strings.
        $tags = $self->normalizeTags($tags);

        // Happy path: the cache store supports tags natively (Redis/Memcached).
        // Let Laravel handle association + invalidation.
        if ($self->supportTags() && $tags) {
            return Cache::tags($tags)->remember($name, $ttl, $callback);
        }

        // Fallback path: no native tag support (e.g. file driver).
        // Cache the value normally...
        $value = Cache::remember($name, $ttl, $callback);

        // ...then record this key in each tag's index so we can find it
        // again when someone calls flush($tag).
        if ($tags) {
            $self->trackTags($tags, $name);
        }

        return $value;
    }

    /**
     * Flush tagged cache entries.
     *
     * - flush(['redirects'])         → only entries tagged 'redirects'
     * - flush()  (null)              → full cache flush (intentional reset)
     * - flush('') / flush([])        → no-op, returns false (safety guard)
     *
     * On non-taggable stores we use the per-tag index built by trackTags()
     * so we never have to flush the whole cache to invalidate one tag.
     *
     * @param array|string|null $tags
     * @return bool
     */
    public static function flush($tags = null)
    {
        $self = new static();

        // Distinguish "flush everything" (null) from "flush these tags".
        // An empty/whitespace tag list is treated as a no-op, NOT a full flush.
        $flushAll = is_null($tags);
        $tags = $self->normalizeTags($tags);

        // Happy path: native tag flush on Redis/Memcached.
        if ($self->supportTags() && $tags) {
            return Cache::tags($tags)->flush();
        }

        // Fallback path: walk our manual index and forget each tracked key.
        if ($tags) {
            return $self->flushTrackedTags($tags);
        }

        // Only an explicit flush() with no arguments wipes everything.
        // An empty/invalid tag input returns false instead of nuking the cache.
        return $flushAll ? Cache::flush() : false;
    }

    /**
     * Whether the active cache store supports Laravel's native tag API.
     * True for Redis/Memcached/Array; false for file/database.
     */
    protected function supportTags()
    {
        return Cache::getStore() instanceof \Illuminate\Cache\TaggableStore;
    }

    /**
     * Turn whatever was passed (null/string/array, possibly with junk)
     * into a clean string[] of unique, non-empty tag names.
     *
     * @param array|string|null $tags
     */
    protected function normalizeTags($tags): array
    {
        if (is_null($tags)) {
            return [];
        }

        // Allow callers to pass a single tag as a plain string.
        if (!is_array($tags)) {
            $tags = [$tags];
        }

        $normalized = [];

        foreach ($tags as $tag) {
            // Defensive: skip nested arrays — only scalar tag names allowed.
            if (is_array($tag)) {
                continue;
            }

            $tag = trim((string) $tag);

            // Drop empty strings so they can never trigger a "flush all".
            if ($tag !== '') {
                $normalized[] = $tag;
            }
        }

        return array_values(array_unique($normalized));
    }

    /**
     * Record that cache key $name belongs to each of the given tags.
     *
     * For each tag we keep an "index" entry in the cache: a list of cache
     * keys filed under that tag. Stored with Cache::forever() — the index
     * must outlive the data entries it points to so flush() can find them.
     *
     * Trade-offs to be aware of:
     *  - Read-modify-write isn't atomic; a concurrent write may lose a key
     *    from the index (entry still expires via its own TTL, so worst case
     *    is one entry not flushed by the next tag flush).
     *  - The index grows over time and is only reset on flush() of that tag.
     */
    protected function trackTags(array $tags, string $name): void
    {
        foreach ($tags as $tag) {
            $indexKey = $this->tagIndexKey($tag);

            // Read current list of keys filed under this tag (empty if none).
            $keys = Cache::get($indexKey, []);

            // Avoid duplicates if the same key gets re-cached.
            if (!in_array($name, $keys, true)) {
                $keys[] = $name;

                // forever() — no TTL. The index has to outlive its entries.
                Cache::forever($indexKey, $keys);
            }
        }
    }

    /**
     * Manual replacement for Cache::tags($tags)->flush() on non-taggable stores.
     *
     * For each tag: read its index, forget every cache key in the list,
     * then forget the index entry itself.
     *
     * Returns true if at least one key (data or index) was actually deleted.
     */
    protected function flushTrackedTags(array $tags): bool
    {
        $flushed = false;

        foreach ($tags as $tag) {
            $indexKey = $this->tagIndexKey($tag);

            // List of cache keys we previously filed under this tag.
            $keys = Cache::get($indexKey, []);

            // Forget the actual cached values one by one.
            // Cache::forget() returns false for already-expired/missing keys,
            // which is harmless — we just don't flip $flushed in that case.
            foreach ($keys as $key) {
                $flushed = Cache::forget($key) || $flushed;
            }

            // Throw the index card away too, so the next remember() rebuilds
            // it from scratch instead of carrying stale entries forward.
            $flushed = Cache::forget($indexKey) || $flushed;
        }

        return $flushed;
    }

    /**
     * Cache key under which a tag's index is stored.
     * sha1() keeps the key safe across all drivers (avoids odd characters
     * in tag names ending up in cache filenames or Redis keys).
     */
    protected function tagIndexKey(string $tag): string
    {
        return self::TAG_INDEX_PREFIX . sha1($tag);
    }
}
