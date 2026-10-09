<?php

namespace App\Services;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Throwable;

class SharedNavigationCache
{
    public function __construct(private readonly CacheFactory $cache) {}

    public function enabled(): bool
    {
        return (bool) config('cache.navigation.enabled', false);
    }

    public function ttlSeconds(): int
    {
        return max(1, (int) config('cache.navigation.ttl_seconds', 300));
    }

    /** @param  array<int,string>  $tags */
    public function get(array $tags, string $key): mixed
    {
        if (! $this->enabled()) {
            return null;
        }

        try {
            return $this->cache
                ->store((string) config('cache.navigation.store', 'redis'))
                ->tags($tags)
                ->get($key);
        } catch (Throwable) {
            return null;
        }
    }

    /** @param  array<int,string>  $tags */
    public function put(array $tags, string $key, mixed $value, ?int $ttlSeconds = null): void
    {
        if (! $this->enabled()) {
            return;
        }

        try {
            $this->cache
                ->store((string) config('cache.navigation.store', 'redis'))
                ->tags($tags)
                ->put($key, $value, max(1, $ttlSeconds ?? $this->ttlSeconds()));
        } catch (Throwable) {
            // Redis is an optimization. Callers keep their database result.
        }
    }

    public function flushTag(string $tag): void
    {
        if (! $this->enabled()) {
            return;
        }

        try {
            $this->cache
                ->store((string) config('cache.navigation.store', 'redis'))
                ->tags([$tag])
                ->flush();
        } catch (Throwable) {
            // A cache outage must not block permission or entitlement writes.
        }
    }
}
