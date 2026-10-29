<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\LabelReader;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;

/**
 * Reading the photo of a label. With the barcode the app scanned, the ingredients it shows add a product we don't
 * have, confirm the one we have, or replace a shorter list from the open databases. The photo itself is not kept.
 */
class LabelController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'image' => ['required', 'file', 'mimes:jpg,jpeg,png,heic,heif', 'max:8192'],
            'barcode' => ['nullable', 'string', 'regex:'.Product::BARCODE],
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->has('image') ? 'invalid_image' : 'invalid_barcode'], 422);
        }

        $read = LabelReader::read($request->file('image'));
        if (! $read) {
            return response()->json(['message' => 'unreadable'], 422);
        }

        // Without a barcode, or without ingredients (a medicine box may show only its active substances), the read
        // goes back as it is, and nothing is kept.
        $barcode = $request->input('barcode');
        $product = $barcode && $read['ingredients']
            ? $this->learn($barcode, $read)
            : new Product([...Arr::only($read, ['name', 'brand', 'kind', 'category', 'ingredients']), 'barcode' => $barcode, 'source' => 'hooli', 'confirmations' => 0]);

        return response()->json(['product' => $product->toApp(), 'medicine' => $read['medicine']]);
    }

    // A label read is first-hand: it adds the product, confirms the list we have, or replaces a shorter one from the
    // open databases. A list that disagrees with a longer one, or with an earlier read, stays as it is.
    private function learn(string $barcode, array $read): Product
    {
        $product = Product::firstWhere('barcode', $barcode);
        if (! $product) {
            return Product::create([...Arr::only($read, ['name', 'brand', 'kind', 'category', 'ingredients']), 'barcode' => $barcode, 'source' => 'hooli']);
        }

        if ($this->same($product->ingredients, $read['ingredients'])) {
            $product->increment('confirmations');
        } elseif ($product->source !== 'hooli' && count($product->ingredients) < count($read['ingredients'])) {
            $product->update(['ingredients' => $read['ingredients'], 'source' => 'hooli', 'confirmations' => 1]);
        }

        return $product;
    }

    // The same ingredients in the same order, whatever the case, spacing or punctuation: Aqua (Water) is aqua water.
    private function same(array $ours, array $read): bool
    {
        $key = fn (array $names) => array_map(fn (string $name) => preg_replace('/[^\pL\pN]+/u', '', mb_strtolower($name)), $names);

        return $key($ours) === $key($read);
    }
}
