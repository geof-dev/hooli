<?php

use App\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
    config(['services.anthropic.key' => 'test-key']);
});

// Claude answers with this read of the label, a night cream unless the read says otherwise. A fresh response each
// time: the SDK reads the body once.
function claudeReads(array $read = []): void
{
    Http::fake(['api.anthropic.com/*' => fn () => Http::response([
        'id' => 'msg_01',
        'type' => 'message',
        'role' => 'assistant',
        'model' => 'claude-opus-5-5',
        'content' => [['type' => 'text', 'text' => json_encode($read + [
            'name' => 'Night cream with retinol',
            'brand' => 'Saar & Sammal',
            'kind' => 'cosmetic',
            'category' => 'face-care',
            'ingredients' => ['Aqua', 'Glycerin', 'Retinyl Palmitate'],
            'medicine' => null,
        ])]],
        'stop_reason' => 'end_turn',
        'stop_sequence' => null,
        'usage' => ['input_tokens' => 4900, 'output_tokens' => 120],
    ])]);
}

// A phone's photo of the label, with the barcode the app scanned.
function sendLabel(array $fields = [])
{
    return test()->post('/api/v1/labels', $fields + [
        'image' => UploadedFile::fake()->image('label.jpg', 4032, 3024),
        'barcode' => '4740000123456',
    ]);
}

it('reads a label with Claude, and adds the product it shows', function () {
    claudeReads();

    sendLabel()->assertOk()->assertHeaderMissing('Set-Cookie')->assertJson([
        'product' => [
            'barcode' => '4740000123456',
            'name' => 'Night cream with retinol',
            'brand' => 'Saar & Sammal',
            'kind' => 'cosmetic',
            'category' => 'face-care',
            'ingredients' => ['Aqua', 'Glycerin', 'Retinyl Palmitate'],
            'image_url' => null,
            'source' => 'hooli',
            'confirmations' => 1,
        ],
        'medicine' => null,
    ]);
    expect(Product::sole())
        ->source->toBe('hooli')
        ->ingredients->toBe(['Aqua', 'Glycerin', 'Retinyl Palmitate']);

    // The photo goes as a JPEG no bigger than Claude reads, asking for the read as JSON.
    [$request] = Http::recorded()->sole();
    $image = $request['messages'][0]['content'][0];
    $photo = new Imagick;
    $photo->readImageBlob(base64_decode($image['source']['data']));
    expect($request->url())->toBe('https://api.anthropic.com/v1/messages')
        ->and($request->header('Authorization'))->toBe(['Bearer test-key'])
        ->and($request->header('anthropic-beta'))->toBe(['server-side-fallback-2026-07-01'])
        ->and($request['fallbacks'])->toBe('default')
        ->and($request['model'])->toBe('claude-opus-5-5')
        ->and($request['output_config']['effort'])->toBe('low')
        ->and($request['output_config']['format']['schema']['required'])->toBe(['name', 'brand', 'kind', 'category', 'ingredients', 'medicine'])
        ->and($request['output_config']['format']['schema']['additionalProperties'])->toBeFalse()
        ->and($request['output_config']['format']['schema']['properties']['medicine']['anyOf'][0]['required'])->toBe(['active_substances', 'strength', 'form'])
        ->and($image['source']['media_type'])->toBe('image/jpeg')
        ->and([$photo->getImageFormat(), $photo->getImageWidth(), $photo->getImageHeight()])->toBe(['JPEG', 2576, 1932]);
});

it('confirms a product we have when the label shows the same ingredients', function () {
    storeProduct(['ingredients' => ['Aqua', 'Glycerin', 'Retinyl palmitate.']]);
    claudeReads();

    sendLabel()->assertOk()
        ->assertJsonPath('product.confirmations', 2)
        ->assertJsonPath('product.ingredients', ['Aqua', 'Glycerin', 'Retinyl palmitate.']);
    expect(Product::sole()->confirmations)->toBe(2);
});

it('replaces a shorter list from the open databases with the label’s', function () {
    storeProduct(['source' => 'openbeautyfacts', 'ingredients' => ['Aqua', 'Glycerin'], 'image_url' => 'https://images.openbeautyfacts.org/front.jpg']);
    claudeReads();

    sendLabel()->assertOk()
        ->assertJsonPath('product.ingredients', ['Aqua', 'Glycerin', 'Retinyl Palmitate'])
        ->assertJsonPath('product.source', 'hooli')
        ->assertJsonPath('product.image_url', 'https://images.openbeautyfacts.org/front.jpg');
    expect(Product::sole()->ingredients)->toBe(['Aqua', 'Glycerin', 'Retinyl Palmitate']);
});

it('keeps a list it disagrees with when that list came from an earlier read, or is longer', function () {
    storeProduct(['ingredients' => ['Aqua', 'Paraffinum Liquidum']]);
    storeProduct(['barcode' => '4740000654321', 'source' => 'openbeautyfacts', 'ingredients' => ['Aqua', 'Glycerin', 'Parfum', 'Tocopherol']]);
    claudeReads();

    sendLabel()->assertOk()->assertJsonPath('product.ingredients', ['Aqua', 'Paraffinum Liquidum']);
    sendLabel(['barcode' => '4740000654321'])->assertOk()->assertJsonPath('product.source', 'openbeautyfacts');
    expect(Product::pluck('confirmations')->all())->toBe([1, 1]);
});

it('reads a medicine box, and keeps nothing without a barcode', function () {
    claudeReads([
        'name' => 'Microgynon',
        'brand' => 'Bayer',
        'kind' => 'medicine',
        'category' => 'medicine',
        'ingredients' => ['Lactose Monohydrate', 'Maize Starch'],
        'medicine' => ['active_substances' => ['Levonorgestrel', 'Ethinylestradiol'], 'strength' => '0.15 mg/0.03 mg', 'form' => 'coated tablets'],
    ]);

    sendLabel(['barcode' => null])->assertOk()->assertExactJson([
        'product' => [
            'barcode' => null,
            'name' => 'Microgynon',
            'brand' => 'Bayer',
            'kind' => 'medicine',
            'category' => 'medicine',
            'ingredients' => ['Lactose Monohydrate', 'Maize Starch'],
            'image_url' => null,
            'source' => 'hooli',
            'confirmations' => 0,
            'updated_at' => null,
        ],
        'medicine' => ['active_substances' => ['Levonorgestrel', 'Ethinylestradiol'], 'strength' => '0.15 mg/0.03 mg', 'form' => 'coated tablets'],
    ]);
    expect(Product::count())->toBe(0);
});

it('says so when a photo shows nothing to read, and keeps nothing', function () {
    claudeReads(['name' => 'Photo of a shelf', 'ingredients' => [], 'medicine' => null]);

    sendLabel()->assertStatus(422)->assertExactJson(['message' => 'unreadable']);
    expect(Product::count())->toBe(0);
});

it('refuses a file that isn’t a photo, or a barcode that isn’t one, before asking Claude', function () {
    sendLabel(['image' => UploadedFile::fake()->create('label.pdf', 100, 'application/pdf')])->assertStatus(422)->assertExactJson(['message' => 'invalid_image']);
    sendLabel(['image' => UploadedFile::fake()->image('label.jpg')->size(9000)])->assertStatus(422)->assertExactJson(['message' => 'invalid_image']);
    sendLabel(['barcode' => '12345'])->assertStatus(422)->assertExactJson(['message' => 'invalid_barcode']);
    $this->post('/api/v1/labels')->assertStatus(422)->assertExactJson(['message' => 'invalid_image']);
    Http::assertNothingSent();
});

it('slows down anyone sending photos over and over', function () {
    foreach (range(1, 20) as $i) {
        $this->post('/api/v1/labels')->assertStatus(422);
    }
    $this->post('/api/v1/labels')->assertStatus(429);
});
