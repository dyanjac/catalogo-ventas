<?php

namespace Tests\Unit;

use App\Services\SharedNavigationCache;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SharedNavigationCacheTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cache.navigation.enabled' => true,
            'cache.navigation.store' => 'array',
            'cache.navigation.ttl_seconds' => 300,
        ]);
        Cache::store('array')->flush();
    }

    public function test_it_stores_and_invalidates_tagged_values(): void
    {
        $cache = app(SharedNavigationCache::class);
        $tags = ['navigation-response', 'navigation-response:user:10'];

        $cache->put($tags, 'roles', ['sales_manager']);

        $this->assertSame(['sales_manager'], $cache->get($tags, 'roles'));

        $cache->flushTag('navigation-response:user:10');

        $this->assertNull($cache->get($tags, 'roles'));
    }

    public function test_it_degrades_to_a_cache_miss_when_the_store_is_unavailable(): void
    {
        config(['cache.navigation.store' => 'missing-store']);

        $cache = app(SharedNavigationCache::class);
        $cache->put(['navigation-response'], 'roles', ['sales_manager']);

        $this->assertNull($cache->get(['navigation-response'], 'roles'));
    }
}
