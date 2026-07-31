<?php

/*
|--------------------------------------------------------------------------
| Sms Module Language Lines
|--------------------------------------------------------------------------
| First lang file in v2 — per the Global Product Rules, user-facing strings
| (including SMS templates) go through translation keys, never hardcoded.
|
| Deliberately NOT named "sms.php": the admin UI uses the bare English
| string __('SMS') as a translation key dozens of places (nav label,
| command palette, page titles/breadcrumbs — see sidebar.blade.php,
| command-palette.blade.php, admin/comms/sms/*.blade.php). Laravel's
| translator resolves a no-dot key like "SMS" by treating the WHOLE key as
| a GROUP name when it isn't found in the flat English-as-key JSON cache —
| i.e. it looks for resources/lang/{locale}/SMS.php. A group file literally
| named sms.php sits one filesystem-case-fold away from that lookup, and on
| a case-insensitive mount (e.g. this repo's Windows bind-mount under
| Docker Desktop) "SMS.php" resolves straight to "sms.php". Since the key
| has no item segment, Arr::get() with a null item returns the ENTIRE
| group array — so __('SMS') silently returned ['due_reminder' => '...']
| instead of the string "SMS", and htmlspecialchars()'d that array fatally
| the moment any admin page rendered under a non-English backend_locale
| (see CLAUDE.md's Language module notes / CHANGELOG for the bug report).
| Named _templates to make a future collision with a plain English label
| extremely unlikely.
*/

return [
    'due_reminder' => 'Dear Guardian, :student has an outstanding fee balance of :currency :amount. Kindly clear the due amount at your earliest convenience. Thank you.',
];
