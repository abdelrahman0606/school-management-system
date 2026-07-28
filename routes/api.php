<?php

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
    return response()->json([
        'status' => 'ok',
        'laravel' => app()->version(),
        'env' => app()->environment(),
        'db' => DB::connection()->getPdo() ? 'connected' : 'error',
        'cache' => Cache::set('ping', 'ok', 10) ? 'connected' : 'error',
    ]);
});

// Auto-load every module's routes/api.php
foreach (glob(__DIR__.'/../app/Modules/*/routes/api.php') as $moduleRoutes) {
    require $moduleRoutes;
}
