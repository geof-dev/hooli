<?php

namespace App;

use App\Models\Product;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * The weekly import of every product the open databases say is sold in our countries, with a name and its ingredient
 * list, so that a scan finds it here without asking anyone. Each database exports all its products every night, a
 * line of JSON each, gzipped: the file is downloaded, read a line at a time so memory stays flat, and deleted. A list
 * read on a label, or found on the web, stays as it is: the import only fills in what we didn't know of its product.
 */
final class ProductImport
{
    // The databases, by the names the command takes, with the source their products keep and their export. Open Food
    // Facts' is the big one, some 13 GB.
    public const DATABASES = [
        'food' => ['source' => 'openfoodfacts', 'export' => 'https://static.openfoodfacts.org/data/openfoodfacts-products.jsonl.gz'],
        'beauty' => ['source' => 'openbeautyfacts', 'export' => 'https://static.openbeautyfacts.org/data/openbeautyfacts-products.jsonl.gz'],
        'products' => ['source' => 'openproductsfacts', 'export' => 'https://static.openproductsfacts.org/data/openproductsfacts-products.jsonl.gz'],
    ];

    // What a later import may fill in on a product whose list stays.
    private const DETAILS = ['types', 'nutrition', 'processing', 'additives'];

    /** Where the export of a database is downloaded to. */
    public static function path(string $database): string
    {
        return storage_path('app/imports/'.basename(self::DATABASES[$database]['export']));
    }

    public static function download(string $database): void
    {
        File::ensureDirectoryExists(dirname(self::path($database)));

        // Straight to the disk: the food export takes half an hour at 60 Mbit/s.
        OpenFacts::http()->connectTimeout(10)->timeout(4 * 3600)->sink(self::path($database))
            ->get(self::DATABASES[$database]['export'])
            ->throw();
    }

    /**
     * Adds or updates, from the export of a database, the products sold in these countries (ISO codes) with a name and
     * an ingredient list. None is ever deleted.
     *
     * @param  list<string>  $countries
     * @return array{sold: int, added: int, updated: int, unchanged: int}
     */
    public static function import(string $database, string $path, array $countries): array
    {
        $source = self::DATABASES[$database]['source'];
        $tags = array_keys(array_intersect(OpenFacts::COUNTRIES, $countries));
        $quoted = array_map(fn (string $tag) => "\"$tag\"", $tags);
        $counts = ['sold' => 0, 'added' => 0, 'updated' => 0, 'unchanged' => 0];

        $file = gzopen($path, 'rb');
        try {
            while (($line = gzgets($file)) !== false) {
                // Most lines name none of our countries, and aren't worth decoding. One that names it elsewhere than
                // where it is sold (where it is made, say) is decoded for nothing.
                if (! Str::contains($line, $quoted)) {
                    continue;
                }
                $found = json_decode($line, true);
                if (! is_array($found) || ! array_intersect((array) ($found['countries_tags'] ?? []), $tags)) {
                    continue;
                }

                $barcode = (string) ($found['code'] ?? '');
                $theirs = preg_match(Product::BARCODE, $barcode) ? OpenFacts::product($barcode, $source, $found) : null;
                if ($theirs) {
                    $counts['sold']++;
                    $counts[self::keep($theirs)]++;
                }
            }
        } finally {
            gzclose($file);
        }

        return $counts;
    }

    // Theirs, as ours: added, refreshed when our list is theirs, or else filled in. The countries we found it in are
    // kept with theirs.
    private static function keep(Product $theirs): string
    {
        $ours = Product::firstWhere('barcode', $theirs->barcode);
        if (! $ours) {
            $theirs->save();

            return 'added';
        }

        if (in_array($ours->source, array_column(self::DATABASES, 'source'), true)) {
            $ours->fill($theirs->only(['name', 'brand', 'kind', 'category', 'ingredients', 'ingredient_ids', 'image_url', 'source', ...self::DETAILS]));
        } else {
            foreach (self::DETAILS as $detail) {
                $ours->$detail ??= $theirs->$detail;
            }
        }
        $ours->countries = array_values(array_unique([...$ours->countries ?? [], ...$theirs->countries]));
        if (! $ours->isDirty()) {
            return 'unchanged';
        }
        $ours->save();

        return 'updated';
    }
}
