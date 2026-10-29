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
        'ingredients' => ['Aqua', 'Glycerin', 'Retinyl Palmitate'],
        'image_url' => null,
        'source' => 'hooli',
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
        ->assertJsonPath('product.source', 'openfoodfacts');
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
