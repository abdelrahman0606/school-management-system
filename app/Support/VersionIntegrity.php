<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;

/**
 * Two separate questions about the /VERSION file, kept deliberately separate:
 *
 * 1. isValidFormat() — is the file's CONTENT well-formed at all? Pure string
 *    check, no I/O, safe to call from config/app.php at boot time (which is
 *    where a malformed/hand-edited VERSION file gets caught and safely
 *    falls back to 'unknown' instead of displaying garbage).
 *
 * 2. verifyAgainstGit() — does that version NUMBER actually correspond to
 *    real, tagged code on disk, or was it hand-edited to some arbitrary
 *    value without the matching release actually being deployed? This is a
 *    much heavier check (shells out to git), so it deliberately is NOT run
 *    on every request/from config — only on demand (the health endpoint,
 *    `php artisan version:verify`, and deploy.sh's smoke test).
 *
 * verifyAgainstGit() only meaningfully works where .git is present on disk —
 * true for every git-based deploy (scripts/deploy.sh, or `git pull` by
 * hand), NOT true for a zip-uploaded shared-hosting install with no git
 * metadata at all. It returns null (not false!) when there's no .git
 * directory — "can't verify" is a different answer than "verified and it's
 * wrong", and treating shared hosting's normal, expected lack of .git as a
 * tamper signal would make this check useless noise for exactly the
 * deployment path most of docs/cpanel-deployment.md is written for.
 */
class VersionIntegrity
{
    /** Semver core (X.Y.Z), optional -prerelease suffix. Rejects 'unknown', empty, and anything hand-typed wrong. */
    private const PATTERN = '/^\d+\.\d+\.\d+(-[0-9A-Za-z.-]+)?$/';

    public static function isValidFormat(string $version): bool
    {
        return preg_match(self::PATTERN, $version) === 1;
    }

    /**
     * @return bool|null true = HEAD is at or after the tagged release this
     *   VERSION claims; false = that tag doesn't exist (this also covers the
     *   git binary being missing/unusable despite .git being present — a
     *   rare, self-inflicted setup this doesn't try to distinguish further),
     *   or HEAD is BEHIND it (VERSION claims a release that isn't actually
     *   on disk); null = not verifiable at all, no .git directory on disk.
     */
    public static function verifyAgainstGit(string $version, ?string $basePath = null): ?bool
    {
        $basePath ??= base_path();

        if (! is_dir($basePath.'/.git')) {
            return null;
        }

        if (! self::isValidFormat($version)) {
            return false;
        }

        // A short cache so a monitoring service hammering /api/v2/health
        // every few seconds doesn't shell out to git that often — this
        // never changes except across an actual deploy.
        return Cache::remember(
            'version-integrity:'.sha1($basePath.'@'.$version),
            60,
            fn () => self::check($basePath, $version),
        );
    }

    private static function check(string $basePath, string $version): ?bool
    {
        $tagCommit = self::run($basePath, ['git', 'rev-list', '-n', '1', 'v'.$version]);
        if ($tagCommit === null) {
            return false; // no tag matching this version exists anywhere in this repo's history
        }

        $head = self::run($basePath, ['git', 'rev-parse', 'HEAD']);
        if ($head === null) {
            return null; // git ran for the tag lookup but not this — treat as unverifiable, not a lie
        }

        if ($head === $tagCommit) {
            return true;
        }

        // VERSION is allowed to lag behind HEAD (normal on `dev` between
        // releases — you haven't bumped VERSION for the newest commits
        // yet), but HEAD must never be an ANCESTOR of the tag it claims —
        // that would mean VERSION was set ahead of the code that's
        // actually deployed.
        $result = Process::path($basePath)->run(['git', 'merge-base', '--is-ancestor', $tagCommit, 'HEAD']);

        return $result->successful();
    }

    private static function run(string $basePath, array $command): ?string
    {
        $result = Process::path($basePath)->run($command);

        if (! $result->successful()) {
            return null;
        }

        $output = trim($result->output());

        return $output !== '' ? $output : null;
    }
}
