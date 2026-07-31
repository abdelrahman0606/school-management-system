<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Disables browser caching AND the back-forward cache (bfcache) for the
 * admin/staff/portal areas — reported symptom: delete every item in a menu,
 * Save, and the old (already-deleted) items reappear. The server-side
 * save/reload logic here is a plain DB read on every GET, nothing cached
 * application-side (confirmed: MenuService/MenuController do no caching at
 * all) — the far more likely explanation is the BROWSER restoring a
 * snapshot of the page exactly as it looked before a navigation (bfcache),
 * which happens entirely client-side and skips the server (and therefore
 * this app's own fresh-data logic) altogether. `Cache-Control: no-store` is
 * the standard, documented way to opt a page out of bfcache in every major
 * browser; `Pragma`/`Expires` are the legacy HTTP/1.0 equivalents some
 * intermediate proxies still respect.
 *
 * Applied broadly to every backend response rather than just the menu
 * editor — the same class of "did I really just reload this from the
 * server" confusion could hit any authenticated admin/staff/portal screen,
 * and there's no legitimate reason to let a browser cache session-scoped,
 * frequently-changing admin data anyway (also closes a minor
 * logout-then-back-button data exposure gap).
 *
 * Registered on the whole 'web' group (bootstrap/app.php) rather than added
 * to the admin/staff/portal route groups individually — self-scopes by path
 * instead, the exact same 'admin'/'admin/*' segment-boundary check
 * SetLocale uses to pick app_locale vs backend_locale (see that class's own
 * docblock for why a bare 'admin*' wildcard is wrong: it would also match a
 * PUBLIC page whose slug merely starts with those letters). One shared
 * definition of "which area is this" instead of three separate route-group
 * middleware arrays that could silently drift out of sync or miss a future
 * fourth area.
 */
class PreventBackendCaching
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->is('admin', 'admin/*', 'staff', 'staff/*', 'portal', 'portal/*')) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
        }

        return $response;
    }
}
