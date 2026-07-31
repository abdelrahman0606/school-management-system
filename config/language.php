<?php

/*
|--------------------------------------------------------------------------
| Multilingual content — AI-assisted draft translation
|--------------------------------------------------------------------------
| docs/modules/30-multilingual-content-plan.md Phase 5. MyMemoryTranslator
| calls the free MyMemory Translation API (https://mymemory.translated.net)
| — no API key required. The optional 'contact_email' param raises
| MyMemory's daily character cap from ~5,000 (anonymous) to ~50,000 per the
| documented "get a free key" ability actually being a plain registered
| email address, not a real key — leave it unset and the anonymous cap
| simply applies.
|
| Every suggestion produced through this gateway is a DRAFT an admin
| reviews and edits before saving — never auto-published — so MyMemory's
| translation-memory-lookup quality (inconsistent versus a real LLM, but
| free and keyless) is an acceptable trade-off here.
*/

return [

    'mymemory_api_base' => env('MYMEMORY_API_BASE', 'https://api.mymemory.translated.net/get'),

    'mymemory_timeout_seconds' => env('MYMEMORY_TIMEOUT_SECONDS', 10),

    'mymemory_contact_email' => env('MYMEMORY_CONTACT_EMAIL'),

    // MyMemory's own per-request query length limit (~500 bytes) — text
    // longer than this is truncated defensively before the request is even
    // sent, rather than trusting the API to reject or silently mangle it.
    'mymemory_max_chars' => env('MYMEMORY_MAX_CHARS', 480),

];
