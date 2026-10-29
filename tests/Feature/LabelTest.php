<?php

use App\Models\Product;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
    config(['hooli.ai' => true, 'services.anthropic.key' => 'test-key']);
});

// A message from Claude, with these content blocks. A fresh response each time: the SDK reads the body once.
function claudeAnswers(array $content, string $stopReason = 'end_turn')
{
    return Http::response([
        'id' => 'msg_01',
        'type' => 'message',
        'role' => 'assistant',
        'model' => 'claude-opus-5-5',
        'content' => $content,
        'stop_reason' => $stopReason,
        'stop_sequence' => null,
        'usage' => ['input_tokens' => 4900, 'output_tokens' => 120],
    ]);
}

// Claude answers with this read of the photo, the label of a night cream unless the read says otherwise. Its searches
// on the web are answered by claudeFinds().
function claudeReads(array $read = []): void
{
    Http::fake(['api.anthropic.com/*' => fn (Request $request) => isset($request['tools']) ? null : claudeAnswers([['type' => 'text', 'text' => json_encode($read + [
        'view' => 'ingredients',
        'name' => 'Night cream with retinol',
        'brand' => 'Saar & Sammal',
        'kind' => 'cosmetic',
        'category' => 'face-care',
        'ingredients' => ['Aqua', 'Glycerin', 'Retinyl Palmitate'],
        'medicine' => null,
    ])]])]);
}

// Claude searches the web once, and records what it found there with record_ingredients. When the search pauses
// first, the turn is answered only once it is sent back.
function claudeFinds(array $found, bool $pauses = false): void
{
    claudeSearches([['type' => 'tool_use', 'id' => 'toolu_01', 'name' => 'record_ingredients', 'input' => $found]], 'tool_use', $pauses);
}

// Claude searches the web once, and ends its turn with these blocks.
function claudeSearches(array $answer, string $stopReason, bool $pauses = false): void
{
    $search = [
        ['type' => 'server_tool_use', 'id' => 'srvtoolu_01', 'name' => 'web_search', 'input' => ['query' => 'Saar & Sammal night cream with retinol ingredients']],
        ['type' => 'web_search_tool_result', 'tool_use_id' => 'srvtoolu_01', 'content' => [
            ['type' => 'web_search_result', 'url' => 'https://saarsammal.ee/en/night-cream', 'title' => 'Night cream with retinol', 'encrypted_content' => 'EqgfCioIARgB', 'page_age' => null],
        ]],
    ];
    Http::fake(['api.anthropic.com/*' => fn (Request $request) => match (true) {
        ! isset($request['tools']) => null,
        $pauses && count($request['messages']) === 1 => claudeAnswers([$search[0]], 'pause_turn'),
        default => claudeAnswers([...$search, ...$answer], $stopReason),
    }]);
}

// The open databases' search answers with the products given for each; the others find nothing.
function openFactsFind(array $products = []): void
{
    Http::fake(collect(['openbeautyfacts', 'openfoodfacts', 'openproductsfacts'])->mapWithKeys(fn (string $database) => [
        "world.$database.org/cgi/search.pl*" => Http::response(['count' => count($products[$database] ?? []), 'page' => 1, 'products' => $products[$database] ?? []]),
    ])->all());
}

// The requests sent to Claude's web search, and to the open databases' search.
function webSearches(): array
{
    return Http::recorded(fn (Request $request) => isset($request['tools']))->map(fn (array $pair) => $pair[0])->values()->all();
}

function openFactsSearches(): array
{
    return Http::recorded(fn (Request $request) => str_contains($request->url(), '/cgi/search.pl'))->map(fn (array $pair) => $pair[0])->values()->all();
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
            'source_url' => null,
            'confirmations' => 1,
        ],
        'medicine' => null,
        'found_by' => 'label',
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
        ->and($request['output_config']['format']['schema']['required'])->toBe(['view', 'name', 'brand', 'kind', 'category', 'ingredients', 'medicine'])
        ->and($request['output_config']['format']['schema']['properties']['view']['enum'])->toBe(['ingredients', 'medicine', 'front', 'unreadable'])
        ->and($request['tools'] ?? null)->toBeNull()
        ->and($request['output_config']['format']['schema']['additionalProperties'])->toBeFalse()
        ->and($request['output_config']['format']['schema']['properties']['medicine']['anyOf'][0]['required'])->toBe(['active_substances', 'strength', 'form'])
        ->and($image['source']['media_type'])->toBe('image/jpeg')
        ->and([$photo->getImageFormat(), $photo->getImageWidth(), $photo->getImageHeight()])->toBe(['JPEG', 2576, 1932]);
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

// A box of the pill, with its excipients and active substances.
function claudeReadsMedicine(): void
{
    claudeReads([
        'view' => 'medicine',
        'name' => 'Microgynon',
        'brand' => 'Bayer',
        'kind' => 'medicine',
        'category' => 'medicine',
        'ingredients' => ['Lactose Monohydrate', 'Maize Starch'],
        'medicine' => ['active_substances' => ['Levonorgestrel', 'Ethinylestradiol'], 'strength' => '0.15 mg/0.03 mg', 'form' => 'coated tablets'],
    ]);
}

it('reads a medicine box, and keeps nothing without a barcode', function () {
    claudeReadsMedicine();

    sendLabel(['barcode' => null])->assertOk()->assertExactJson([
        'product' => [
            'barcode' => null,
            'name' => 'Microgynon',
            'brand' => 'Bayer',
            'kind' => 'medicine',
            'category' => 'medicine',
            'types' => null,
            'countries' => null,
            'ingredients' => ['Levonorgestrel', 'Ethinylestradiol'],
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
        'medicine' => ['active_substances' => ['Levonorgestrel', 'Ethinylestradiol'], 'strength' => '0.15 mg/0.03 mg', 'form' => 'coated tablets'],
        'found_by' => 'medicine',
    ]);
    expect(Product::count())->toBe(0);
});

it('keeps a medicine by its barcode and active substances, so the next scan needs no photo', function () {
    claudeReadsMedicine();

    sendLabel()->assertOk()
        ->assertJsonPath('found_by', 'medicine')
        ->assertJsonPath('product.barcode', '4740000123456')
        ->assertJsonPath('product.ingredients', ['Levonorgestrel', 'Ethinylestradiol'])
        ->assertJsonPath('medicine.strength', '0.15 mg/0.03 mg');

    $this->get('/api/v1/products/4740000123456')->assertOk()->assertJson(['product' => [
        'name' => 'Microgynon',
        'brand' => 'Bayer',
        'kind' => 'medicine',
        'category' => 'medicine',
        'ingredients' => ['Levonorgestrel', 'Ethinylestradiol'],
        'source' => 'hooli',
        'confirmations' => 1,
    ]]);
    Http::assertSentCount(1);
});

it('says so when a photo shows nothing to read, and keeps nothing', function () {
    claudeReads(['view' => 'unreadable', 'name' => 'Photo of a shelf', 'ingredients' => [], 'medicine' => null]);
    sendLabel()->assertStatus(422)->assertExactJson(['message' => 'unreadable']);

    // A label Claude couldn't make out is nothing to go on either.
    claudeReads(['view' => 'ingredients', 'name' => 'Blurred label', 'ingredients' => [' '], 'medicine' => null]);
    sendLabel()->assertStatus(422)->assertExactJson(['message' => 'unreadable']);

    expect(Product::count())->toBe(0);
    expect(webSearches())->toBeEmpty();
});

// The front of a night cream: Claude recognises it, and reads no ingredients.
function claudeReadsFront(array $read = []): void
{
    claudeReads($read + ['view' => 'front', 'ingredients' => []]);
}

it('finds the list of a front in our products, by its name and brand whatever their case, the most confirmed first', function () {
    storeProduct(['barcode' => '4740000000001', 'confirmations' => 2]);
    storeProduct(['barcode' => '4740000000002', 'confirmations' => 5, 'ingredients' => ['Aqua', 'Glycerin', 'Retinol']]);
    storeProduct(['barcode' => '4740000000003', 'confirmations' => 9, 'brand' => 'Another brand']);
    storeProduct(['barcode' => '4740000000004', 'confirmations' => 9, 'name' => 'Day cream']);
    claudeReadsFront(['name' => 'NIGHT CREAM WITH RETINOL', 'brand' => 'saar & sammal']);

    sendLabel(['barcode' => null])->assertOk()
        ->assertJsonPath('found_by', 'database')
        ->assertJsonPath('product.barcode', '4740000000002')
        ->assertJsonPath('product.ingredients', ['Aqua', 'Glycerin', 'Retinol'])
        ->assertJsonPath('medicine', null);
    Http::assertSentCount(1);

    // The barcode the app scanned comes first.
    sendLabel(['barcode' => '4740000000001'])->assertOk()->assertJsonPath('found_by', 'database')->assertJsonPath('product.barcode', '4740000000001');
});

it('searches the open databases for a front, and keeps what they know under its own barcode', function () {
    claudeReadsFront(['name' => 'Öökreem retinooliga']);
    openFactsFind(['openbeautyfacts' => [
        ['code' => '4740000999991', 'product_name' => 'Päevakreem', 'brands' => 'Saar & Sammal', 'ingredients_text' => 'Aqua, Glycerin'],
        ['code' => '4740000999992', 'product_name' => 'Öökreem retinooliga', 'brands' => 'Another brand', 'ingredients_text' => 'Aqua, Paraffinum Liquidum'],
        ['code' => '4740000999993', 'product_name' => 'Öökreem retinooliga', 'brands' => 'Saar & Sammal', 'ingredients_text' => ''],
        [
            'code' => '4740000123457',
            'product_name' => 'Ookreem retinooliga 50 ml',
            'brands' => 'Saar & Sammal,Saar',
            'categories_tags' => ['en:face-creams', 'en:night-creams'],
            'ingredients_text_en' => 'Ingredients: Aqua, Glycerin, Parfum (Limonene, Linalool), Retinyl Palmitate*.',
        ],
    ]]);

    sendLabel()->assertOk()
        ->assertJsonPath('found_by', 'open_facts')
        ->assertJson(['product' => [
            'barcode' => '4740000123457',
            'name' => 'Ookreem retinooliga 50 ml',
            'brand' => 'Saar & Sammal',
            'kind' => 'cosmetic',
            'category' => 'face-care',
            'types' => ['en:face-creams', 'en:night-creams'],
            'ingredients' => ['Aqua', 'Glycerin', 'Parfum (Limonene, Linalool)', 'Retinyl Palmitate'],
            'ingredient_ids' => [null, null, null, null],
            'source' => 'openbeautyfacts',
            'source_url' => null,
        ]]);

    // Open Beauty Facts first, for a cosmetic, which knew it: nobody else was asked.
    [$search] = openFactsSearches();
    parse_str(parse_url($search->url(), PHP_URL_QUERY), $query);
    expect($search->url())->toStartWith('https://world.openbeautyfacts.org/cgi/search.pl?')
        ->and($query)->toBe([
            'search_terms' => 'Saar & Sammal Öökreem retinooliga',
            'search_simple' => '1',
            'action' => 'process',
            'json' => '1',
            'page_size' => '5',
            'fields' => 'code,product_name,brands,ingredients_text,ingredients_text_en,ingredients,categories_tags,countries_tags,additives_tags,nutriments,nova_group',
        ])
        ->and($search->header('User-Agent'))->toBe(['Hooli/1.0 (hello@gethooli.app)']);
    Http::assertSentCount(2);

    // The next scan of its barcode finds it here.
    $this->get('/api/v1/products/4740000123457')->assertOk()->assertJsonPath('product.source', 'openbeautyfacts');
    Http::assertSentCount(2);
    expect(Product::count())->toBe(1);
});

it('looks a front up on the web last, and keeps the list under the barcode the app scanned', function () {
    claudeReadsFront();
    openFactsFind();
    claudeFinds(['ingredients' => ['Aqua', ' Glycerin ', 'Retinyl Palmitate', ''], 'source_url' => 'https://saarsammal.ee/en/night-cream']);

    sendLabel()->assertOk()->assertJson([
        'product' => [
            'barcode' => '4740000123456',
            'name' => 'Night cream with retinol',
            'brand' => 'Saar & Sammal',
            'kind' => 'cosmetic',
            'category' => 'face-care',
            'ingredients' => ['Aqua', 'Glycerin', 'Retinyl Palmitate'],
            'image_url' => null,
            'source' => 'web',
            'source_url' => 'https://saarsammal.ee/en/night-cream',
            'confirmations' => 0,
        ],
        'medicine' => null,
        'found_by' => 'web',
    ]);
    expect(Product::sole())->source->toBe('web')->confirmations->toBe(0);

    // One call, with a few paid searches at most, answering through a strict tool it is asked, not made, to call.
    [$request] = webSearches();
    expect($request['model'])->toBe('claude-opus-5-5')
        ->and($request['tools'][0])->toBe(['type' => 'web_search_20260318', 'name' => 'web_search', 'max_uses' => 3, 'response_inclusion' => 'excluded'])
        ->and($request['tools'][1])->toMatchArray(['name' => 'record_ingredients', 'strict' => true])
        ->and($request['tools'][1]['input_schema'])->toBe([
            'type' => 'object',
            'properties' => [
                'ingredients' => ['type' => 'array', 'items' => ['type' => 'string']],
                'source_url' => ['anyOf' => [['type' => 'string'], ['type' => 'null']]],
            ],
            'required' => ['ingredients', 'source_url'],
            'additionalProperties' => false,
        ])
        ->and($request['tools'])->toHaveCount(2)
        ->and($request['tool_choice'])->toBe(['type' => 'auto'])
        ->and($request['output_config'])->toBe(['effort' => 'medium'])
        ->and($request['system'])->toContain('calling record_ingredients once')
        ->and($request['fallbacks'])->toBe('default')
        ->and($request->header('anthropic-beta'))->toBe(['server-side-fallback-2026-07-01'])
        ->and($request['messages'][0]['content'])->toBe("Find the ingredient list of this product.\n\nBrand: Saar & Sammal\nName: Night cream with retinol\nWhat it is: cosmetic, face-care\nBarcode: 4740000123456");
    expect(openFactsSearches())->toHaveCount(3);

    // The next scan finds it here.
    $this->get('/api/v1/products/4740000123456')->assertOk()->assertJsonPath('product.source', 'web');
    Http::assertSentCount(5);
});

it('answers from the web without a barcode, and keeps nothing', function () {
    claudeReadsFront();
    openFactsFind();
    claudeFinds(['ingredients' => ['Aqua', 'Glycerin', 'Retinyl Palmitate'], 'source_url' => 'https://www.kaubamaja.ee/saar-sammal-night-cream']);

    sendLabel(['barcode' => null])->assertOk()
        ->assertJsonPath('found_by', 'web')
        ->assertJsonPath('product.barcode', null)
        ->assertJsonPath('product.source', 'web')
        ->assertJsonPath('product.source_url', 'https://www.kaubamaja.ee/saar-sammal-night-cream')
        ->assertJsonPath('product.updated_at', null);
    expect(Product::count())->toBe(0)
        ->and(webSearches()[0]['messages'][0]['content'])->not->toContain('Barcode');
});

it('carries on a web search Claude paused', function () {
    claudeReadsFront();
    openFactsFind();
    claudeFinds(['ingredients' => ['Aqua', 'Glycerin', 'Retinyl Palmitate'], 'source_url' => 'https://saarsammal.ee/en/night-cream'], pauses: true);

    sendLabel()->assertOk()->assertJsonPath('found_by', 'web');

    // The paused turn goes back as it came, for Claude to finish.
    [$paused, $continued] = webSearches();
    expect($paused['messages'])->toHaveCount(1)
        ->and($continued['messages'][1]['role'])->toBe('assistant')
        ->and($continued['messages'][1]['content'][0])->toMatchArray(['type' => 'server_tool_use', 'id' => 'srvtoolu_01', 'name' => 'web_search'])
        ->and($continued['tools'])->toBe($paused['tools']);
});

it('asks for a photo of the ingredient list when nobody knows the front, naming the product', function () {
    claudeReadsFront(['name' => 'Keefir 2,5%', 'brand' => 'Tere', 'kind' => 'food', 'category' => 'drink']);
    openFactsFind(['openfoodfacts' => [
        ['code' => '4740125000016', 'product_name' => 'Keefir 2,5%', 'brands' => 'Tere', 'ingredients_text' => ''],
    ]]);
    claudeFinds(['ingredients' => [], 'source_url' => null]);

    sendLabel()->assertStatus(422)->assertExactJson([
        'message' => 'needs_ingredients',
        'product' => ['name' => 'Keefir 2,5%', 'brand' => 'Tere', 'kind' => 'food', 'category' => 'drink'],
    ]);
    expect(Product::count())->toBe(0);

    // Open Food Facts first, for a food, then the others, then the web.
    expect(collect(openFactsSearches())->map(fn (Request $request) => parse_url($request->url(), PHP_URL_HOST))->all())
        ->toBe(['world.openfoodfacts.org', 'world.openbeautyfacts.org', 'world.openproductsfacts.org'])
        ->and(webSearches())->toHaveCount(1);
});

it('takes no answer from the web but a well-formed call of record_ingredients', function (array $answer, string $stopReason) {
    claudeReadsFront();
    openFactsFind();
    claudeSearches($answer, $stopReason);

    sendLabel()->assertStatus(422)->assertJsonPath('message', 'needs_ingredients');
    expect(Product::count())->toBe(0)
        ->and(webSearches())->toHaveCount(1);
})->with([
    'no call' => [[['type' => 'text', 'text' => '{"ingredients": ["Aqua"], "source_url": null}']], 'end_turn'],
    'a refusal' => [[], 'refusal'],
    'an answer cut short' => [[['type' => 'tool_use', 'id' => 'toolu_01', 'name' => 'record_ingredients', 'input' => ['ingredients' => ['Aqua']]]], 'max_tokens'],
    'a call of another shape' => [[['type' => 'tool_use', 'id' => 'toolu_01', 'name' => 'record_ingredients', 'input' => ['ingredients' => 'Aqua, Glycerin', 'source_url' => null]]], 'tool_use'],
    'a call without its page' => [[['type' => 'tool_use', 'id' => 'toolu_01', 'name' => 'record_ingredients', 'input' => ['ingredients' => ['Aqua']]]], 'tool_use'],
    'a call of another tool' => [[['type' => 'tool_use', 'id' => 'toolu_01', 'name' => 'web_fetch', 'input' => ['ingredients' => ['Aqua'], 'source_url' => null]]], 'tool_use'],
]);

it('asks for the ingredient list when the web search fails', function () {
    claudeReadsFront();
    openFactsFind();
    Http::fake(['api.anthropic.com/*' => fn (Request $request) => isset($request['tools']) ? Http::response(['type' => 'error', 'error' => ['type' => 'invalid_request_error', 'message' => 'Web search is not enabled']], 400) : null]);

    sendLabel()->assertStatus(422)->assertJsonPath('message', 'needs_ingredients');
    expect(Product::count())->toBe(0);
});

it('replaces a list from the web with a fuller one read on the label', function () {
    storeProduct(['source' => 'web', 'source_url' => 'https://saarsammal.ee/en/night-cream', 'confirmations' => 0, 'ingredients' => ['Aqua', 'Glycerin']]);
    claudeReads();

    sendLabel()->assertOk()
        ->assertJsonPath('found_by', 'label')
        ->assertJsonPath('product.ingredients', ['Aqua', 'Glycerin', 'Retinyl Palmitate'])
        ->assertJsonPath('product.source', 'hooli')
        ->assertJsonPath('product.source_url', null)
        ->assertJsonPath('product.confirmations', 1);
    expect(Product::sole())->source->toBe('hooli')->source_url->toBeNull();
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
