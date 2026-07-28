<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * /api/v2/health used to hardcode Cache::store('redis'), forcing a Redis
 * connection attempt regardless of the app's actual CACHE_STORE — which
 * meant this exact endpoint threw a 500 on any deployment (e.g. shared
 * cPanel hosting, see docs/cpanel-deployment.md) that doesn't configure
 * Redis at all. It must resolve whatever cache store is actually
 * configured instead.
 */
class HealthTest extends TestCase
{
    public function test_health_check_uses_the_configured_cache_store_not_a_hardcoded_redis(): void
    {
        $response = $this->getJson('/api/v2/health')->assertOk();

        $response->assertJson(['status' => 'ok', 'cache' => 'connected']);
        $response->assertJsonMissingPath('redis');
    }
}
