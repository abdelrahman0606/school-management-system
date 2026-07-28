<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class VersionVerifyCommandTest extends TestCase
{
    public function test_reports_failure_for_a_malformed_version(): void
    {
        config(['app.version' => 'not-a-real-version']);

        $this->artisan('version:verify')
            ->assertFailed()
            ->expectsOutputToContain('malformed');
    }

    /**
     * Deliberately does NOT assert success/failure here — the real outcome
     * depends on whether HEAD happens to be exactly on (or after) a tagged
     * release commit at whatever moment the test suite runs, which drifts
     * as the repo gets more commits and isn't this test's concern. The
     * actual true/false/null decision logic is covered by
     * tests/Unit/Support/VersionIntegrityTest.php against a disposable,
     * fully controlled git repo instead — this just confirms the command
     * runs against a real checkout without crashing.
     */
    public function test_runs_without_crashing_against_whatever_this_checkout_actually_is(): void
    {
        $exitCode = Artisan::call('version:verify');

        $this->assertContains($exitCode, [0, 1]);
    }
}
