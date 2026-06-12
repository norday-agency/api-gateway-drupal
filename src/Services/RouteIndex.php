<?php

namespace IN10\ApiGatewayDrupal\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Authoritative index of valid content paths, per locale.
 *
 * The point: a *cache* only knows what's been asked for, so cache-absence can't
 * prove non-existence. This index is the complete, authoritative set of valid
 * paths — so absence here *does* mean "this page does not exist", letting the
 * gateway return an instant 404 for junk/scan paths WITHOUT calling Drupal.
 *
 * Safety contract:
 *  - Only rebuild() (a full build from Drupal) marks the index "built" for a
 *    locale. Until then exists() is false and callers MUST fall back to asking
 *    Drupal — never 404 from a not-yet-built index, or you blank the whole site.
 *  - add()/remove() (webhook upkeep) only mutate an already-built index; before
 *    the first rebuild they are no-ops (the next rebuild will pick the change up).
 *
 * Storage uses the cache (works on file + redis): one assoc array per locale,
 * keyed by path for O(1) isset(). For very large sites a native redis SET would
 * avoid loading the whole list per request — a future optimisation.
 */
class RouteIndex
{
    /** Per-locale set of valid paths: { "/some/path": true, ... } */
    protected const SET_PREFIX = 'route_index:set:';

    /** Per-locale flag: has a full rebuild ever populated this locale? */
    protected const BUILT_PREFIX = 'route_index:built:';

    /**
     * Is there an authoritative index for this locale? Only true after rebuild().
     * When false, callers must behave as if there were no index (ask Drupal).
     */
    public static function exists(string $locale): bool
    {
        return (bool) Cache::get(self::BUILT_PREFIX . $locale, false);
    }

    /**
     * Is this path a known valid route for this locale?
     */
    public static function has(string $locale, string $path): bool
    {
        $set = Cache::get(self::SET_PREFIX . $locale, []);

        return isset($set[self::normalize($path)]);
    }

    /**
     * Add a path to an already-built index (webhook on publish / path change).
     * No-op before the first rebuild — the upcoming rebuild will include it.
     */
    public static function add(string $locale, string $path): bool
    {
        if (! self::exists($locale)) {
            return false;
        }

        $path = self::normalize($path);
        if ($path === '') {
            return false;
        }

        $set = Cache::get(self::SET_PREFIX . $locale, []);
        $set[$path] = true;
        Cache::forever(self::SET_PREFIX . $locale, $set);

        return true;
    }

    /**
     * Remove a path from an already-built index (webhook on unpublish / delete).
     * No-op before the first rebuild.
     */
    public static function remove(string $locale, string $path): bool
    {
        if (! self::exists($locale)) {
            return false;
        }

        $path = self::normalize($path);

        $set = Cache::get(self::SET_PREFIX . $locale, []);
        if (! isset($set[$path])) {
            return false;
        }

        unset($set[$path]);
        Cache::forever(self::SET_PREFIX . $locale, $set);

        return true;
    }

    /**
     * Replace the whole index for a locale and mark it authoritative.
     * This is the only method that flips exists() to true.
     *
     * @param string[] $paths
     * @return int number of unique paths indexed
     */
    public static function rebuild(string $locale, array $paths): int
    {
        $set = [];
        foreach ($paths as $path) {
            $path = self::normalize($path);
            if ($path !== '') {
                $set[$path] = true;
            }
        }

        Cache::forever(self::SET_PREFIX . $locale, $set);
        Cache::forever(self::BUILT_PREFIX . $locale, true);

        return count($set);
    }

    /**
     * Drop the index for a locale (back to "not built" → callers ask Drupal).
     */
    public static function clear(string $locale): void
    {
        Cache::forget(self::SET_PREFIX . $locale);
        Cache::forget(self::BUILT_PREFIX . $locale);
    }

    /**
     * Canonicalise a path so storage and lookup always match: exactly one
     * leading slash, no trailing slash, no query string.
     */
    protected static function normalize(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '';
        }

        // Drop any query string — the index keys on the path only.
        $path = explode('?', $path, 2)[0];
        $path = '/' . trim($path, '/');

        return $path === '/' ? '/' : rtrim($path, '/');
    }
}
