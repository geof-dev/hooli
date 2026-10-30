<?php

namespace App;

use Anthropic\Client;
use GuzzleHttp\Client as Guzzle;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Imagick;

/**
 * Reads the photo of a product with Claude, whichever side it shows, and anything at all, a frying pan too: what the
 * product is, what it is made of, and what each ingredient may do to hormones, for the phone to score. The photo is
 * held in memory for the read and dropped with the request: it is never saved, and it leaves without its metadata,
 * such as where it was taken.
 */
final class LabelReader
{
    // The most Claude takes in on the long edge: a bigger photo would be shrunk on arrival anyway.
    private const LONG_EDGE = 2576;

    // The languages the app speaks, which Claude writes its notes in.
    public const LANGUAGES = ['en' => 'English', 'et' => 'Estonian', 'ru' => 'Russian'];

    // What the app scores by: the hormones it shows, its three levels, the evidence it names, and the situations and
    // medicines of its profile that can make an ingredient matter more.
    private const HORMONES = ['oestrogen', 'progesterone', 'testosterone', 'thyroid', 'cortisol', 'insulin'];

    private const LEVELS = ['fine', 'know', 'swap'];

    private const EVIDENCE = ['lab', 'animal', 'human'];

    private const FLAGS = ['thyroid', 'meningioma', 'endometriosis', 'pcos', 'pregnancy', 'menopause', 'levothyroxine', 'contraceptive', 'progestin', 'hrt'];

    private const INSTRUCTIONS = <<<'TEXT'
        You read photos for Hooli, an app that helps women see what in the products they use may affect their hormones. From one photo, you say what the product is, what it is made of, and what each ingredient may do to hormones. The app turns your answer into a score from 0 to 100 and a page the person reads, so be accurate and calm: Hooli informs, it never frightens.

        What the photo shows, the view:
        - ingredients: you can read the product's ingredient list.
        - medicine: a medicine box, blister or leaflet whose active substances you can read.
        - front: you can tell what the product is, but can't read its ingredients. The front of a pack, or an object with no list at all, such as a frying pan, a water bottle or a pair of leggings.
        - unreadable: you can't even tell what the product is.

        Labels can be in any language, and cosmetics list their ingredients by their INCI names. When the list is printed in several languages, read the most complete one.

        The fields:
        - name: the product's name as printed, without the brand. If none can be read, a short plain description, such as "Non-stick frying pan".
        - brand: as printed, or null.
        - kind and category: what the product is. A medicine is of kind and category medicine. Pans, food containers and kitchen utensils are kitchenware, of kind household.
        - ingredients: one entry per ingredient, or per material for an object. For a list you can read, every ingredient in the label's order; for a medicine, its active substances first, then the other ingredients the photo shows. For a front, what this product contains as far as you know it, or else what such a product usually contains or is made of (a non-stick pan: its coating, the metal of its body, its handle). Leave out percentages, footnote marks and headings such as "Ingredients:". For each:
          - name: its INCI name when it has one (Aqua, Glycerin, Retinyl Palmitate), an active substance its INN (Ibuprofen), anything else its English name (sugar, wheat flour, PTFE non-stick coating). A food additive may keep its E number (E211).
          - role: what it does in this product, in two to five words (Moisturiser, Preservative, Painkiller, Non-stick coating).
          - level: fine, know or swap, as below.
          - hormones: each hormone it has been shown to affect, with the strongest evidence of it: human for studies of people, animal, or lab for cells. Empty when there is none.
          - why: for know and swap, one or two plain sentences: what it may do to which hormone, and how much that matters at the amounts in this kind of product. Empty for fine.
          - matters_for: the situations, as below, in which it matters more. Empty when there are none.
          - level_for: its level for people in those situations, the same as its level or higher.
        - summary: one or two plain sentences: what in this product matters for hormones, and why. Only when every ingredient is fine, say that nothing stands out.
        - medicine: for a medicine, its active substances by their INN, its strength and form as printed, its family (what kind of medicine it is, such as "Anti-inflammatory painkiller (NSAID)"), and two or three short questions worth asking a doctor or pharmacist about it. For anything else, null.

        The levels:
        - fine: no evidence that it affects any of these hormones, such as water, salt, plain oils, meat, vegetables or most thickeners.
        - know: worth knowing. Evidence that it affects a hormone, in the lab, in animals, or in people at higher amounts than this product holds; a substance regulators are reviewing for its effect on hormones; or an effect on hormones that is the point of the product, such as a hormonal contraceptive or a thyroid hormone. For example: nitrites and nitrates (E249 to E252) for thyroid, as nitrate competes with iodine in the thyroid; added sugars, syrups, dextrose, maltodextrin and refined flours for insulin, as they raise blood sugar, and more for pcos; soy isoflavones for oestrogen; liquorice for cortisol.
        - swap: better to swap. Good evidence that it disrupts hormones at the amounts people are exposed to, such as the substances the EU lists or restricts as endocrine disruptors, when easy alternatives exist. A medicine's active substance is never swap: it is prescribed for a reason, so it is know at most, to talk over with a doctor.

        Judge by the evidence, as bodies such as the EU's SCCS, EFSA and ECHA and the WHO assess it, and for a medicine by its package leaflet: not by the internet's fears. But don't wave real evidence away: an ingredient shown to affect a hormone, in people, in animals or in the lab, is know, with that evidence named, even when this product holds little of it. Say in why how much the amount matters; the level stays know. Food additives count like any other ingredient: preservatives, stabilisers, sweeteners and colours have been studied for their effects on hormones too.

        The situations: thyroid (a thyroid condition), meningioma, endometriosis, pcos, pregnancy (pregnant or trying to be), menopause, levothyroxine (taking thyroid hormone), contraceptive (taking hormonal contraception), progestin (taking a progestin treatment), hrt (taking hormone replacement therapy).

        When you can read a list or a medicine, give only what the photo shows: never add to it from what you know of the product. Write role, why, summary, form, family and questions in the language the message asks for.
        TEXT;

    /**
     * The read: view (ingredients, medicine or front), name, brand, kind, category, ingredients (each with its name,
     * role, level, hormones, why, matters_for and level_for), summary, and medicine (active_substances, strength, form,
     * family, questions) or null. A front's ingredients are what such a product usually holds. Null when the photo
     * shows nothing to read and no product to recognise.
     */
    public static function read(UploadedFile $photo, string $locale): ?array
    {
        $jpeg = self::jpeg($photo);
        $label = config('hooli.models.label');
        $retry = config('hooli.models.retry');

        // The low-cost model reads the photo; one it can't make out, or declines, goes once to the stronger one.
        return self::ask($label, $jpeg, $locale)
            ?? ($retry && $retry !== $label ? self::ask($retry, $jpeg, $locale) : null);
    }

    private static function ask(string $model, string $jpeg, string $locale): ?array
    {
        $message = self::claude()->beta->messages->create(
            maxTokens: 16000,
            model: $model,
            system: self::INSTRUCTIONS,
            messages: [[
                'role' => 'user',
                'content' => [
                    ['type' => 'image', 'source' => ['type' => 'base64', 'mediaType' => 'image/jpeg', 'data' => base64_encode($jpeg)]],
                    ['type' => 'text', 'text' => 'Read this product. Write in '.self::LANGUAGES[$locale].'.'],
                ],
            ]],
            outputConfig: ['effort' => 'low', 'format' => ['type' => 'json_schema', 'schema' => self::schema()]],
        );
        if ($message->stopReason !== 'end_turn') {
            // A refusal, or an answer cut short.
            return null;
        }

        $text = collect($message->content)->firstWhere('type', 'text')->text;
        $read = json_decode($text, true, flags: JSON_THROW_ON_ERROR);

        $read['name'] = Str::limit(trim($read['name']), 255, '');
        $read['brand'] = Str::limit(trim($read['brand'] ?? ''), 255, '') ?: null;
        $read['summary'] = trim($read['summary']);
        $read['ingredients'] = collect($read['ingredients'])
            ->map(fn (array $ingredient) => [...$ingredient, 'name' => Str::limit(trim($ingredient['name']), 255, '')])
            ->filter(fn (array $ingredient) => $ingredient['name'] !== '')
            ->values()
            ->all();
        if ($read['medicine']) {
            $read['medicine']['active_substances'] = collect($read['medicine']['active_substances'])->map(fn (string $name) => trim($name))->filter()->values()->all();
            $read['medicine'] = $read['medicine']['active_substances'] ? $read['medicine'] : null;
        }

        // The view goes by what Claude did give, whatever it called the photo: active substances make a medicine. With
        // no ingredients, even guessed, there is nothing to score.
        $read['view'] = match (true) {
            $read['medicine'] !== null => 'medicine',
            $read['ingredients'] === [] => 'unreadable',
            $read['view'] === 'front' => 'front',
            $read['view'] === 'ingredients' => 'ingredients',
            default => 'unreadable',
        };

        return $read['view'] !== 'unreadable' ? $read : null;
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
        $texts = ['type' => 'array', 'items' => $text];
        $level = ['type' => 'string', 'enum' => self::LEVELS];
        $object = fn (array $properties) => [
            'type' => 'object',
            'properties' => $properties,
            'required' => array_keys($properties),
            'additionalProperties' => false,
        ];

        return $object([
            'view' => ['type' => 'string', 'enum' => ['ingredients', 'medicine', 'front', 'unreadable']],
            'name' => $text,
            'brand' => $textOrNull,
            'kind' => ['type' => 'string', 'enum' => config('hooli.kinds')],
            'category' => ['type' => 'string', 'enum' => config('hooli.categories')],
            'ingredients' => ['type' => 'array', 'items' => $object([
                'name' => $text,
                'role' => $text,
                'level' => $level,
                'hormones' => ['type' => 'array', 'items' => $object([
                    'hormone' => ['type' => 'string', 'enum' => self::HORMONES],
                    'evidence' => ['type' => 'string', 'enum' => self::EVIDENCE],
                ])],
                'why' => $text,
                'matters_for' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => self::FLAGS]],
                'level_for' => $level,
            ])],
            'summary' => $text,
            'medicine' => ['anyOf' => [
                $object([
                    'active_substances' => $texts,
                    'strength' => $textOrNull,
                    'form' => $textOrNull,
                    'family' => $text,
                    'questions' => $texts,
                ]),
                ['type' => 'null'],
            ]],
        ]);
    }
}
