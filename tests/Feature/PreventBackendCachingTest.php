<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\School\Models\School;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reported: deleting every item in a menu, saving, and the old items
 * reappearing. The server-side save/reload path does no caching of its own
 * (confirmed by reading MenuService/MenuController) — the far more likely
 * explanation is the browser's back-forward cache (bfcache) restoring a
 * stale page snapshot instead of ever hitting the server again.
 * Cache-Control: no-store is the standard fix, applied to admin/staff/
 * portal only (see PreventBackendCaching's own docblock for why NOT the
 * public site too).
 */
class PreventBackendCachingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_responses_are_marked_uncacheable(): void
    {
        $this->seed(RoleSeeder::class);
        $school = School::create(['name' => 'Test School', 'is_active' => true]);
        $admin = User::factory()->create(['school_id' => $school->id, 'is_active' => true]);
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)->get('/admin/languages');

        $response->assertOk();
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertSame('no-cache', $response->headers->get('Pragma'));
    }

    public function test_public_responses_are_left_alone(): void
    {
        $response = $this->get('/');

        // No opinion either way on the public site's own caching — just
        // confirms this middleware doesn't touch it.
        $cacheControl = $response->headers->get('Cache-Control');
        $this->assertTrue($cacheControl === null || ! str_contains($cacheControl, 'no-store'));
    }
}
