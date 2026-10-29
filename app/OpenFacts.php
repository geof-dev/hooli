<?php

namespace App;

use App\Models\Product;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * The open databases of products, asked in turn about a barcode we don't have: Open Beauty Facts, Open Food Facts,
 * Open Products Facts. A product they know counts only with a name to show and its ingredient list, the one thing the
 * app needs.
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

    /** The product with this barcode, not yet saved, or null when no database knows it with its ingredients. */
    public static function find(string $barcode): ?Product
    {
        foreach (self::DATABASES as $source => $database) {
            try {
                $response = Http::withUserAgent('Hooli/1.0 ('.config('hooli.contact').')')
                    ->connectTimeout(2)
                    ->timeout(4)
                    ->get("{$database['url']}/api/v2/product/$barcode.json", [
                        'fields' => 'product_name,product_name_en,brands,categories_tags,ingredients_text_en,ingredients_text,ingredients,image_front_url,image_url',
                    ]);
            } catch (ConnectionException) {
                // One database down or slow is no reason not to ask the next.
                continue;
            }
            if (! $response->successful() || $response->json('status') !== 1) {
                continue;
            }

            $found = $response->json('product');
            $name = trim($found['product_name'] ?? '') ?: trim($found['product_name_en'] ?? '');
            $ingredients = self::ingredients($found);
            if ($name === '' || $ingredients === []) {
                continue;
            }

            return new Product([
                'barcode' => $barcode,
                'name' => Str::limit($name, 255, ''),
                'brand' => Str::limit(trim(Str::before($found['brands'] ?? '', ',')), 255, '') ?: null,
                'kind' => $database['kind'],
                'category' => self::category($database['kind'], $found['categories_tags'] ?? []),
                'ingredients' => $ingredients,
                'image_url' => $found['image_front_url'] ?? $found['image_url'] ?? null,
                'source' => $source,
            ]);
        }

        return null;
    }

    /** @return list<string> the ingredient list in English if they have it, as on the label if not */
    private static function ingredients(array $found): array
    {
        $text = trim($found['ingredients_text_en'] ?? '') ?: trim($found['ingredients_text'] ?? '');
        $names = $text !== ''
            // Drops a heading (Ingredients:), and splits at commas outside brackets: Parfum (Limonene, Linalool) is one.
            ? preg_split('/[,;\n](?![^(\[]*[)\]])/u', preg_replace('/^[^,:(]{0,40}:\s*/u', '', $text))
            : array_column($found['ingredients'] ?? [], 'text');

        // Underscores mark allergens in Open Food Facts (_milk_); stars and full stops are footnotes and ends.
        return collect($names)
            ->map(fn (string $name) => preg_replace('/^[\s*.•]+|[\s*.•]+$/u', '', str_replace('_', '', $name)))
            ->filter()
            ->values()
            ->all();
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
