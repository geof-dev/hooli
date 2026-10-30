<?php

namespace App;

use App\Models\Product;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * The open databases of products, asked in turn about a barcode we don't have: Open Beauty Facts, Open Food Facts,
 * Open Products Facts. A product they know counts only with a name to show and its ingredient list, the one thing the
 * app needs; it comes with what else they know of it, for the phone to score it: its types, the countries it is sold
 * in, its nutrition, processing and additives.
 */
final class OpenFacts
{
    // Each database, by the source its products keep, with the kind of products it holds.
    private const DATABASES = [
        'openbeautyfacts' => ['url' => 'https://world.openbeautyfacts.org', 'kind' => 'cosmetic'],
        'openfoodfacts' => ['url' => 'https://world.openfoodfacts.org', 'kind' => 'food'],
        'openproductsfacts' => ['url' => 'https://world.openproductsfacts.org', 'kind' => 'household'],
    ];

    // Our categories, for each kind, by the databases' own. A word is a whole category of theirs (en:beverages) or
    // the start of a word in one (face finds en:face-creams). The first that matches wins, so the narrower come first:
    // a sun cream for the face is sun care, a baby shampoo baby care. Food goes by whole categories, because each of
    // its products also carries the broad ones above it, like en:plant-based-foods-and-beverages.
    private const CATEGORIES = [
        'cosmetic' => [
            'sun-care' => ['sun', 'spf', 'tanning'],
            'baby-care' => ['baby', 'babies', 'infant'],
            'intimate-care' => ['intimate', 'feminine', 'menstrual', 'tampon', 'sanitary'],
            'deodorant' => ['deodorant', 'antiperspirant'],
            'oral-care' => ['tooth', 'dental', 'oral', 'mouthwash'],
            'fragrance' => ['perfume', 'fragrance', 'cologne', 'toilette'],
            'makeup' => ['make', 'lipstick', 'gloss', 'mascara', 'foundation', 'eyeliner', 'eyeshadow', 'concealer', 'blush', 'nail'],
            'hair-care' => ['hair', 'shampoo', 'conditioner'],
            'face-care' => ['face', 'facial', 'eye', 'serum', 'micellar'],
            'body-care' => ['body', 'shower', 'bath', 'soap', 'hand', 'lotion', 'moistur'],
        ],
        'food' => [
            'supplement' => ['en:dietary-supplements', 'en:food-supplements', 'en:vitamins'],
            'drink' => ['en:beverages'],
        ],
        'household' => [
            'laundry' => ['laundry', 'fabric'],
            'cleaning' => ['clean', 'dish', 'detergent', 'toilet', 'surface', 'bleach', 'disinfect'],
        ],
    ];

    // Countries, by the databases' tags, with their ISO 3166-1 codes: the EU's, its neighbours' and a few more. One
    // that isn't here is left out. The United Kingdom is UK to them, GB to ISO.
    public const COUNTRIES = [
        'en:austria' => 'at', 'en:belgium' => 'be', 'en:bulgaria' => 'bg', 'en:croatia' => 'hr', 'en:cyprus' => 'cy',
        'en:czech-republic' => 'cz', 'en:denmark' => 'dk', 'en:estonia' => 'ee', 'en:finland' => 'fi',
        'en:france' => 'fr', 'en:germany' => 'de', 'en:greece' => 'gr', 'en:hungary' => 'hu', 'en:ireland' => 'ie',
        'en:italy' => 'it', 'en:latvia' => 'lv', 'en:lithuania' => 'lt', 'en:luxembourg' => 'lu', 'en:malta' => 'mt',
        'en:netherlands' => 'nl', 'en:poland' => 'pl', 'en:portugal' => 'pt', 'en:romania' => 'ro',
        'en:slovakia' => 'sk', 'en:slovenia' => 'si', 'en:spain' => 'es', 'en:sweden' => 'se',
        'en:norway' => 'no', 'en:iceland' => 'is', 'en:liechtenstein' => 'li', 'en:switzerland' => 'ch',
        'en:united-kingdom' => 'gb', 'en:ukraine' => 'ua', 'en:moldova' => 'md', 'en:belarus' => 'by',
        'en:russia' => 'ru', 'en:georgia' => 'ge', 'en:serbia' => 'rs', 'en:bosnia-and-herzegovina' => 'ba',
        'en:montenegro' => 'me', 'en:north-macedonia' => 'mk', 'en:albania' => 'al', 'en:turkey' => 'tr',
        'en:israel' => 'il', 'en:morocco' => 'ma', 'en:algeria' => 'dz', 'en:tunisia' => 'tn',
        'en:united-states' => 'us', 'en:canada' => 'ca', 'en:mexico' => 'mx', 'en:brazil' => 'br',
        'en:australia' => 'au', 'en:new-zealand' => 'nz', 'en:japan' => 'jp', 'en:china' => 'cn', 'en:india' => 'in',
        'en:south-africa' => 'za',
    ];

    // Our names for what they give per 100 g or ml.
    private const NUTRIENTS = [
        'energy_kcal' => 'energy-kcal', 'carbohydrates' => 'carbohydrates', 'sugars' => 'sugars', 'fibre' => 'fiber',
        'fat' => 'fat', 'saturated_fat' => 'saturated-fat', 'proteins' => 'proteins', 'salt' => 'salt',
    ];

    /** The product with this barcode, not yet saved, or null when no database knows it with its ingredients. */
    public static function find(string $barcode): ?Product
    {
        foreach (self::DATABASES as $source => $database) {
            try {
                $response = self::http()->get("{$database['url']}/api/v2/product/$barcode.json", [
                    'fields' => 'product_name,product_name_en,brands,categories_tags,countries_tags,ingredients_text_en,ingredients_text,ingredients,additives_tags,nutriments,nova_group,image_front_url,image_url',
                ]);
            } catch (ConnectionException) {
                // One database down or slow is no reason not to ask the next.
                continue;
            }
            if (! $response->successful() || $response->json('status') !== 1) {
                continue;
            }

            $product = self::product($barcode, $source, $response->json('product') ?? []);
            if ($product) {
                return $product;
            }
        }

        return null;
    }

    /** Their API asks callers to say who they are. Each database gets a few seconds. */
    public static function http(): PendingRequest
    {
        return Http::withUserAgent('Hooli/1.0 ('.config('hooli.contact').')')->connectTimeout(2)->timeout(4);
    }

    /** Ours, not yet saved, from what a database knows of it, when that's a name to show and the ingredient list. */
    public static function product(string $barcode, string $source, array $found): ?Product
    {
        $name = trim($found['product_name'] ?? '') ?: trim($found['product_name_en'] ?? '');
        [$ingredients, $ids] = self::ingredients($found);
        if ($name === '' || $ingredients === []) {
            return null;
        }
        $kind = self::DATABASES[$source]['kind'];

        return new Product([
            'barcode' => $barcode,
            'name' => Str::limit($name, 255, ''),
            'brand' => Str::limit(trim(Str::before($found['brands'] ?? '', ',')), 255, '') ?: null,
            'kind' => $kind,
            'category' => self::category($kind, $found['categories_tags'] ?? []),
            'types' => self::tags($found['categories_tags'] ?? null),
            'countries' => self::countries($found['countries_tags'] ?? null),
            'nutrition' => self::nutrition($found['nutriments'] ?? null),
            'processing' => in_array($found['nova_group'] ?? null, [1, 2, 3, 4]) ? (int) $found['nova_group'] : null,
            'ingredients' => $ingredients,
            'ingredient_ids' => $ids,
            'additives' => self::tags($found['additives_tags'] ?? null),
            'image_url' => $found['image_front_url'] ?? $found['image_url'] ?? null,
            'source' => $source,
        ]);
    }

    /**
     * The ingredient list, and the databases' ids of its ingredients in the same order: from their reading of the list
     * when they have one, in the language of the label, or else the list in English if they have it, without ids.
     *
     * @return array{list<string>, list<?string>}
     */
    private static function ingredients(array $found): array
    {
        if (is_array($found['ingredients'] ?? null) && $found['ingredients'] !== []) {
            // An id their taxonomy doesn't know is only the words of the label (en:shave-grass).
            $read = collect($found['ingredients'])->filter(fn ($ingredient) => is_array($ingredient))->map(fn (array $ingredient) => [
                self::clean(self::text($ingredient)),
                is_string($ingredient['id'] ?? null) && ($ingredient['is_in_taxonomy'] ?? 1) ? $ingredient['id'] : null,
            ])->filter(fn (array $pair) => $pair[0] !== '')->values();

            return [$read->pluck(0)->all(), $read->pluck(1)->all()];
        }

        // Drops a heading (Ingredients:), and splits at commas outside brackets: Parfum (Limonene, Linalool) is one.
        $text = trim($found['ingredients_text_en'] ?? '') ?: trim($found['ingredients_text'] ?? '');
        $names = collect(preg_split('/[,;\n](?![^(\[]*[)\]])/u', preg_replace('/^[^,:(]{0,40}:\s*/u', '', $text)))
            ->map(fn (string $name) => self::clean($name))
            ->filter()
            ->values()
            ->all();

        return [$names, array_fill(0, count($names), null)];
    }

    // An ingredient as the label gives it, with those it is made of in brackets: Parfum (Limonene, Linalool).
    private static function text(array $ingredient): string
    {
        $parts = collect($ingredient['ingredients'] ?? [])->filter(fn ($part) => is_array($part))->map(fn (array $part) => self::text($part))->filter();
        $text = trim((string) ($ingredient['text'] ?? ''));

        return $parts->isNotEmpty() ? "$text ({$parts->implode(', ')})" : $text;
    }

    // Underscores mark allergens in Open Food Facts (_milk_); stars and full stops are footnotes and ends.
    private static function clean(string $name): string
    {
        return preg_replace('/^[\s*.•]+|[\s*.•]+$/u', '', str_replace('_', '', $name));
    }

    /** @return ?array<string, ?float> per 100 g or ml, null when they give none of it */
    private static function nutrition(mixed $nutriments): ?array
    {
        $nutrition = array_map(
            fn (string $theirs) => is_numeric($nutriments["{$theirs}_100g"] ?? null) ? round((float) $nutriments["{$theirs}_100g"], 2) : null,
            self::NUTRIENTS,
        );

        return array_filter($nutrition, fn (?float $value) => $value !== null) ? $nutrition : null;
    }

    /** @return list<string> the ISO codes of the countries they say it is sold in */
    private static function countries(mixed $tags): array
    {
        return collect(self::tags($tags))->map(fn (string $tag) => self::COUNTRIES[$tag] ?? null)->filter()->unique()->values()->all();
    }

    /** @return list<string> */
    private static function tags(mixed $tags): array
    {
        return is_array($tags) ? array_values(array_filter($tags, 'is_string')) : [];
    }

    private static function category(string $kind, array $tags): string
    {
        $tags = new Collection($tags);
        $words = $tags->flatMap(fn (string $tag) => explode('-', Str::after($tag, ':')));

        foreach (self::CATEGORIES[$kind] as $category => $matches) {
            if ($tags->intersect($matches)->isNotEmpty() || $words->contains(fn (string $word) => Str::startsWith($word, $matches))) {
                return $category;
            }
        }

        return $kind === 'food' ? 'food' : 'other';
    }
}
