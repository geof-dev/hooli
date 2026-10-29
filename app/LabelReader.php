<?php

namespace App;

use Anthropic\Client;
use GuzzleHttp\Client as Guzzle;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Imagick;

/**
 * Reads the photo of a label with Claude: what the product is, its ingredients in the order of the label, and for a
 * medicine its active substances. The photo is held in memory for the read and dropped with the request: it is
 * never saved, and it leaves without its metadata, such as where it was taken.
 */
final class LabelReader
{
    // Claude Opus 5.5 reads labels accurately even at low effort, where it answers fastest.
    private const MODEL = 'claude-opus-5-5';

    // The most Claude takes in on the long edge: a bigger photo would be shrunk on arrival anyway.
    private const LONG_EDGE = 2576;

    private const INSTRUCTIONS = <<<'TEXT'
        You read photos of product labels for Hooli, an app that tells women what in a product may affect their hormones.

        The photos are taken in Estonia: a label may be in Estonian, Russian, Latvian, Finnish or English, and cosmetics list their ingredients by their Latin INCI names. When the ingredients are printed in several languages, read the most complete list.

        - name: the product's name as printed, without the brand. If you can't read one, a short description in English, such as "Face cream".
        - brand: as printed, or null.
        - kind and category: what the product is. A medicine is of kind and category medicine.
        - ingredients: every ingredient, in the order of the label, one per entry. Write each by its INCI name when it has one (Aqua, Glycerin, Retinyl Palmitate), otherwise by its English name (sugar, wheat flour); food additives may keep their E number (E211). Leave out percentages, footnote marks and headings such as "Ingredients:".
        - medicine: only for a medicine box or leaflet, its active substances by their English INN (Levonorgestrel, Ethinylestradiol), its strength as printed and its form in English (film-coated tablets). For a medicine, the ingredients are its other ingredients, if the photo shows them. For anything else, null.

        Read only what the photo shows: never complete a list from what you know of the product. If the photo shows no ingredients you can read and no medicine, give an empty ingredients list and a null medicine.
        TEXT;

    /**
     * The read: name, brand, kind, category, ingredients, and medicine (active_substances, strength, form) or null.
     * Null itself when the photo shows no ingredients and no medicine.
     */
    public static function read(UploadedFile $photo): ?array
    {
        $message = self::claude()->beta->messages->create(
            maxTokens: 16000,
            model: self::MODEL,
            system: self::INSTRUCTIONS,
            messages: [[
                'role' => 'user',
                'content' => [
                    ['type' => 'image', 'source' => ['type' => 'base64', 'mediaType' => 'image/jpeg', 'data' => base64_encode(self::jpeg($photo))]],
                    ['type' => 'text', 'text' => 'Read this label.'],
                ],
            ]],
            outputConfig: ['effort' => 'low', 'format' => ['type' => 'json_schema', 'schema' => self::schema()]],
            // Should Claude decline, the API hands the read to the fallback model it picks.
            fallbacks: 'default',
            betas: ['server-side-fallback-2026-07-01'],
        );
        if ($message->stopReason === 'refusal') {
            return null;
        }

        $text = collect($message->content)->firstWhere('type', 'text')->text;
        $read = json_decode($text, true, flags: JSON_THROW_ON_ERROR);

        $read['name'] = Str::limit(trim($read['name']), 255, '');
        $read['brand'] = Str::limit(trim($read['brand'] ?? ''), 255, '') ?: null;
        $read['ingredients'] = self::clean($read['ingredients']);
        if ($read['medicine']) {
            $read['medicine']['active_substances'] = self::clean($read['medicine']['active_substances']);
            $read['medicine'] = $read['medicine']['active_substances'] ? $read['medicine'] : null;
        }

        return $read['ingredients'] || $read['medicine'] ? $read : null;
    }

    private static function claude(): Client
    {
        return new Client(
            apiKey: (string) config('services.anthropic.key'),
            requestOptions: [
                // Through Laravel's HTTP stack, so that Http::fake() stands in for Claude in the tests.
                'transporter' => new Guzzle(['handler' => Http::buildHandlerStack(), 'connect_timeout' => 5, 'timeout' => 45]),
                'maxRetries' => 1,
            ],
        );
    }

    // Claude reads JPEG, PNG, GIF and WebP, so every photo goes as a JPEG, an iPhone's HEIC too: upright, no bigger
    // than Claude reads, and stripped of its metadata.
    private static function jpeg(UploadedFile $photo): string
    {
        $image = new Imagick;
        $image->readImageBlob($photo->get());
        $image->autoOrient();
        $image->stripImage();
        if (max($image->getImageWidth(), $image->getImageHeight()) > self::LONG_EDGE) {
            $image->thumbnailImage(self::LONG_EDGE, self::LONG_EDGE, true);
        }
        $image->setImageFormat('jpeg');
        $image->setImageCompressionQuality(85);

        return $image->getImageBlob();
    }

    private static function schema(): array
    {
        $text = ['type' => 'string'];
        $textOrNull = ['anyOf' => [$text, ['type' => 'null']]];

        return [
            'type' => 'object',
            'properties' => [
                'name' => $text,
                'brand' => $textOrNull,
                'kind' => ['type' => 'string', 'enum' => config('hooli.kinds')],
                'category' => ['type' => 'string', 'enum' => config('hooli.categories')],
                'ingredients' => ['type' => 'array', 'items' => $text],
                'medicine' => ['anyOf' => [
                    [
                        'type' => 'object',
                        'properties' => [
                            'active_substances' => ['type' => 'array', 'items' => $text],
                            'strength' => $textOrNull,
                            'form' => $textOrNull,
                        ],
                        'required' => ['active_substances', 'strength', 'form'],
                        'additionalProperties' => false,
                    ],
                    ['type' => 'null'],
                ]],
            ],
            'required' => ['name', 'brand', 'kind', 'category', 'ingredients', 'medicine'],
            'additionalProperties' => false,
        ];
    }

    /** @return list<string> */
    private static function clean(array $names): array
    {
        return collect($names)->map(fn (string $name) => trim($name))->filter()->values()->all();
    }
}
