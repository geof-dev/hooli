<?php

use App\Models\Product;
use App\ProductImport;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    Http::preventStrayRequests();
    Log::spy();
    config(['hooli.countries' => ['ee']]);
});

// An export as the open databases make it: a line of JSON for each product, gzipped. Lines that aren't arrays go as
// they are.
function openFactsExport(array $lines): string
{
    return gzencode(collect($lines)->map(fn ($line) => is_array($line) ? json_encode($line) : $line)->implode("\n")."\n");
}

function exportFile(array $lines): string
{
    $path = tempnam(sys_get_temp_dir(), 'export');
    file_put_contents($path, openFactsExport($lines));

    return $path;
}

// A white bread sold in Estonia, as Open Food Facts knows it.
function bread(array $fields = []): array
{
    return $fields + [
        'code' => '4740125100013',
        'product_name' => 'Röst sai',
        'brands' => 'Leibur',
        'categories_tags' => ['en:breads', 'en:white-breads'],
        'countries_tags' => ['en:estonia'],
        'ingredients' => [
            ['id' => 'en:wheat-flour', 'text' => 'nisujahu', 'is_in_taxonomy' => 1],
            ['id' => 'en:water', 'text' => 'vesi', 'is_in_taxonomy' => 1],
            ['id' => 'en:e300', 'text' => 'askorbiinhape', 'is_in_taxonomy' => 1],
        ],
        'additives_tags' => ['en:e300'],
        'nova_group' => 4,
        'nutriments' => ['energy-kcal_100g' => 265, 'fiber_100g' => 2.7, 'salt_100g' => 1.0160000000000002],
        'image_front_url' => 'https://images.openfoodfacts.org/images/products/474/012/510/0013/front_et.4.400.jpg',
    ];
}

it('imports the products sold in our countries that have a name and an ingredient list', function () {
    $file = exportFile([
        bread(),
        bread(['code' => '4750000000017', 'product_name' => 'Baltmaize', 'countries_tags' => ['en:latvia']]),
        bread(['code' => '4740000000101', 'ingredients' => [], 'ingredients_text' => '']),
        bread(['code' => '4740000000102', 'product_name' => '']),
        bread(['code' => 'abc']),
        // Made in Estonia, sold in Finland.
        bread(['code' => '4740000000103', 'origins_tags' => ['en:estonia'], 'countries_tags' => ['en:finland']]),
        '{"code": "4740000000104", "countries_tags": ["en:estonia"',
        '',
    ]);

    $this->artisan('products:import', ['--file' => $file, '--source' => ['food']])
        ->expectsOutput('food: 1 sold in ee with their ingredients, 1 added, 0 updated, 0 unchanged')
        ->assertSuccessful();

    expect(Product::sole()->toApp())->toMatchArray([
        'barcode' => '4740125100013',
        'name' => 'Röst sai',
        'brand' => 'Leibur',
        'kind' => 'food',
        'category' => 'food',
        'types' => ['en:breads', 'en:white-breads'],
        'countries' => ['ee'],
        'ingredients' => ['nisujahu', 'vesi', 'askorbiinhape'],
        'ingredient_ids' => ['en:wheat-flour', 'en:water', 'en:e300'],
        'additives' => ['en:e300'],
        'nutrition' => ['energy_kcal' => 265.0, 'carbohydrates' => null, 'sugars' => null, 'fibre' => 2.7, 'fat' => null, 'saturated_fat' => null, 'proteins' => null, 'salt' => 1.02],
        'processing' => 4,
        'image_url' => 'https://images.openfoodfacts.org/images/products/474/012/510/0013/front_et.4.400.jpg',
        'source' => 'openfoodfacts',
        'confirmations' => 1,
    ]);
    Log::shouldHaveReceived('info')->with('products:import food', ['countries' => ['ee'], 'sold' => 1, 'added' => 1, 'updated' => 0, 'unchanged' => 0]);

    // Other countries, when asked.
    $this->artisan('products:import', ['--file' => $file, '--source' => ['food'], '--country' => ['LV', 'ee']])
        ->expectsOutput('food: 2 sold in lv, ee with their ingredients, 1 added, 0 updated, 1 unchanged')
        ->assertSuccessful();
    expect(Product::pluck('barcode')->all())->toBe(['4740125100013', '4750000000017']);
    expect(File::exists($file))->toBeTrue();
});

it('refreshes the products it has from the open databases, and only fills in those read on a label or found on the web', function () {
    $ours = [
        // From an earlier import, since scanned in Latvia.
        storeProduct(['barcode' => '4740125100013', 'kind' => 'food', 'category' => 'food', 'source' => 'openfoodfacts', 'name' => 'Rost sai', 'ingredients' => ['nisujahu', 'vesi'], 'countries' => ['lv'], 'confirmations' => 3]),
        storeProduct(['barcode' => '4740125100020', 'kind' => 'food', 'category' => 'food', 'name' => 'Röstsai Leibur', 'ingredients' => ['Wheat flour', 'Water', 'Salt', 'E300']]),
        storeProduct(['barcode' => '4740125100037', 'kind' => 'food', 'category' => 'food', 'source' => 'user', 'ingredients' => ['Wheat flour', 'Water'], 'types' => ['en:breads'], 'processing' => 3]),
        storeProduct(['barcode' => '4740125100044', 'kind' => 'food', 'category' => 'food', 'source' => 'web', 'source_url' => 'https://leibur.ee/rost-sai', 'confirmations' => 0, 'ingredients' => ['Wheat flour', 'Water', 'Yeast', 'E300']]),
        // Not in the export.
        storeProduct(['barcode' => '4740000123456']),
    ];
    $file = exportFile([bread(), bread(['code' => '4740125100020']), bread(['code' => '4740125100037']), bread(['code' => '4740125100044'])]);

    $this->artisan('products:import', ['--file' => $file, '--source' => ['food']])
        ->expectsOutput('food: 4 sold in ee with their ingredients, 0 added, 4 updated, 0 unchanged')
        ->assertSuccessful();
    [$imported, $read, $sent, $web, $other] = array_map(fn (Product $product) => $product->fresh(), $ours);

    expect($imported)
        ->name->toBe('Röst sai')
        ->ingredients->toBe(['nisujahu', 'vesi', 'askorbiinhape'])
        ->ingredient_ids->toBe(['en:wheat-flour', 'en:water', 'en:e300'])
        ->types->toBe(['en:breads', 'en:white-breads'])
        ->countries->toBe(['lv', 'ee'])
        ->source->toBe('openfoodfacts')
        ->confirmations->toBe(3);

    // A list read on the label keeps its name and list: what we didn't know comes from theirs.
    expect($read)
        ->name->toBe('Röstsai Leibur')
        ->ingredients->toBe(['Wheat flour', 'Water', 'Salt', 'E300'])
        ->ingredient_ids->toBeNull()
        ->source->toBe('hooli')
        ->types->toBe(['en:breads', 'en:white-breads'])
        ->countries->toBe(['ee'])
        ->nutrition->toMatchArray(['energy_kcal' => 265.0, 'fibre' => 2.7])
        ->processing->toBe(4)
        ->additives->toBe(['en:e300']);
    expect($sent)
        ->ingredients->toBe(['Wheat flour', 'Water'])
        ->source->toBe('user')
        ->types->toBe(['en:breads'])
        ->processing->toBe(3)
        ->additives->toBe(['en:e300']);
    expect($web)
        ->ingredients->toBe(['Wheat flour', 'Water', 'Yeast', 'E300'])
        ->source->toBe('web')
        ->source_url->toBe('https://leibur.ee/rost-sai')
        ->processing->toBe(4);
    expect($other->toApp())->toBe($ours[4]->fresh()->toApp())
        ->and(Product::count())->toBe(5);

    // Nothing has changed a week later.
    $this->artisan('products:import', ['--file' => $file, '--source' => ['food']])
        ->expectsOutput('food: 4 sold in ee with their ingredients, 0 added, 0 updated, 4 unchanged')
        ->assertSuccessful();
});

it('downloads the export of each database, and deletes it when done', function () {
    Http::fake([
        'static.openfoodfacts.org/data/openfoodfacts-products.jsonl.gz' => Http::response(openFactsExport([bread()])),
        'static.openbeautyfacts.org/data/openbeautyfacts-products.jsonl.gz' => Http::response(openFactsExport([
            ['code' => '4740000123456', 'product_name' => 'Öökreem retinooliga', 'countries_tags' => ['en:estonia'], 'categories_tags' => ['en:face-creams', 'en:night-creams'], 'ingredients_text' => 'Aqua, Glycerin, Retinyl Palmitate'],
        ])),
        'static.openproductsfacts.org/data/openproductsfacts-products.jsonl.gz' => Http::response(openFactsExport([])),
    ]);

    $this->artisan('products:import')
        ->expectsOutput('food: 1 sold in ee with their ingredients, 1 added, 0 updated, 0 unchanged')
        ->expectsOutput('beauty: 1 sold in ee with their ingredients, 1 added, 0 updated, 0 unchanged')
        ->expectsOutput('products: 0 sold in ee with their ingredients, 0 added, 0 updated, 0 unchanged')
        ->assertSuccessful();

    expect(Product::pluck('source', 'barcode')->all())->toBe(['4740125100013' => 'openfoodfacts', '4740000123456' => 'openbeautyfacts'])
        ->and(Product::firstWhere('barcode', '4740000123456')->category)->toBe('face-care');
    Http::assertSentCount(3);
    Http::assertSent(fn (Request $request) => $request->hasHeader('User-Agent', 'Hooli/1.0 (hello@gethooli.app)'));
    foreach (array_keys(ProductImport::DATABASES) as $database) {
        expect(File::exists(ProductImport::path($database)))->toBeFalse();
    }
});

it('deletes a download it couldn’t import', function () {
    Http::fake(['static.openbeautyfacts.org/*' => Http::response('Not found', 404)]);

    expect(fn () => $this->artisan('products:import', ['--source' => ['beauty']])->run())->toThrow(RequestException::class);
    expect(File::exists(ProductImport::path('beauty')))->toBeFalse()
        ->and(Product::count())->toBe(0);
});

it('refuses a source or a country it doesn’t know, and a file of no one source', function () {
    $file = exportFile([bread()]);

    $this->artisan('products:import', ['--source' => ['toys']])->assertFailed();
    $this->artisan('products:import', ['--file' => $file])->assertFailed();
    $this->artisan('products:import', ['--file' => $file, '--source' => ['food', 'beauty']])->assertFailed();
    $this->artisan('products:import', ['--file' => $file, '--source' => ['food'], '--country' => ['xx']])->assertFailed();
    expect(Product::count())->toBe(0);
    Http::assertNothingSent();
});

it('imports every week', function () {
    $event = collect(app(Schedule::class)->events())->first(fn ($event) => str_contains($event->command, 'products:import'));

    expect($event->expression)->toBe('0 0 * * 0');
});
