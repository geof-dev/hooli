<?php

use App\Models\Product;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
    config(['hooli.ai' => true, 'services.anthropic.key' => 'test-key', 'hooli.models.label' => 'claude-haiku-5-5', 'hooli.models.retry' => 'claude-sonnet-5-5']);
});

// A message from Claude, with these content blocks. A fresh response each time: the SDK reads the body once.
function claudeAnswers(array $content, string $stopReason = 'end_turn')
{
    return Http::response([
        'id' => 'msg_01',
        'type' => 'message',
        'role' => 'assistant',
        'model' => 'claude-haiku-5-5',
        'content' => $content,
        'stop_reason' => $stopReason,
        'stop_sequence' => null,
        'usage' => ['input_tokens' => 7600, 'output_tokens' => 900],
    ]);
}

// One ingredient as Claude reads it: no concern unless the fields say otherwise.
function ingredient(string $name, string $role, array $fields = []): array
{
    return $fields + ['name' => $name, 'role' => $role, 'level' => 'fine', 'hormones' => [], 'why' => '', 'matters_for' => [], 'level_for' => 'fine'];
}

// The label of a night cream, as Claude reads it, unless the read says otherwise.
function nightCream(array $read = []): array
{
    return $read + [
        'view' => 'ingredients',
        'name' => 'Night cream with retinol',
        'brand' => 'Saar & Sammal',
        'kind' => 'cosmetic',
        'category' => 'face-care',
        'ingredients' => [
            ingredient('Aqua', 'Water'),
            ingredient('Glycerin', 'Moisturiser'),
            ingredient('Retinyl Palmitate', 'Vitamin A', ['level' => 'know', 'why' => 'High doses of vitamin A are a concern in pregnancy.', 'matters_for' => ['pregnancy'], 'level_for' => 'swap']),
        ],
        'summary' => 'Mostly gentle, with vitamin A to know about if you are pregnant.',
        'medicine' => null,
    ];
}

// Claude answers each model with its read: by default, the first model reads the night cream.
function claudeReads(?array $first = null, ?array $retry = null, string $stopReason = 'end_turn'): void
{
    $reads = ['claude-haiku-5-5' => $first ?? nightCream(), 'claude-sonnet-5-5' => $retry];
    Http::fake(['api.anthropic.com/*' => fn (Request $request) => isset($reads[$request['model']])
        ? claudeAnswers([['type' => 'text', 'text' => json_encode($reads[$request['model']])]], $request['model'] === 'claude-haiku-5-5' ? $stopReason : 'end_turn')
        : null]);
}

// The models Claude was asked, in turn.
function modelsAsked(): array
{
    return Http::recorded()->map(fn (array $pair) => $pair[0]['model'])->values()->all();
}

// A phone's photo of the label, with the barcode the app scanned.
function sendLabel(array $fields = [])
{
    return test()->post('/api/v1/labels', $fields + [
        'image' => UploadedFile::fake()->image('label.jpg', 4032, 3024),
        'barcode' => '4740000123456',
    ]);
}

it('reads a label with the low-cost model, in the app’s language, and adds the product it shows', function () {
    claudeReads();

    sendLabel(['locale' => 'et'])->assertOk()->assertHeaderMissing('Set-Cookie')->assertJson([
        'product' => [
            'barcode' => '4740000123456',
            'name' => 'Night cream with retinol',
            'brand' => 'Saar & Sammal',
            'kind' => 'cosmetic',
            'category' => 'face-care',
            'ingredients' => ['Aqua', 'Glycerin', 'Retinyl Palmitate'],
            'image_url' => null,
            'source' => 'hooli',
            'source_url' => null,
            'confirmations' => 1,
        ],
        'medicine' => null,
        'analysis' => [
            'locale' => 'et',
            'estimated' => false,
            'summary' => 'Mostly gentle, with vitamin A to know about if you are pregnant.',
            'ingredients' => nightCream()['ingredients'],
        ],
        'found_by' => 'label',
    ]);
    expect(Product::sole())
        ->source->toBe('hooli')
        ->ingredients->toBe(['Aqua', 'Glycerin', 'Retinyl Palmitate']);

    // The photo goes as a JPEG no bigger than Claude reads, asking for the read as JSON, in Estonian.
    [$request] = Http::recorded()->sole();
    [$image, $text] = $request['messages'][0]['content'];
    $photo = new Imagick;
    $photo->readImageBlob(base64_decode($image['source']['data']));
    $schema = $request['output_config']['format']['schema'];
    expect($request->url())->toBe('https://api.anthropic.com/v1/messages')
        ->and($request->header('Authorization'))->toBe(['Bearer test-key'])
        ->and($request['model'])->toBe('claude-haiku-5-5')
        ->and($request['output_config']['effort'])->toBe('low')
        ->and($request['fallbacks'] ?? null)->toBeNull()
        ->and($request['tools'] ?? null)->toBeNull()
        ->and($request['system'])->not->toContain('Estonia')
        ->and($text)->toBe(['type' => 'text', 'text' => 'Read this product. Write in Estonian.'])
        ->and($schema['required'])->toBe(['view', 'name', 'brand', 'kind', 'category', 'ingredients', 'summary', 'medicine'])
        ->and($schema['additionalProperties'])->toBeFalse()
        ->and($schema['properties']['view']['enum'])->toBe(['ingredients', 'medicine', 'front', 'unreadable'])
        ->and($schema['properties']['category']['enum'])->toContain('kitchenware')
        ->and($schema['properties']['ingredients']['items']['required'])->toBe(['name', 'role', 'level', 'hormones', 'why', 'matters_for', 'level_for'])
        ->and($schema['properties']['ingredients']['items']['properties']['hormones']['items']['properties']['hormone']['enum'])
        ->toBe(['oestrogen', 'progesterone', 'testosterone', 'thyroid', 'cortisol', 'insulin'])
        ->and($schema['properties']['medicine']['anyOf'][0]['required'])->toBe(['active_substances', 'strength', 'form', 'family', 'questions'])
        ->and($image['source']['media_type'])->toBe('image/jpeg')
        ->and([$photo->getImageFormat(), $photo->getImageWidth(), $photo->getImageHeight()])->toBe(['JPEG', 2576, 1932]);
});

it('writes in English when the app names no language, and refuses one it doesn’t speak', function () {
    claudeReads();

    sendLabel()->assertOk()->assertJsonPath('analysis.locale', 'en');
    expect(Http::recorded()->sole()[0]['messages'][0]['content'][1]['text'])->toBe('Read this product. Write in English.');

    sendLabel(['locale' => 'fr'])->assertStatus(422)->assertExactJson(['message' => 'invalid_locale']);
    Http::assertSentCount(1);
});

it('reads with the models the settings name', function () {
    config(['hooli.models.label' => 'claude-sonnet-5-5', 'hooli.models.retry' => 'claude-opus-5-5']);
    Http::fake(['api.anthropic.com/*' => fn () => claudeAnswers([['type' => 'text', 'text' => json_encode(nightCream())]])]);

    sendLabel()->assertOk();
    expect(modelsAsked())->toBe(['claude-sonnet-5-5']);
});

it('reads no photo while the AI is switched off', function () {
    config(['hooli.ai' => false]);
    claudeReads();

    sendLabel()->assertStatus(503)->assertExactJson(['message' => 'ai_off']);
    Http::assertNothingSent();
    expect(Product::count())->toBe(0);
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
    storeProduct(['source' => 'openbeautyfacts', 'ingredients' => ['Aqua', 'Glycerin'], 'ingredient_ids' => ['en:water', 'en:glycerol'], 'image_url' => 'https://images.openbeautyfacts.org/front.jpg']);
    claudeReads();

    sendLabel()->assertOk()
        ->assertJsonPath('product.ingredients', ['Aqua', 'Glycerin', 'Retinyl Palmitate'])
        ->assertJsonPath('product.ingredient_ids', null)
        ->assertJsonPath('product.source', 'hooli')
        ->assertJsonPath('product.image_url', 'https://images.openbeautyfacts.org/front.jpg');
    expect(Product::sole()->ingredients)->toBe(['Aqua', 'Glycerin', 'Retinyl Palmitate']);
});

it('keeps a list it disagrees with when that list came from an earlier read, or is longer', function () {
    storeProduct(['ingredients' => ['Aqua', 'Paraffinum Liquidum']]);
    storeProduct(['barcode' => '4740000654321', 'source' => 'openbeautyfacts', 'ingredients' => ['Aqua', 'Glycerin', 'Parfum', 'Tocopherol']]);
    storeProduct(['barcode' => '4740000654322', 'source' => 'user', 'ingredients' => ['Aqua']]);
    claudeReads();

    sendLabel()->assertOk()->assertJsonPath('product.ingredients', ['Aqua', 'Paraffinum Liquidum']);
    sendLabel(['barcode' => '4740000654321'])->assertOk()->assertJsonPath('product.source', 'openbeautyfacts');
    // Read on the phone is read on the label too.
    sendLabel(['barcode' => '4740000654322'])->assertOk()->assertJsonPath('product.source', 'user')->assertJsonPath('product.ingredients', ['Aqua']);
    expect(Product::pluck('confirmations')->all())->toBe([1, 1, 1]);
});

// A box of ibuprofen, with its active substance first and the excipients it shows.
function ibuprofenBox(): array
{
    return [
        'view' => 'medicine',
        'name' => 'Ibumax 400 mg',
        'brand' => 'Vitabalans',
        'kind' => 'medicine',
        'category' => 'medicine',
        'ingredients' => [
            ingredient('Ibuprofen', 'Painkiller', ['level' => 'know', 'hormones' => [['hormone' => 'progesterone', 'evidence' => 'human']], 'why' => 'Taken around ovulation, it can delay it.', 'matters_for' => ['pregnancy'], 'level_for' => 'know']),
            ingredient('Microcrystalline Cellulose', 'Filler'),
        ],
        'summary' => 'A common painkiller: fine now and then, worth knowing about when trying for a baby.',
        'medicine' => [
            'active_substances' => ['Ibuprofen', ' '],
            'strength' => '400 mg',
            'form' => 'film-coated tablets',
            'family' => 'Anti-inflammatory painkiller (NSAID)',
            'questions' => ['How many days in a row can I take it?', 'Is it safe while trying to get pregnant?'],
        ],
    ];
}

it('reads a medicine box, and keeps nothing without a barcode', function () {
    claudeReads(ibuprofenBox());

    sendLabel(['barcode' => null])->assertOk()->assertExactJson([
        'product' => [
            'barcode' => null,
            'name' => 'Ibumax 400 mg',
            'brand' => 'Vitabalans',
            'kind' => 'medicine',
            'category' => 'medicine',
            'types' => null,
            'countries' => null,
            'ingredients' => ['Ibuprofen', 'Microcrystalline Cellulose'],
            'ingredient_ids' => null,
            'additives' => null,
            'nutrition' => null,
            'processing' => null,
            'image_url' => null,
            'source' => 'hooli',
            'source_url' => null,
            'confirmations' => 0,
            'updated_at' => null,
        ],
        'medicine' => [
            'active_substances' => ['Ibuprofen'],
            'strength' => '400 mg',
            'form' => 'film-coated tablets',
            'family' => 'Anti-inflammatory painkiller (NSAID)',
            'questions' => ['How many days in a row can I take it?', 'Is it safe while trying to get pregnant?'],
        ],
        'analysis' => [
            'locale' => 'en',
            'estimated' => false,
            'summary' => 'A common painkiller: fine now and then, worth knowing about when trying for a baby.',
            'ingredients' => ibuprofenBox()['ingredients'],
        ],
        'found_by' => 'medicine',
    ]);
    expect(Product::count())->toBe(0);
});

it('keeps a medicine by its barcode, as a medicine whatever Claude called it', function () {
    claudeReads([...ibuprofenBox(), 'kind' => 'other', 'category' => 'other']);

    sendLabel()->assertOk()
        ->assertJsonPath('found_by', 'medicine')
        ->assertJsonPath('product.kind', 'medicine')
        ->assertJsonPath('medicine.strength', '400 mg');

    $this->get('/api/v1/products/4740000123456')->assertOk()->assertJson(['product' => [
        'name' => 'Ibumax 400 mg',
        'kind' => 'medicine',
        'category' => 'medicine',
        'ingredients' => ['Ibuprofen', 'Microcrystalline Cellulose'],
        'source' => 'hooli',
        'confirmations' => 1,
    ]]);
    Http::assertSentCount(1);
});

it('gives what a front or an object usually holds, marked as an estimate, and never keeps it', function () {
    claudeReads(nightCream([
        'view' => 'front',
        'name' => 'Non-stick frying pan',
        'brand' => 'Tefal',
        'kind' => 'household',
        'category' => 'kitchenware',
        'ingredients' => [
            ingredient('PTFE non-stick coating', 'Non-stick coating', ['level' => 'know', 'hormones' => [['hormone' => 'thyroid', 'evidence' => 'human']], 'why' => 'Older coatings were made with PFAS linked to thyroid changes.']),
            ingredient('Aluminium', 'Pan body'),
        ],
        'summary' => 'Worth knowing about its coating; keep it below high heat.',
    ]));

    sendLabel()->assertOk()
        ->assertJsonPath('found_by', 'estimate')
        ->assertJsonPath('product.barcode', '4740000123456')
        ->assertJsonPath('product.category', 'kitchenware')
        ->assertJsonPath('product.ingredients', ['PTFE non-stick coating', 'Aluminium'])
        ->assertJsonPath('product.confirmations', 0)
        ->assertJsonPath('analysis.estimated', true)
        ->assertJsonPath('analysis.ingredients.0.hormones', [['hormone' => 'thyroid', 'evidence' => 'human']]);
    expect(Product::count())->toBe(0);
});

it('asks the stronger model about a photo the first can’t make out, or declines', function (?array $first, string $stopReason) {
    claudeReads($first, retry: nightCream(), stopReason: $stopReason);

    sendLabel()->assertOk()->assertJsonPath('product.name', 'Night cream with retinol');
    expect(modelsAsked())->toBe(['claude-haiku-5-5', 'claude-sonnet-5-5']);
})->with([
    'unreadable' => [['view' => 'unreadable', 'name' => 'Photo of a shelf', 'ingredients' => []] + nightCream(), 'end_turn'],
    'a list too blurred to hold a name' => [['ingredients' => [ingredient(' ', 'Unknown')]] + nightCream(), 'end_turn'],
    'a front with nothing to go on' => [['view' => 'front', 'ingredients' => []] + nightCream(), 'end_turn'],
    'a refusal' => [nightCream(), 'refusal'],
    'an answer cut short' => [nightCream(), 'max_tokens'],
]);

it('says so when neither model can make the photo out, and keeps nothing', function () {
    $shelf = ['view' => 'unreadable', 'name' => 'Photo of a shelf', 'ingredients' => []] + nightCream();
    claudeReads($shelf, retry: $shelf);

    sendLabel()->assertStatus(422)->assertExactJson(['message' => 'unreadable']);
    expect(modelsAsked())->toBe(['claude-haiku-5-5', 'claude-sonnet-5-5'])
        ->and(Product::count())->toBe(0);
});

it('asks no other model when the settings name none', function () {
    config(['hooli.models.retry' => null]);
    claudeReads(['view' => 'unreadable', 'ingredients' => []] + nightCream());

    sendLabel()->assertStatus(422)->assertExactJson(['message' => 'unreadable']);
    expect(modelsAsked())->toBe(['claude-haiku-5-5']);
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
