<?php

namespace App\Modules\LMS\Gateways;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Calls the self-hosted AI-text-detector service (services/ai-detector/,
 * a FastAPI wrapper around desklib/ai-text-detector-v1.01) instead of the
 * paid Anthropic API — free and unlimited, at the cost of needing its own
 * container (see docker-compose.yml's ai-detector service; won't run on
 * plain shared cPanel hosting the way the rest of this app does).
 *
 * Selected via LMS_AI_PROVIDER=self_hosted (see config/lms.php and
 * AppServiceProvider's AiCheckerContract binding) — the default stays
 * "anthropic" so installs without the extra container keep working exactly
 * as before.
 *
 * $apiKey is accepted (to satisfy AiCheckerContract) but unused — this
 * provider has no per-school credential; it authenticates to the
 * self-hosted service with a single shared secret instead (LMS_AI_
 * SELF_HOSTED_SECRET), the same value the service itself was started with.
 *
 * The underlying model only scores "how AI-generated does this text look",
 * not academic-integrity plagiarism (matching-against-sources) — same
 * scope as AnthropicAiChecker, just a different provider behind the same
 * contract. originality_note is always empty: the local model returns a
 * single score, no free-text explanation to surface.
 */
class SelfHostedAiChecker implements AiCheckerContract
{
    public function check(string $apiKey, string $content): AiCheckResult
    {
        $maxChars = (int) config('lms.ai_max_content_chars');
        $truncated = mb_substr($content, 0, $maxChars);

        $headers = [];
        $secret = config('lms.ai_self_hosted_secret');
        if (! empty($secret)) {
            $headers['X-Internal-Secret'] = $secret;
        }

        $response = Http::withHeaders($headers)
            ->timeout((int) config('lms.ai_timeout_seconds'))
            ->post(rtrim((string) config('lms.ai_self_hosted_url'), '/').'/detect', [
                'text' => $truncated,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException("Self-hosted AI detector error ({$response->status()}): {$response->body()}");
        }

        $decoded = $response->json();

        if (! is_array($decoded) || ! array_key_exists('ai_score', $decoded)) {
            throw new RuntimeException('Self-hosted AI detector returned an unparseable response: '.$response->body());
        }

        $score = max(0, min(100, (int) $decoded['ai_score']));

        return AiCheckResult::success(
            $score,
            (bool) ($decoded['likely_ai_generated'] ?? ($score >= 50)),
            '',
            $decoded,
        );
    }
}
