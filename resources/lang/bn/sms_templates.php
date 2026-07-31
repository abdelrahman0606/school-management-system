<?php

/*
|--------------------------------------------------------------------------
| Sms Module Language Lines (Bangla)
|--------------------------------------------------------------------------
| Mirrors resources/lang/en/sms_templates.php. Keep the :student, :currency,
| :amount placeholders intact — SendSmsBatchJob fills them in per recipient.
| Renamed from sms.php — see that file's docblock for why (group-name
| collision with the bare __('SMS') translation key used across the admin
| UI, which crashed sidebar.blade.php's rendering under backend_locale=bn).
*/

return [
    'due_reminder' => 'প্রিয় অভিভাবক, :student এর বকেয়া ফি :currency :amount। অনুগ্রহ করে যত দ্রুত সম্ভব বকেয়া পরিশোধ করুন। ধন্যবাদ।',
];
