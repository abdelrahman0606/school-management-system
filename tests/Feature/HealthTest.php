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

    /**
     * config('app.version') always resolves to a string (falls back to
     * 'unknown' for a missing/malformed VERSION file — see config/app.php
     * and App\Support\VersionIntegrity::isValidFormat()), and
     * version_verified is present with whatever App\Support\VersionIntegrity
     * ::verifyAgainstGit() returns for however this test environment
     * happens to be checked out — true/false/null are all valid answers
     * here depending on whether .git exists and whether the currently
     * checked-out commit happens to be tagged, so this only asserts the key
     * exists and holds one of those three shapes, not a specific value.
     */
    public function test_health_check_reports_a_version_and_its_verification_status(): void
    {
        $response = $this->getJson('/api/v2/health')->assertOk();

        $response->assertJsonStructure(['version', 'version_verified']);
        $this->assertIsString($response->json('version'));
        $this->assertContains($response->json('version_verified'), [true, false, null]);
    }
}
