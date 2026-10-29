<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\LabelReader;
use App\Models\Product;
use App\OpenFacts;
use App\WebIngredients;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;

/**
 * Reading the photo of a product, whichever side it shows. With the barcode the app scanned, the ingredients on a
 * label add a product we don't have, confirm the one we have, or replace a shorter list from the open databases or the
 * web; a medicine box is kept by its active substances. Of a front, Claude can tell only which product it is: its list
 * is looked up. The photo itself is not kept. With the AI switched off (config/hooli.php), no photo is read: the app
 * reads the label on the phone, and sends its text (ProductController::store).
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
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->has('image') ? 'invalid_image' : 'invalid_barcode'], 422);
        }

        $read = LabelReader::read($request->file('image'));
        if (! $read) {
            return response()->json(['message' => 'unreadable'], 422);
        }

        // A medicine's ingredients, for the app, are its active substances.
        $barcode = $request->input('barcode');
        [$product, $foundBy] = match ($read['view']) {
            'ingredients' => [$this->label($barcode, $read), 'label'],
            'medicine' => [$this->label($barcode, [...$read, 'kind' => 'medicine', 'category' => 'medicine', 'ingredients' => $read['medicine']['active_substances']]), 'medicine'],
            'front' => $this->lookUp($barcode, $read),
        };
        if (! $product) {
            // The app names the product it recognised, and asks for a photo of its ingredient list.
            return response()->json(['message' => 'needs_ingredients', 'product' => Arr::only($read, ['name', 'brand', 'kind', 'category'])], 422);
        }

        return response()->json(['product' => $product->toApp(), 'medicine' => $read['medicine'], 'found_by' => $foundBy]);
    }

    // Without a barcode, the read goes back as it is, and nothing is kept.
    private function label(?string $barcode, array $read): Product
    {
        return $barcode
            ? $this->learn($barcode, $read)
            : new Product([...Arr::only($read, ['name', 'brand', 'kind', 'category', 'ingredients']), 'barcode' => null, 'source' => 'hooli', 'confirmations' => 0]);
    }

    // A label read is first-hand: it adds the product, confirms the list we have, or replaces a shorter one from the
    // open databases or the web, whose ids of its ingredients go with it. A list that disagrees with a longer one, or
    // with an earlier read, stays as it is.
    private function learn(string $barcode, array $read): Product
    {
        $product = Product::firstWhere('barcode', $barcode);
        if (! $product) {
            return Product::create([...Arr::only($read, ['name', 'brand', 'kind', 'category', 'ingredients']), 'barcode' => $barcode, 'source' => 'hooli']);
        }

        if ($product->hasIngredients($read['ingredients'])) {
            $product->increment('confirmations');
        } elseif (! in_array($product->source, Product::READ_ON_LABEL, true) && count($product->ingredients) < count($read['ingredients'])) {
            $product->update(['ingredients' => $read['ingredients'], 'ingredient_ids' => null, 'source' => 'hooli', 'source_url' => null, 'confirmations' => 1]);
        }

        return $product;
    }

    /**
     * The ingredient list of the product on a front: ours, then the open databases', then the web's, the one that
     * costs a fee for each search. What the open databases know is kept under its own barcode, what the web gives
     * under the one the app scanned.
     *
     * @return array{?Product, ?string} the product, and where it was found
     */
    private function lookUp(?string $barcode, array $read): array
    {
        // By the barcode, or else by its name and brand, whatever their case: the most confirmed list first.
        $ours = ($barcode ? Product::firstWhere('barcode', $barcode) : null) ?? Product::query()
            ->whereRaw('lower(name) = lower(?)', [$read['name']])
            ->when($read['brand'], fn ($query, string $brand) => $query->whereRaw('lower(brand) = lower(?)', [$brand]))
            ->whereJsonLength('ingredients', '>', 0)
            ->orderByDesc('confirmations')
            ->latest('updated_at')
            ->first();
        if ($ours) {
            return [$ours, 'database'];
        }

        $open = OpenFacts::search($read['name'], $read['brand'], $read['kind']);
        if ($open) {
            return [Product::firstWhere('barcode', $open->barcode) ?? tap($open)->save(), 'open_facts'];
        }

        $web = WebIngredients::find($read, $barcode);
        if ($web && $barcode) {
            $web->save();
        }

        return [$web, $web ? 'web' : null];
    }
}
