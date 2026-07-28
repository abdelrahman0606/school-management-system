<?php

use App\Support\VersionIntegrity;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/v2/health', function () {
    // Cache::set(...) (no explicit ->store('redis')) resolves whatever
    // CACHE_STORE actually is — redis on the Docker stack, database/file on
    // shared cPanel hosting with no Redis configured at all. Hardcoding
    // ->store('redis') here used to force a Redis connection attempt on
    // every health check regardless of the app's real cache config, so this
    // endpoint threw a 500 on exactly the hosting setup this app now
    // explicitly supports — the one place you'd expect a working smoke-test
    // endpoint the most, right after a fresh deploy.
    $version = config('app.version');

    // true/false only where .git is on disk (git-based deploys); null on a
    // zip-uploaded shared-hosting install with no git history to check
    // against — that's "not verifiable", not "broken", so it's surfaced as
    // its own distinct value rather than folded into true/false.
    $versionVerified = VersionIntegrity::isValidFormat($version)
        ? VersionIntegrity::verifyAgainstGit($version)
        : false;

    return response()->json([
        'status' => 'ok',
        'laravel' => app()->version(),
        'env' => app()->environment(),
        'db' => DB::connection()->getPdo() ? 'connected' : 'error',
        'cache' => Cache::set('ping', 'ok', 10) ? 'connected' : 'error',
        'version' => $version,
        'version_verified' => $versionVerified,
    ]);
});

// Auto-load every module's routes/api.php
foreach (glob(__DIR__.'/../app/Modules/*/routes/api.php') as $moduleRoutes) {
    require $moduleRoutes;
}
