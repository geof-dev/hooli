<?php

use App\Models\Product;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(fn () => Http::preventStrayRequests());

// The open databases answer with the products given (or the answer given); the others know nothing of the barcode.
function openFactsKnow(array $products): void
{
    Http::fake(collect(['openbeautyfacts', 'openfoodfacts', 'openproductsfacts'])->mapWithKeys(fn (string $database) => [
        "world.$database.org/*" => match (true) {
            ! isset($products[$database]) => Http::response(['status' => 0, 'status_verbose' => 'product not found'], 404),
            is_array($products[$database]) => Http::response(['status' => 1, 'status_verbose' => 'product found', 'product' => $products[$database]]),
            default => $products[$database],
        },
    ])->all());
}

it('answers with a product we have, without asking anyone', function () {
    $product = storeProduct();

    $this->get('/api/v1/products/4740000123456')->assertOk()->assertHeaderMissing('Set-Cookie')->assertExactJson(['product' => [
        'barcode' => '4740000123456',
        'name' => 'Night cream with retinol',
        'brand' => 'Saar & Sammal',
        'kind' => 'cosmetic',
        'category' => 'face-care',
        'types' => null,
        'countries' => null,
        'ingredients' => ['Aqua', 'Glycerin', 'Retinyl Palmitate'],
        'ingredient_ids' => null,
        'additives' => null,
        'nutrition' => null,
        'processing' => null,
        'image_url' => null,
        'source' => 'hooli',
        'source_url' => null,
        'confirmations' => 1,
        'updated_at' => $product->updated_at->toAtomString(),
    ]]);
    Http::assertNothingSent();
});

it('looks a new barcode up in Open Beauty Facts, and keeps what it finds', function () {
    openFactsKnow(['openbeautyfacts' => [
        'product_name' => 'Öökreem retinooliga',
        'brands' => 'Saar & Sammal,Saar',
        'categories_tags' => ['en:face-creams', 'en:night-creams'],
        'ingredients_text_en' => 'Ingredients: Aqua, Glycerin, Parfum (Limonene, Linalool), Retinyl Palmitate*.',
        'image_front_url' => 'https://images.openbeautyfacts.org/images/products/474/000/012/3456/front_et.3.400.jpg',
    ]]);

    $this->get('/api/v1/products/4740000123456')->assertOk()
        ->assertJson(['product' => [
            'barcode' => '4740000123456',
            'name' => 'Öökreem retinooliga',
            'brand' => 'Saar & Sammal',
            'kind' => 'cosmetic',
            'category' => 'face-care',
            'image_url' => 'https://images.openbeautyfacts.org/images/products/474/000/012/3456/front_et.3.400.jpg',
            'source' => 'openbeautyfacts',
            'confirmations' => 1,
        ]])
        ->assertJsonPath('product.ingredients', ['Aqua', 'Glycerin', 'Parfum (Limonene, Linalool)', 'Retinyl Palmitate']);
    Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://world.openbeautyfacts.org/api/v2/product/4740000123456.json')
        && $request->hasHeader('User-Agent', 'Hooli/1.0 (hello@gethooli.app)'));

    // The next scan finds it here.
    $this->get('/api/v1/products/4740000123456')->assertOk()->assertJsonPath('product.source', 'openbeautyfacts');
    Http::assertSentCount(1);
    expect(Product::count())->toBe(1);
});

it('asks Open Food Facts next, and takes only a product that lists its ingredients', function () {
    openFactsKnow([
        'openbeautyfacts' => ['product_name' => 'Keefir', 'ingredients_text' => ''],
        'openfoodfacts' => [
            'product_name' => 'Keefir 2,5%',
            'brands' => 'Tere',
            'categories_tags' => ['en:dairies', 'en:fermented-milk-products', 'en:beverages', 'en:kefir'],
            'ingredients' => [['id' => 'en:pasteurised-milk', 'text' => 'pastöriseeritud _piim_'], ['id' => 'en:ferment', 'text' => 'juuretis']],
        ],
    ]);

    $this->get('/api/v1/products/4740125000016')->assertOk()
        ->assertJsonPath('product.name', 'Keefir 2,5%')
        ->assertJsonPath('product.kind', 'food')
        ->assertJsonPath('product.category', 'drink')
        ->assertJsonPath('product.ingredients', ['pastöriseeritud piim', 'juuretis'])
        ->assertJsonPath('product.ingredient_ids', ['en:pasteurised-milk', 'en:ferment'])
        ->assertJsonPath('product.source', 'openfoodfacts');
});

it('keeps what else Open Food Facts knows of a food: its types, countries, nutrition, processing and additives', function () {
    openFactsKnow(['openfoodfacts' => [
        'product_name' => 'Röst sai',
        'brands' => 'Leibur',
        'categories_tags' => ['en:plant-based-foods-and-beverages', 'en:breads', 'en:white-breads'],
        'countries_tags' => ['en:estonia', 'en:latvia', 'en:world'],
        'ingredients_text_en' => 'Wheat flour, water, sugar, yeast, vegetable oil (rapeseed), antioxidant: ascorbic acid',
        'ingredients' => [
            ['id' => 'en:wheat-flour', 'text' => '_nisujahu_', 'is_in_taxonomy' => 1],
            ['id' => 'en:water', 'text' => 'vesi', 'is_in_taxonomy' => 1],
            ['id' => 'en:sugar', 'text' => 'suhkur', 'is_in_taxonomy' => 1],
            ['id' => 'et:parm', 'text' => 'pärm', 'is_in_taxonomy' => 0],
            ['id' => 'en:vegetable-oil', 'text' => 'taimeõli', 'is_in_taxonomy' => 1, 'ingredients' => [['id' => 'en:rapeseed-oil', 'text' => 'rapsiõli']]],
            ['id' => 'en:e300', 'text' => 'askorbiinhape', 'is_in_taxonomy' => 1],
        ],
        'additives_tags' => ['en:e300'],
        'nova_group' => 4,
        'nutriments' => [
            'energy-kcal_100g' => 265, 'carbohydrates_100g' => 49.1, 'sugars_100g' => '4.2', 'fiber_100g' => 2.7, 'fat_100g' => 3.2,
            'saturated-fat_100g' => 0.4, 'proteins_100g' => 8.9, 'salt_100g' => 1.0160000000000002, 'sodium_100g' => 0.4064,
        ],
    ]]);

    $this->get('/api/v1/products/4740125100013')->assertOk()->assertJson(['product' => [
        'kind' => 'food',
        'category' => 'bread-cereals',
        'types' => ['en:plant-based-foods-and-beverages', 'en:breads', 'en:white-breads'],
        'countries' => ['ee', 'lv'],
        // In the order of the label, each with its id, as the label gives it; one they don't know has none.
        'ingredients' => ['nisujahu', 'vesi', 'suhkur', 'pärm', 'taimeõli (rapsiõli)', 'askorbiinhape'],
        'ingredient_ids' => ['en:wheat-flour', 'en:water', 'en:sugar', null, 'en:vegetable-oil', 'en:e300'],
        'additives' => ['en:e300'],
        'nutrition' => [
            'energy_kcal' => 265, 'carbohydrates' => 49.1, 'sugars' => 4.2, 'fibre' => 2.7, 'fat' => 3.2, 'saturated_fat' => 0.4,
            'proteins' => 8.9, 'salt' => 1.02,
        ],
        'processing' => 4,
    ]]);
    Http::assertSent(function (Request $request) {
        parse_str(parse_url($request->url(), PHP_URL_QUERY), $query);

        return str_contains($request->url(), 'openfoodfacts')
            && $query['fields'] === 'product_name,product_name_en,brands,categories_tags,countries_tags,ingredients_text_en,ingredients_text,ingredients,additives_tags,nutriments,nova_group,image_front_url,image_url';
    });
});

it('leaves out what a database doesn’t know of a product', function () {
    openFactsKnow(['openbeautyfacts' => ['product_name' => 'Hand cream', 'ingredients_text' => 'Aqua, Glycerin', 'nova_group' => 'unknown', 'nutriments' => []]]);

    $this->get('/api/v1/products/4740000123456')->assertOk()->assertJson(['product' => [
        'types' => [],
        'countries' => [],
        'ingredient_ids' => [null, null],
        'additives' => [],
        'nutrition' => null,
        'processing' => null,
    ]]);
});

it('records the country a product is scanned in', function () {
    storeProduct();

    $this->get('/api/v1/products/4740000123456?country=EE')->assertOk()->assertJsonPath('product.countries', ['ee']);
    $this->get('/api/v1/products/4740000123456?country=ee')->assertOk()->assertJsonPath('product.countries', ['ee']);
    $this->get('/api/v1/products/4740000123456?country=lv')->assertOk()->assertJsonPath('product.countries', ['ee', 'lv']);
    $this->get('/api/v1/products/4740000123456')->assertOk()->assertJsonPath('product.countries', ['ee', 'lv']);
    expect(Product::sole()->countries)->toBe(['ee', 'lv']);

    // One the open databases know is kept with it.
    openFactsKnow(['openfoodfacts' => ['product_name' => 'Keefir', 'countries_tags' => ['en:latvia'], 'ingredients_text' => 'piim, juuretis']]);
    $this->get('/api/v1/products/4740125000016?country=ee')->assertOk()->assertJsonPath('product.countries', ['lv', 'ee']);
    expect(Product::firstWhere('barcode', '4740125000016')->countries)->toBe(['lv', 'ee']);
});

it('refuses a country that isn’t one', function () {
    storeProduct();

    foreach (['est', 'e', '12', 'e1'] as $country) {
        $this->get("/api/v1/products/4740000123456?country=$country")->assertStatus(422)->assertExactJson(['message' => 'invalid_country']);
        $this->get("/api/v1/products?category=face-care&country=$country")->assertStatus(422)->assertExactJson(['message' => 'invalid_country']);
    }
    $this->get('/api/v1/products/4740000123456?country[]=ee')->assertStatus(422);
    expect(Product::sole()->countries)->toBeNull();
});

it('answers 404 when no database knows the barcode, and keeps nothing', function () {
    openFactsKnow(['openbeautyfacts' => Http::failedConnection()]);

    $this->get('/api/v1/products/4740000999999')->assertNotFound()->assertExactJson(['message' => 'not_found']);
    Http::assertSentCount(3);
    Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://world.openproductsfacts.org/api/v2/product/4740000999999.json'));
    expect(Product::count())->toBe(0);
});

it('refuses a barcode that isn’t one', function () {
    foreach (['abc', '1234567', '123456789012345', '47400001234x6'] as $barcode) {
        $this->get("/api/v1/products/$barcode")->assertStatus(422)->assertExactJson(['message' => 'invalid_barcode']);
    }
    Http::assertNothingSent();
});

it('offers swaps: products of the same category, the most confirmed first', function () {
    storeProduct(['barcode' => '4740000000001', 'confirmations' => 2]);
    storeProduct(['barcode' => '4740000000002', 'confirmations' => 7]);
    storeProduct(['barcode' => '4740000000003', 'confirmations' => 9]);
    storeProduct(['barcode' => '4740000000004', 'confirmations' => 9, 'category' => 'body-care']);
    storeProduct(['barcode' => '4740000000005', 'confirmations' => 9, 'ingredients' => []]);

    $this->get('/api/v1/products?category=face-care&exclude=4740000000003')->assertOk()
        ->assertJsonPath('products.*.barcode', ['4740000000002', '4740000000001'])
        ->assertJsonPath('products.0', Product::firstWhere('barcode', '4740000000002')->toApp());
});

it('offers swaps sold in the country the app says', function () {
    storeProduct(['barcode' => '4740000000001', 'countries' => ['ee', 'lv']]);
    storeProduct(['barcode' => '4740000000002', 'countries' => ['lv']]);
    storeProduct(['barcode' => '4740000000003']);
    storeProduct(['barcode' => '4740000000004', 'countries' => ['ee'], 'confirmations' => 3]);

    $this->get('/api/v1/products?category=face-care&country=ee')->assertOk()->assertJsonPath('products.*.barcode', ['4740000000004', '4740000000001']);
    $this->get('/api/v1/products?category=face-care&country=LV')->assertOk()->assertJsonPath('products.*.barcode', ['4740000000001', '4740000000002']);
    $this->get('/api/v1/products?category=face-care')->assertOk()->assertJsonCount(4, 'products');
});

it('offers swaps of the most specific type that gives five, then of a more general one, then of the category', function () {
    // Six white breads, three wholemeal ones, and a cake.
    foreach (range(1, 6) as $i) {
        storeProduct(['barcode' => "474000000000$i", 'kind' => 'food', 'category' => 'food', 'types' => ['en:breads', 'en:white-breads']]);
    }
    foreach (range(7, 9) as $i) {
        storeProduct(['barcode' => "474000000000$i", 'kind' => 'food', 'category' => 'food', 'types' => ['en:breads', 'en:wholemeal-breads']]);
    }
    storeProduct(['barcode' => '4740000000010', 'kind' => 'food', 'category' => 'food', 'types' => ['en:cakes']]);
    $swaps = fn (string $types) => $this->get("/api/v1/products?category=food&types=$types")->assertOk()->json('products.*.barcode');

    expect($swaps('en:breads,en:white-breads'))->toHaveCount(6)
        ->and($swaps('en:breads,en:wholemeal-breads'))->toHaveCount(9)
        ->and($swaps('en:breads, en:wholemeal-breads, en:rye-breads'))->toHaveCount(9)
        ->and($swaps('en:pastries,en:croissants'))->toHaveCount(10)
        ->and($swaps(''))->toHaveCount(10);

    // Five are enough, even with the one scanned left out.
    $this->get('/api/v1/products?category=food&types=en:breads,en:white-breads&exclude=4740000000001')->assertOk()->assertJsonCount(5, 'products');
    $this->get('/api/v1/products?category=food&types=en:breads,en:white-breads&exclude=4740000000001&country=ee')->assertOk()->assertJsonCount(0, 'products');
});

it('offers the swaps of a food the richest in fibre first, after the most confirmed', function () {
    $food = fn (string $barcode, ?float $fibre, int $confirmations = 1) => storeProduct([
        'barcode' => $barcode, 'kind' => 'food', 'category' => 'food', 'confirmations' => $confirmations,
        'nutrition' => $fibre === null ? null : ['energy_kcal' => 250, 'fibre' => $fibre],
    ]);
    $food('4740000000001', 2.5);
    $food('4740000000002', null);
    $food('4740000000003', 8.1);
    $food('4740000000004', 0.4, confirmations: 4);
    $food('4740000000005', 0);

    $this->get('/api/v1/products?category=food')->assertOk()
        ->assertJsonPath('products.*.barcode', ['4740000000004', '4740000000003', '4740000000001', '4740000000005', '4740000000002']);
});

it('offers at most 40 swaps, and none for a category it doesn’t know', function () {
    foreach (range(1, 45) as $i) {
        storeProduct(['barcode' => sprintf('47400000%05d', $i)]);
    }

    $this->get('/api/v1/products?category=face-care')->assertOk()->assertJsonCount(40, 'products');
    $this->get('/api/v1/products?category=skin')->assertStatus(422)->assertExactJson(['message' => 'invalid_category']);
    $this->get('/api/v1/products')->assertStatus(422);
});

it('tells the app about changes to EU rules, in its three languages', function () {
    $this->get('/api/v1/updates')->assertOk()
        ->assertJsonPath('updates.0.id', 'vitamin-a-limits')
        ->assertJsonPath('updates.0.date', '2024-04-03')
        ->assertJsonPath('updates.0.substances', ['vitamin-a'])
        ->assertJsonPath('updates.0.title.en', 'Lower limits for vitamin A in cosmetics')
        ->assertJsonPath('updates.0.source', 'Commission Regulation (EU) 2024/996');

    foreach (config('hooli.updates') as $update) {
        expect($update)->toHaveKeys(['id', 'date', 'substances', 'title', 'body', 'source'])
            ->and($update['date'])->toMatch('/^\d{4}-\d{2}-\d{2}$/')
            ->and($update['title'])->toHaveKeys(['en', 'et', 'ru'])
            ->and($update['body'])->toHaveKeys(['en', 'et', 'ru']);
    }
});
