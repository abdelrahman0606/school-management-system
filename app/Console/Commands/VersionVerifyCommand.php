<?php

namespace App\Console\Commands;

use App\Support\VersionIntegrity;
use Illuminate\Console\Command;

/**
 * Manual/CI-friendly wrapper around App\Support\VersionIntegrity — the same
 * check GET /api/v2/health reports, runnable on demand (e.g. right after
 * scripts/deploy.sh finishes, or as a standalone sanity check on a server
 * you're not sure was updated correctly).
 */
class VersionVerifyCommand extends Command
{
    protected $signature = 'version:verify';

    protected $description = 'Verify config(\'app.version\') is well-formed and, where .git is present, matches a real tagged release on disk';

    public function handle(): int
    {
        $version = config('app.version');

        if (! VersionIntegrity::isValidFormat($version)) {
            $this->error("VERSION file is missing or malformed — config('app.version') resolved to '{$version}' instead of a real release number.");

            return self::FAILURE;
        }

        $this->info("Version: {$version}");

        $verified = VersionIntegrity::verifyAgainstGit($version);

        return match ($verified) {
            true => $this->succeed($version),
            false => $this->fail($version),
            null => $this->unverifiable($version),
        };
    }

    private function succeed(string $version): int
    {
        $this->info("Verified — v{$version} is tagged and HEAD is at or after that release.");

        return self::SUCCESS;
    }

    private function fail(string $version): int
    {
        $this->error("NOT verified — either no 'v{$version}' tag exists in this repo's history, or HEAD is BEHIND it.");
        $this->line('This usually means the VERSION file was hand-edited to a value the deployed code does not actually match.');

        return self::FAILURE;
    }

    private function unverifiable(string $version): int
    {
        $this->warn("Can't verify — no .git directory on disk (a zip-uploaded install has no git history to check against).");
        $this->line("This is normal for shared-hosting deploys made without git; see docs/cpanel-deployment.md.");

        return self::SUCCESS;
    }
}
