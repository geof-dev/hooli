<?php

use App\Models\Product;
use Illuminate\Support\Facades\Http;

beforeEach(fn () => Http::preventStrayRequests());

// The label of a night cream, as the app read it on the phone.
function sendRead(array $fields = [])
{
    return test()->postJson('/api/v1/products', $fields + [
        'barcode' => '4740000123456',
        'name' => 'Night cream with retinol',
        'brand' => 'Saar & Sammal',
        'kind' => 'cosmetic',
        'category' => 'face-care',
        'ingredients' => ['Aqua', 'Glycerin', 'Retinyl Palmitate'],
        'country' => 'ee',
    ]);
}

it('adds a product whose label the app read, without asking anyone', function () {
    sendRead()->assertCreated()->assertHeaderMissing('Set-Cookie')->assertJson(['product' => [
        'barcode' => '4740000123456',
        'name' => 'Night cream with retinol',
        'brand' => 'Saar & Sammal',
        'kind' => 'cosmetic',
        'category' => 'face-care',
        'countries' => ['ee'],
        'ingredients' => ['Aqua', 'Glycerin', 'Retinyl Palmitate'],
        'ingredient_ids' => null,
        'source' => 'user',
        'source_url' => null,
        'confirmations' => 1,
    ]]);
    expect(Product::sole())->source->toBe('user')->confirmations->toBe(1);
    Http::assertNothingSent();

    // The next scan finds it here.
    $this->get('/api/v1/products/4740000123456')->assertOk()->assertJsonPath('product.source', 'user');
});

it('adds a medicine by its active substances, without a brand or a country', function () {
    sendRead(['name' => 'Microgynon', 'brand' => null, 'kind' => 'medicine', 'category' => 'medicine', 'ingredients' => ['Levonorgestrel', 'Ethinylestradiol'], 'country' => null])
        ->assertCreated()
        ->assertJsonPath('product.brand', null)
        ->assertJsonPath('product.countries', null)
        ->assertJsonPath('product.ingredients', ['Levonorgestrel', 'Ethinylestradiol']);
});

it('confirms the list we have when the app reads the same, and adds the country', function () {
    storeProduct(['source' => 'openbeautyfacts', 'countries' => ['ee'], 'ingredients' => ['Aqua', 'Glycerin', 'Retinyl palmitate.']]);

    sendRead(['ingredients' => ['AQUA', 'Glycerin', 'Retinyl Palmitate'], 'country' => 'LV'])->assertOk()
        ->assertJsonPath('product.confirmations', 2)
        ->assertJsonPath('product.countries', ['ee', 'lv'])
        ->assertJsonPath('product.ingredients', ['Aqua', 'Glycerin', 'Retinyl palmitate.'])
        ->assertJsonPath('product.source', 'openbeautyfacts');
    expect(Product::sole())->confirmations->toBe(2)->countries->toBe(['ee', 'lv']);
});

it('replaces a shorter list from the open databases or the web with the one the app read', function (string $source) {
    storeProduct(['source' => $source, 'source_url' => $source === 'web' ? 'https://saarsammal.ee/en/night-cream' : null, 'confirmations' => 4, 'ingredients' => ['Aqua', 'Glycerin'], 'ingredient_ids' => ['en:water', 'en:glycerol']]);

    sendRead()->assertOk()
        ->assertJsonPath('product.ingredients', ['Aqua', 'Glycerin', 'Retinyl Palmitate'])
        ->assertJsonPath('product.ingredient_ids', null)
        ->assertJsonPath('product.source', 'user')
        ->assertJsonPath('product.source_url', null)
        ->assertJsonPath('product.confirmations', 1)
        ->assertJsonPath('product.countries', ['ee']);
    expect(Product::sole())->source->toBe('user')->ingredients->toBe(['Aqua', 'Glycerin', 'Retinyl Palmitate']);
})->with(['openbeautyfacts', 'web']);

it('keeps a list it disagrees with when that list was read on a label, or is longer', function () {
    storeProduct(['ingredients' => ['Aqua', 'Paraffinum Liquidum']]);
    storeProduct(['barcode' => '4740000654321', 'source' => 'user', 'ingredients' => ['Aqua']]);
    storeProduct(['barcode' => '4740000654322', 'source' => 'openbeautyfacts', 'ingredients' => ['Aqua', 'Glycerin', 'Parfum', 'Tocopherol']]);

    sendRead()->assertOk()->assertJsonPath('product.ingredients', ['Aqua', 'Paraffinum Liquidum'])->assertJsonPath('product.source', 'hooli');
    sendRead(['barcode' => '4740000654321'])->assertOk()->assertJsonPath('product.ingredients', ['Aqua'])->assertJsonPath('product.source', 'user');
    sendRead(['barcode' => '4740000654322'])->assertOk()->assertJsonPath('product.source', 'openbeautyfacts');
    expect(Product::pluck('confirmations')->all())->toBe([1, 1, 1])
        ->and(Product::pluck('countries')->all())->toBe([['ee'], ['ee'], ['ee']]);
});

it('refuses a read that isn’t whole, with what is wrong with it', function (array $fields, array $errors) {
    sendRead($fields)->assertUnprocessable()->assertJsonStructure(['message', 'errors'])->assertOnlyJsonValidationErrors($errors);
    expect(Product::count())->toBe(0);
})->with([
    'no barcode' => [['barcode' => null], ['barcode']],
    'a barcode that isn’t one' => [['barcode' => '12345'], ['barcode']],
    'a barcode as a number' => [['barcode' => 4740000123456], ['barcode']],
    'no name' => [['name' => ' '], ['name']],
    'a name too long' => [['name' => str_repeat('a', 201)], ['name']],
    'a kind we don’t have' => [['kind' => 'toy'], ['kind']],
    'a category we don’t have' => [['category' => 'skin'], ['category']],
    'no ingredients' => [['ingredients' => []], ['ingredients']],
    'ingredients as text' => [['ingredients' => 'Aqua, Glycerin'], ['ingredients']],
    'ingredients by name' => [['ingredients' => ['first' => 'Aqua']], ['ingredients']],
    'too many ingredients' => [['ingredients' => array_fill(0, 301, 'Aqua')], ['ingredients']],
    'an empty ingredient' => [['ingredients' => ['Aqua', '']], ['ingredients.1']],
    'an ingredient too long' => [['ingredients' => ['Aqua', str_repeat('a', 201)]], ['ingredients.1']],
    'a country that isn’t one' => [['country' => 'est'], ['country']],
]);

it('slows down anyone sending reads over and over', function () {
    foreach (range(1, 30) as $i) {
        $this->postJson('/api/v1/products')->assertUnprocessable();
    }
    $this->postJson('/api/v1/products')->assertTooManyRequests();
});
