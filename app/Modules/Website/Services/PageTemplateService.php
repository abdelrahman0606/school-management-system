<?php

namespace App\Modules\Website\Services;

use App\Modules\Language\Models\Language;
use App\Modules\Website\Models\Page;
use App\Modules\Website\Models\PageTemplate;

class PageTemplateService
{
    /**
     * Clones the page's current (latest) DEFAULT LANGUAGE layout into a new
     * school-owned template — templates are starting points for brand-new
     * pages, which always start in the default language (docs/modules/
     * 30-multilingual-content-plan.md Phase 2, PageController::store()), so
     * the default locale's content is the only sensible source here.
     */
    public function saveAsTemplate(Page $page, string $name): PageTemplate
    {
        $latest = $page->layoutsForLocale(Language::defaultCode())->first();

        return PageTemplate::create([
            'school_id' => $page->school_id,
            'name' => $name,
            'layout_json' => $latest?->layout_json ?? [],
        ]);
    }

    public function rename(PageTemplate $template, string $name): PageTemplate
    {
        $template->update(['name' => $name]);

        return $template;
    }

    public function delete(PageTemplate $template): void
    {
        $template->delete();
    }
}
