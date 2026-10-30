<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\LabelReader;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Reading the photo of a product, whichever side it shows, and what its ingredients may do to hormones, in the app's
 * language. With the barcode the app scanned, the ingredients on a label add a product we don't have, confirm the one
 * we have, or replace a shorter list from the open databases; a medicine box is kept by its ingredients. A front's
 * list is only what such a product usually holds: it is never kept. The photo itself is not kept either. With the AI
 * switched off (config/hooli.php), no photo is read: the app reads the label on the phone, and sends its text
 * (ProductController::store).
 */
class LabelController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        if (! config('hooli.ai')) {
            return response()->json(['message' => 'ai_off'], 503);
        }

        $validator = Validator::make($request->all(), [
            'image' => ['required', 'file', 'mimes:jpg,jpeg,png,heic,heif', 'max:8192'],
            'barcode' => ['nullable', 'string', 'regex:'.Product::BARCODE],
            'locale' => ['nullable', Rule::in(array_keys(LabelReader::LANGUAGES))],
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => match (true) {
                $validator->errors()->has('image') => 'invalid_image',
                $validator->errors()->has('barcode') => 'invalid_barcode',
                default => 'invalid_locale',
            }], 422);
        }

        $locale = $request->input('locale') ?? 'en';
        $read = LabelReader::read($request->file('image'), $locale);
        if (! $read) {
            return response()->json(['message' => 'unreadable'], 422);
        }

        $barcode = $request->input('barcode');
        $fields = [...Arr::only($read, ['name', 'brand', 'kind', 'category']), 'ingredients' => array_column($read['ingredients'], 'name')];
        if ($read['view'] === 'medicine') {
            $fields = [...$fields, 'kind' => 'medicine', 'category' => 'medicine'];
        }
        $product = $barcode && $read['view'] !== 'front'
            ? $this->learn($barcode, $fields)
            : new Product([...$fields, 'barcode' => $barcode, 'source' => 'hooli', 'confirmations' => 0]);

        return response()->json([
            'product' => $product->toApp(),
            'medicine' => $read['medicine'],
            // What the phone scores by, for the ingredients as Claude read them.
            'analysis' => [
                'locale' => $locale,
                'estimated' => $read['view'] === 'front',
                'summary' => $read['summary'],
                'ingredients' => $read['ingredients'],
            ],
            'found_by' => match ($read['view']) {
                'front' => 'estimate',
                'medicine' => 'medicine',
                'ingredients' => 'label',
            },
        ]);
    }

    // A label read is first-hand: it adds the product, confirms the list we have, or replaces a shorter one from the
    // open databases, whose ids of its ingredients go with it. A list that disagrees with a longer one, or with an
    // earlier read, stays as it is.
    private function learn(string $barcode, array $read): Product
    {
        $product = Product::firstWhere('barcode', $barcode);
        if (! $product) {
            return Product::create([...$read, 'barcode' => $barcode, 'source' => 'hooli']);
        }

        if ($product->hasIngredients($read['ingredients'])) {
            $product->increment('confirmations');
        } elseif (! in_array($product->source, Product::READ_ON_LABEL, true) && count($product->ingredients) < count($read['ingredients'])) {
            $product->update(['ingredients' => $read['ingredients'], 'ingredient_ids' => null, 'source' => 'hooli', 'source_url' => null, 'confirmations' => 1]);
        }

        return $product;
    }
}
