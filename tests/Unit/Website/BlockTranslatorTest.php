<?php

namespace Tests\Unit\Website;

use App\Modules\Language\Gateways\TranslationGatewayContract;
use App\Modules\Website\Services\BlockTranslator;
use Tests\TestCase;

/**
 * Schema-driven field mapping — asserts translatable text fields are sent
 * through the gateway and structural values (urls, colors, icons, alignment)
 * never are, plus recursion into container/grid children. A fake gateway,
 * never a real network call. docs/modules/30-multilingual-content-plan.md
 * Phase 5.
 */
class BlockTranslatorTest extends TestCase
{
    private function fakeGateway(): TranslationGatewayContract
    {
        return new class implements TranslationGatewayContract
        {
            /** @var array<int, array{0: string, 1: string, 2: string}> */
            public array $calls = [];

            public function translate(string $text, string $sourceLocale, string $targetLocale): string
            {
                $this->calls[] = [$text, $sourceLocale, $targetLocale];

                return "TR:{$text}";
            }
        };
    }

    public function test_translates_hero_text_fields_but_not_its_url_or_image(): void
    {
        $gateway = $this->fakeGateway();
        $translator = new BlockTranslator($gateway);

        $layout = ['blocks' => [
            ['type' => 'hero', 'data' => [
                'title' => 'Welcome', 'subtitle' => 'To our school', 'button_text' => 'Apply now',
                'button_url' => '/admission', 'image' => '/uploads/hero.jpg',
            ], 'style' => ['bg_color' => '#fff'], 'layout' => []],
        ]];

        $out = $translator->translateLayout($layout, 'en', 'bn');
        $data = $out['blocks'][0]['data'];

        $this->assertSame('TR:Welcome', $data['title']);
        $this->assertSame('TR:To our school', $data['subtitle']);
        $this->assertSame('TR:Apply now', $data['button_text']);
        // Structural values pass through completely untouched.
        $this->assertSame('/admission', $data['button_url']);
        $this->assertSame('/uploads/hero.jpg', $data['image']);
        $this->assertSame(['bg_color' => '#fff'], $out['blocks'][0]['style']);
    }

    public function test_skips_block_types_with_no_translatable_text(): void
    {
        $gateway = $this->fakeGateway();
        $translator = new BlockTranslator($gateway);

        $layout = ['blocks' => [
            ['type' => 'divider', 'data' => ['line_style' => 'solid', 'width_pct' => 80], 'style' => [], 'layout' => []],
            ['type' => 'spacer', 'data' => ['height' => 40], 'style' => [], 'layout' => []],
            ['type' => 'icon', 'data' => ['icon' => 'bi-star', 'color' => '#000', 'url' => '/about'], 'style' => [], 'layout' => []],
        ]];

        $out = $translator->translateLayout($layout, 'en', 'bn');

        $this->assertSame($layout['blocks'], $out['blocks']);
        $this->assertSame([], $gateway->calls);
    }

    public function test_translates_faq_items_question_and_answer(): void
    {
        $gateway = $this->fakeGateway();
        $translator = new BlockTranslator($gateway);

        $layout = ['blocks' => [
            ['type' => 'faq', 'data' => ['heading' => 'FAQs', 'faq_items' => [
                ['question' => 'What are the fees?', 'answer' => 'See fee schedule.'],
            ]], 'style' => [], 'layout' => []],
        ]];

        $data = $translator->translateLayout($layout, 'en', 'bn')['blocks'][0]['data'];

        $this->assertSame('TR:FAQs', $data['heading']);
        $this->assertSame('TR:What are the fees?', $data['faq_items'][0]['question']);
        $this->assertSame('TR:See fee schedule.', $data['faq_items'][0]['answer']);
    }

    public function test_translates_stats_item_labels_but_not_values(): void
    {
        $gateway = $this->fakeGateway();
        $translator = new BlockTranslator($gateway);

        $layout = ['blocks' => [
            ['type' => 'stats', 'data' => ['items' => [
                ['label' => 'Pass rate', 'value' => '98%'],
            ]], 'style' => [], 'layout' => []],
        ]];

        $data = $translator->translateLayout($layout, 'en', 'bn')['blocks'][0]['data'];

        $this->assertSame('TR:Pass rate', $data['items'][0]['label']);
        $this->assertSame('98%', $data['items'][0]['value']);
    }

    public function test_recurses_into_container_and_grid_children(): void
    {
        $gateway = $this->fakeGateway();
        $translator = new BlockTranslator($gateway);

        $layout = ['blocks' => [
            ['type' => 'container', 'data' => ['blocks' => [
                ['type' => 'heading', 'data' => ['text' => 'Nested heading'], 'style' => [], 'layout' => []],
                ['type' => 'grid', 'data' => ['blocks' => [
                    ['type' => 'button', 'data' => ['text' => 'Click me', 'url' => '/x'], 'style' => [], 'layout' => []],
                ]], 'style' => [], 'layout' => []],
            ]], 'style' => [], 'layout' => []],
        ]];

        $out = $translator->translateLayout($layout, 'en', 'bn');
        $inner = $out['blocks'][0]['data']['blocks'];

        $this->assertSame('TR:Nested heading', $inner[0]['data']['text']);
        $grandchild = $inner[1]['data']['blocks'][0]['data'];
        $this->assertSame('TR:Click me', $grandchild['text']);
        $this->assertSame('/x', $grandchild['url']);
    }

    public function test_translates_sidebar_blocks_too(): void
    {
        $gateway = $this->fakeGateway();
        $translator = new BlockTranslator($gateway);

        $layout = [
            'blocks' => [],
            'sidebar' => [
                ['type' => 'quick_links', 'data' => ['heading' => 'Links', 'links' => [
                    ['label' => 'Admissions', 'url' => '/admissions'],
                ]], 'style' => [], 'layout' => []],
            ],
        ];

        $data = $translator->translateLayout($layout, 'en', 'bn')['sidebar'][0]['data'];

        $this->assertSame('TR:Links', $data['heading']);
        $this->assertSame('TR:Admissions', $data['links'][0]['label']);
        $this->assertSame('/admissions', $data['links'][0]['url']);
    }

    public function test_blank_and_missing_fields_are_left_alone_without_calling_the_gateway(): void
    {
        $gateway = $this->fakeGateway();
        $translator = new BlockTranslator($gateway);

        $layout = ['blocks' => [
            ['type' => 'hero', 'data' => ['title' => '', 'subtitle' => '   '], 'style' => [], 'layout' => []],
            ['type' => 'heading', 'data' => [], 'style' => [], 'layout' => []],
        ]];

        $out = $translator->translateLayout($layout, 'en', 'bn');

        $this->assertSame('', $out['blocks'][0]['data']['title']);
        $this->assertSame('   ', $out['blocks'][0]['data']['subtitle']);
        $this->assertArrayNotHasKey('text', $out['blocks'][1]['data']);
        $this->assertSame([], $gateway->calls);
    }
}
