<?php

/*
|--------------------------------------------------------------------------
| LMS AI assignment checker settings
|--------------------------------------------------------------------------
| AnthropicAiChecker calls the real Anthropic Messages API using each
| school's own encrypted lms_ai_api_key (schools.lms_ai_api_key) — there is
| no platform-level fallback key, matching the per-school-credential
| convention already established by Sms/Payment.
*/

return [

    // 'anthropic' (default, paid, per-school API key) | 'self_hosted' (free,
    // unlimited, but needs the ai-detector Docker service — see
    // docker-compose.yml and services/ai-detector/. Won't run on plain
    // shared cPanel hosting; see docs/cpanel-deployment.md).
    'ai_provider' => env('LMS_AI_PROVIDER', 'anthropic'),

    'ai_api_base' => env('LMS_AI_API_BASE', 'https://api.anthropic.com/v1/messages'),

    'ai_api_version' => env('LMS_AI_API_VERSION', '2023-06-01'),

    'ai_model' => env('LMS_AI_MODEL', 'claude-3-5-haiku-latest'),

    'ai_timeout_seconds' => env('LMS_AI_TIMEOUT_SECONDS', 30),

    // Content sent to the model is truncated to this many characters —
    // keeps prompt size (and cost/latency) bounded for large submissions.
    'ai_max_content_chars' => env('LMS_AI_MAX_CONTENT_CHARS', 8000),

    // Only used when ai_provider=self_hosted — the ai-detector container's
    // internal address (its docker-compose service name) and the shared
    // secret it was started with (AI_DETECTOR_SHARED_SECRET in its own
    // environment — keep both values in sync).
    'ai_self_hosted_url' => env('LMS_AI_SELF_HOSTED_URL', 'http://ai-detector:8000'),

    'ai_self_hosted_secret' => env('LMS_AI_SELF_HOSTED_SECRET', ''),

];
