<?php

namespace App;

use Anthropic\Beta\Messages\BetaMessage;
use Anthropic\Core\Exceptions\APIException;
use App\Models\Product;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Finds the ingredient list of a product on the web with Claude, when a photo showed only its front and neither we nor
 * the open databases know it: the list its brand publishes, on its own site or a major retailer's product page. Every
 * search is paid for, so this comes last, and Claude may search only a few times.
 */
final class WebIngredients
{
    // Claude Opus 5.5, at medium effort, tells one variant of a product from another: the night cream from the day
    // cream, SPF 30 from SPF 50.
    private const MODEL = 'claude-opus-5-5';

    // Each search costs a fee: three find the brand's page or a retailer's, or show there is none.
    private const SEARCHES = 3;

    // A long search may pause: Claude carries on when its turn is sent back, twice at most.
    private const TURNS = 3;

    private const INSTRUCTIONS = <<<'TEXT'
        You find ingredient lists for Hooli, an app that tells women what in a product may affect their hormones. Search the web for the ingredient list of the product you are given, as its brand publishes it: on the brand's own site, or on the product page of a major retailer.

        Give your answer by calling record_ingredients once, at the end:

        - ingredients: every ingredient, in the order of the list, one per entry. Write each by its INCI name when it has one (Aqua, Glycerin, Retinyl Palmitate), otherwise by its English name (sugar, wheat flour); food additives may keep their E number (E211). Leave out percentages, footnote marks and headings such as "Ingredients:".
        - source_url: the page you took the list from.

        Only a list given for this very product counts, never one of another variant: a night cream is not the day cream, SPF 30 is not SPF 50. Never write a list from what you know of the product. If no page gives its list, call record_ingredients with an empty list and a null source_url.
        TEXT;

    /**
     * The product read on a front (name, brand, kind, category) with the ingredient list found on the web, not yet
     * saved, or null when Claude found none.
     */
    public static function find(array $read, ?string $barcode): ?Product
    {
        $product = collect([
            'Brand' => $read['brand'],
            'Name' => $read['name'],
            'What it is' => "{$read['kind']}, {$read['category']}",
            'Barcode' => $barcode,
        ])->filter()->map(fn (string $value, string $label) => "$label: $value")->implode("\n");
        $messages = [['role' => 'user', 'content' => "Find the ingredient list of this product.\n\n$product"]];

        try {
            foreach (range(1, self::TURNS) as $turn) {
                $message = LabelReader::claude(timeout: 60)->beta->messages->create(
                    maxTokens: 16000,
                    model: self::MODEL,
                    system: self::INSTRUCTIONS,
                    messages: $messages,
                    // The search results Claude sifts with code stay out of the answer: only its list is needed.
                    tools: [['type' => 'web_search_20260318', 'name' => 'web_search', 'maxUses' => self::SEARCHES, 'responseInclusion' => 'excluded'], self::tool()],
                    // Opus 5.5 can't be made to call a tool: the instructions ask for record_ingredients.
                    toolChoice: ['type' => 'auto'],
                    outputConfig: ['effort' => 'medium'],
                    // Should Claude decline, the API hands the search to the fallback model it picks.
                    fallbacks: 'default',
                    betas: ['server-side-fallback-2026-07-01'],
                );
                if ($message->stopReason !== 'pause_turn') {
                    break;
                }
                $messages[] = ['role' => 'assistant', 'content' => $message->content];
            }
        } catch (APIException $exception) {
            // A search that fails is no reason to fail the photo: the app asks for the ingredient list instead.
            report($exception);

            return null;
        }

        $found = self::answer($message);
        $ingredients = collect($found['ingredients'] ?? [])->map(fn (string $name) => trim($name))->filter()->values()->all();
        if ($ingredients === []) {
            return null;
        }
        $url = $found['source_url'];

        return new Product([
            ...Arr::only($read, ['name', 'brand', 'kind', 'category']),
            'barcode' => $barcode,
            'ingredients' => $ingredients,
            'source' => 'web',
            'source_url' => is_string($url) && Str::startsWith($url, ['https://', 'http://']) && strlen($url) <= 500 ? $url : null,
            // Nobody has read this list on the product yet: a label photo replaces it.
            'confirmations' => 0,
        ]);
    }

    // The tool Claude answers with, strict so that its input fits the schema. A tool rather than an answer in JSON,
    // which the API may refuse alongside the citations web search adds to Claude's text.
    private static function tool(): array
    {
        return [
            'name' => 'record_ingredients',
            'description' => 'Records the ingredient list found for the product, and the page it was taken from: an empty list and a null page when none gives it. Call it once, with your answer.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'ingredients' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'source_url' => ['anyOf' => [['type' => 'string'], ['type' => 'null']]],
                ],
                'required' => ['ingredients', 'source_url'],
                'additionalProperties' => false,
            ],
            'strict' => true,
        ];
    }

    // The input of Claude's record_ingredients call, which the SDK hands over decoded, when it stopped to make it and
    // the input has the tool's shape. A refusal, an answer cut short, a search still paused or no call at all is none.
    private static function answer(BetaMessage $message): ?array
    {
        $call = $message->stopReason === 'tool_use'
            ? collect($message->content)->first(fn ($block) => $block->type === 'tool_use' && $block->name === 'record_ingredients')
            : null;
        $input = $call?->input;

        return is_array($input)
            && is_array($input['ingredients'] ?? null) && array_is_list($input['ingredients'])
            && collect($input['ingredients'])->every(fn ($name) => is_string($name))
            && array_key_exists('source_url', $input) && (is_string($input['source_url']) || $input['source_url'] === null)
            ? $input
            : null;
    }
}
