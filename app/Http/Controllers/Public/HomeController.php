<?php

namespace App\Http\Controllers\Public;

use App\Modules\School\Models\School;
use App\Modules\Website\Models\SiteSetting;
use App\Modules\Website\Services\PageRenderService;
use App\Modules\Website\Services\PublicPortalService;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Public school homepage at "/". If a homepage Page with a published block
 * layout exists, it drives the page; otherwise a sensible default landing
 * renders from live data (notices, stats, staff).
 */
class HomeController extends Controller
{
    public function __construct(
        private readonly PublicPortalService $portal,
        private readonly PageRenderService $render,
    ) {}

    public function index(): View
    {
        $school = School::current();

        if (! $school) {
            return view('public.home', [
                'school' => null,
                'settings' => new SiteSetting,
                'notices' => new Collection,
                'staff' => new Collection,
                'stats' => ['active_students' => 0, 'active_staff' => 0],
            ]);
        }

        // A designated homepage page's published layout wins. renderPage()
        // (cached, keyed by the published layout's own id) returns null when
        // there's no published layout for this locale OR the default
        // locale, in which case the default landing below still applies
        // exactly as before.
        $home = $this->render->homepage($school->id);
        $view = $home ? $this->render->renderPage($home, app()->getLocale()) : null;

        if ($home && $view) {
            return view('public.page', [
                'page' => $home,
                'view' => $view,
                'settings' => SiteSetting::forSchool($school->id),
                'school' => $school,
            ]);
        }

        // Fallback: default landing built from live data.
        $locale = app()->getLocale();

        return view('public.home', [
            'school' => $school,
            'settings' => SiteSetting::forSchool($school->id),
            'notices' => $this->portal->notices($school->id, $locale),
            'staff' => $this->portal->staffList($school->id, [], $locale),
            'stats' => $this->portal->stats($school->id),
        ]);
    }
}
