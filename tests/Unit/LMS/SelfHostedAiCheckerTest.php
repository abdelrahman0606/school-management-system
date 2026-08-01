<?php

namespace Tests\Unit\LMS;

use App\Modules\LMS\Gateways\SelfHostedAiChecker;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class SelfHostedAiCheckerTest extends TestCase
{
    public function test_parses_a_successful_response(): void
    {
        Http::fake([
            'ai-detector:8000/detect' => Http::response([
                'ai_score' => 73,
                'likely_ai_generated' => true,
            ], 200),
        ]);

        $result = (new SelfHostedAiChecker)->check('', 'Some submission content.');

        $this->assertTrue($result->success);
        $this->assertSame(73, $result->aiScore);
        $this->assertTrue($result->likelyAiGenerated);
        // The local model returns a single score, no free-text explanation.
        $this->assertSame('', $result->originalityNote);
    }

    public function test_ai_score_is_clamped_to_0_100(): void
    {
        Http::fake([
            'ai-detector:8000/detect' => Http::response([
                'ai_score' => -15,
                'likely_ai_generated' => false,
            ], 200),
        ]);

        $result = (new SelfHostedAiChecker)->check('', 'content');

        $this->assertSame(0, $result->aiScore);
    }

    public function test_derives_likely_ai_generated_from_score_when_the_service_omits_it(): void
    {
        Http::fake([
            'ai-detector:8000/detect' => Http::response(['ai_score' => 61], 200),
        ]);

        $result = (new SelfHostedAiChecker)->check('', 'content');

        $this->assertTrue($result->likelyAiGenerated);
    }

    public function test_sends_the_shared_secret_header_when_configured(): void
    {
        config(['lms.ai_self_hosted_secret' => 'shh-secret']);
        Http::fake(['ai-detector:8000/detect' => Http::response(['ai_score' => 5], 200)]);

        (new SelfHostedAiChecker)->check('', 'content');

        Http::assertSent(fn ($request) => $request->hasHeader('X-Internal-Secret', 'shh-secret'));
    }

    public function test_throws_on_a_non_successful_response(): void
    {
        Http::fake(['ai-detector:8000/detect' => Http::response('service unavailable', 503)]);

        $this->expectException(RuntimeException::class);

        (new SelfHostedAiChecker)->check('', 'content');
    }

    public function test_throws_on_an_unparseable_response(): void
    {
        Http::fake(['ai-detector:8000/detect' => Http::response(['nope' => true], 200)]);

        $this->expectException(RuntimeException::class);

        (new SelfHostedAiChecker)->check('', 'content');
    }
}
