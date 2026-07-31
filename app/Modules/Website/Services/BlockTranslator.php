<?php

namespace App\Modules\Website\Services;

use App\Modules\Language\Gateways\TranslationGatewayContract;
use Throwable;

/**
 * Walks a page's stored layout_json ({template, blocks: [{type,data,style,
 * layout}], sidebar: [...]} — see PageRenderService's docblock for the
 * authoritative shape) and returns a translated copy. docs/modules/
 * 30-multilingual-content-plan.md Phase 5.
 *
 * Schema-driven, not a generic "translate every string" heuristic: each
 * block type declares exactly which of its own `data` keys are free text,
 * so structural values (colors, urls, icon names, alignment, numeric
 * settings) are never sent through translation. The field list mirrors
 * public/blocks/render.blade.php and public/sidebar/render.blade.php —
 * a new block type's translatable fields need a matching entry here, the
 * same way it needs a @case in those two partials.
 */
class BlockTranslator
{
    /** Plain scalar text fields, keyed by block type. */
    private const TEXT_FIELDS = [
        'hero' => ['title', 'subtitle', 'button_text'],
        'heading' => ['text'],
        'richtext' => ['heading', 'html'],
        'image' => ['caption'],
        'video' => ['heading', 'caption'],
        'button' => ['text'],
        'image_text' => ['heading', 'html'],
        'staff' => ['heading'],
        'notices' => ['heading'],
        'gallery_photo' => ['heading'],
        'gallery_video' => ['heading'],
        'contact' => ['heading'],
        'announcement_bar' => ['text', 'link_text'],
        'admission_form' => ['heading', 'intro'],
        'faq' => ['heading'],
        // Sidebar-only types (public/sidebar/render.blade.php).
        'quick_links' => ['heading'],
        'office_hours' => ['heading'],
        'contact_info' => ['heading'],
        'recent_notices' => ['heading'],
    ];

    /** Block types whose `data` holds a nested list of child blocks to recurse into. */
    private const CONTAINER_TYPES = ['container', 'grid'];

    public function __construct(private readonly TranslationGatewayContract $gateway) {}

    /**
     * @param  array<string, mixed>  $layoutJson  Raw stored layout_json.
     * @return array<string, mixed>
     */
    public function translateLayout(array $layoutJson, string $sourceLocale, string $targetLocale): array
    {
        if (is_array($layoutJson['blocks'] ?? null)) {
            $layoutJson['blocks'] = $this->translateBlocks($layoutJson['blocks'], $sourceLocale, $targetLocale);
        }
        if (is_array($layoutJson['sidebar'] ?? null)) {
            $layoutJson['sidebar'] = $this->translateBlocks($layoutJson['sidebar'], $sourceLocale, $targetLocale);
        }

        return $layoutJson;
    }

    /**
     * @param  array<int, mixed>  $blocks
     * @return array<int, mixed>
     */
    private function translateBlocks(array $blocks, string $sourceLocale, string $targetLocale): array
    {
        return array_map(
            fn ($block) => is_array($block) ? $this->translateBlock($block, $sourceLocale, $targetLocale) : $block,
            $blocks,
        );
    }

    /**
     * @param  array<string, mixed>  $block
     * @return array<string, mixed>
     */
    private function translateBlock(array $block, string $sourceLocale, string $targetLocale): array
    {
        $type = $block['type'] ?? null;
        $data = $block['data'] ?? [];

        if (! is_string($type) || ! is_array($data)) {
            return $block;
        }

        foreach (self::TEXT_FIELDS[$type] ?? [] as $field) {
            // array_key_exists, not isset/??: only touch a field the block
            // actually has -- never introduce a new `field => null` key into
            // stored layout_json for a field this block instance never set.
            if (array_key_exists($field, $data)) {
                $data[$field] = $this->translateField($data[$field], $sourceLocale, $targetLocale);
            }
        }

        if ($type === 'faq' && is_array($data['faq_items'] ?? null)) {
            $data['faq_items'] = array_map(function ($item) use ($sourceLocale, $targetLocale) {
                if (! is_array($item)) {
                    return $item;
                }
                $item['question'] = $this->translateField($item['question'] ?? null, $sourceLocale, $targetLocale);
                $item['answer'] = $this->translateField($item['answer'] ?? null, $sourceLocale, $targetLocale);

                return $item;
            }, $data['faq_items']);
        }

        if ($type === 'stats' && is_array($data['items'] ?? null)) {
            $data['items'] = array_map(function ($item) use ($sourceLocale, $targetLocale) {
                if (! is_array($item)) {
                    return $item;
                }
                $item['label'] = $this->translateField($item['label'] ?? null, $sourceLocale, $targetLocale);

                return $item;
            }, $data['items']);
        }

        if ($type === 'quick_links' && is_array($data['links'] ?? null)) {
            $data['links'] = array_map(function ($link) use ($sourceLocale, $targetLocale) {
                if (! is_array($link)) {
                    return $link;
                }
                $link['label'] = $this->translateField($link['label'] ?? null, $sourceLocale, $targetLocale);

                return $link;
            }, $data['links']);
        }

        if ($type === 'office_hours' && is_array($data['lines'] ?? null)) {
            $data['lines'] = array_map(function ($line) use ($sourceLocale, $targetLocale) {
                if (! is_array($line)) {
                    // A bare string line (day name only, no separate value) — the
                    // admin form's simplest shape (see office_hours' own template).
                    return $this->translateField($line, $sourceLocale, $targetLocale);
                }
                $line['label'] = $this->translateField($line['label'] ?? null, $sourceLocale, $targetLocale);
                $line['value'] = $this->translateField($line['value'] ?? null, $sourceLocale, $targetLocale);

                return $line;
            }, $data['lines']);
        }

        if (in_array($type, self::CONTAINER_TYPES, true) && is_array($data['blocks'] ?? null)) {
            $data['blocks'] = $this->translateBlocks($data['blocks'], $sourceLocale, $targetLocale);
        }

        $block['data'] = $data;

        return $block;
    }

    /**
     * Translates a single scalar field, leaving non-strings/blanks untouched.
     * A block tree can carry dozens of these calls; TranslationGatewayContract
     * throws on any failure (a MyMemory rate limit hit partway through a
     * large page), and one field failing must not discard every field
     * translated before it — so this swallows a failure and leaves that one
     * field in the source language rather than letting the whole
     * translateLayout() call abort. The result is still a best-effort DRAFT
     * an admin reviews before saving; an occasional untranslated line is
     * easy to spot and finish by hand, losing the whole draft is not.
     */
    private function translateField(mixed $value, string $sourceLocale, string $targetLocale): mixed
    {
        if (! is_string($value) || trim($value) === '') {
            return $value;
        }

        try {
            return $this->gateway->translate($value, $sourceLocale, $targetLocale);
        } catch (Throwable) {
            return $value;
        }
    }
}
