<?php

namespace App\Modules\LMS\Gateways;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Calls Anthropic's Messages API to assess a submission's likely-AI-generated
 * score. Any exception here (timeout, non-2xx response, unparseable model
 * output) propagates straight out of check() — this class has no retry
 * policy of its own. See AssignmentAiCheckJob's own docblock for what
 * happens to it from there: caught and swallowed by design, not retried
 * (an earlier version let it propagate "for the queue to retry" and it
 * surfaced as a 500 on the submit endpoint under QUEUE_CONNECTION=sync
 * instead — this class's job is just to throw or return cleanly, not to
 * second-guess how the caller handles either outcome).
 */
class AnthropicAiChecker implements AiCheckerContract
{
    public function check(string $apiKey, string $content): AiCheckResult
    {
        $maxChars = (int) config('lms.ai_max_content_chars');
        $truncated = mb_substr($content, 0, $maxChars);

        $prompt = "Assess this student assignment for likely AI generation and academic integrity.\n\n"
            ."Submission content:\n---\n{$truncated}\n---\n\n"
            .'Return ONLY valid JSON in exactly this shape, with no prose and no markdown code '
            .'fences: {"ai_score": <integer 0-100>, "likely_ai_generated": <true|false>, '
            .'"originality_note": "<one or two sentence explanation>"}';

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => config('lms.ai_api_version'),
            'content-type' => 'application/json',
        ])
            ->timeout((int) config('lms.ai_timeout_seconds'))
            ->post(config('lms.ai_api_base'), [
                'model' => config('lms.ai_model'),
                'max_tokens' => 512,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException("Anthropic API error ({$response->status()}): {$response->body()}");
        }

        $text = $response->json('content.0.text');
        $decoded = is_string($text) ? json_decode($text, true) : null;

        if (! is_array($decoded) || ! array_key_exists('ai_score', $decoded)) {
            throw new RuntimeException('Anthropic API returned an unparseable response: '.$response->body());
        }

        // Model output is prompted to stay 0-100 but nothing enforces that
        // contractually — clamp defensively (same as SelfHostedAiChecker).
        $score = max(0, min(100, (int) $decoded['ai_score']));

        return AiCheckResult::success(
            $score,
            (bool) ($decoded['likely_ai_generated'] ?? false),
            (string) ($decoded['originality_note'] ?? ''),
            $response->json(),
        );
    }
}
