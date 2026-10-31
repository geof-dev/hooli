<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\OpenFacts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

/** Products for the app: one by its barcode, the better swaps for one, and those whose label the app read. */
class ProductController extends Controller
{
    public function show(Request $request, string $barcode): JsonResponse
    {
        if (! preg_match(Product::BARCODE, $barcode)) {
            return response()->json(['message' => 'invalid_barcode'], 422);
        }
        $country = $this->country($request);
        if ($country === false) {
            return response()->json(['message' => 'invalid_country'], 422);
        }

        // Ours first. One we don't have is looked up in the open databases, and kept when they know it. Either is
        // sold where it was scanned.
        $product = Product::firstWhere('barcode', $barcode) ?? OpenFacts::find($barcode);
        $product?->foundIn($country)->save();

        return $product
            ? response()->json(['product' => $product->toApp()])
            : response()->json(['message' => 'not_found'], 404);
    }

    // Products of the same category, sold in the country when the app says which, the most confirmed first, and of
    // food the richest in fibre. The phone scores them against the profile only it knows, and offers the better ones.
    public function index(Request $request): JsonResponse
    {
        $category = $request->query('category');
        if (! in_array($category, config('hooli.categories'), true)) {
            return response()->json(['message' => 'invalid_category'], 422);
        }
        $country = $this->country($request);
        if ($country === false) {
            return response()->json(['message' => 'invalid_country'], 422);
        }
        $exclude = $request->query('exclude');

        $candidates = Product::where('category', $category)
            ->whereJsonLength('ingredients', '>', 0)
            ->when(is_string($exclude), fn ($query) => $query->where('barcode', '!=', $exclude))
            ->when($country, fn ($query, string $country) => $query->whereJsonContains('countries', $country));

        // Of the same type as the product scanned, as narrow a one as gives five to choose from: white breads, or else
        // breads, or else any food. The types go from the most general to the most specific; twenty are plenty.
        $types = is_string($request->query('types')) ? array_filter(array_map('trim', explode(',', $request->query('types')))) : [];
        $type = collect(array_slice($types, -20))->reverse()
            ->first(fn (string $type) => $candidates->clone()->whereJsonContains('types', $type)->count() >= 5);

        $products = $candidates
            ->when($type, fn ($query, string $type) => $query->whereJsonContains('types', $type))
            ->orderByDesc('confirmations')
            ->when(in_array($category, config('hooli.food_categories'), true), fn ($query) => $query->orderByDesc('nutrition->fibre'))
            ->latest('updated_at')
            ->limit(40)
            ->get();

        return response()->json(['products' => $products->map->toApp()]);
    }

    // A product whose label the app read on the phone: its name, and its ingredients, or a medicine's active
    // substances. Like a label photo, it adds the product, confirms the list we have, or replaces a shorter one from
    // the open databases or the web. A list that disagrees with a longer one, or with an earlier read, stays as it is.
    public function store(Request $request): JsonResponse
    {
        $fields = $request->validate([
            'barcode' => ['required', 'string', 'regex:'.Product::BARCODE],
            'name' => ['required', 'string', 'max:200'],
            'brand' => ['nullable', 'string', 'max:200'],
            'kind' => ['required', Rule::in(config('hooli.kinds'))],
            'category' => ['required', Rule::in(config('hooli.categories'))],
            'ingredients' => ['required', 'list', 'min:1', 'max:300'],
            'ingredients.*' => ['required', 'string', 'max:200'],
            'country' => ['nullable', 'string', 'regex:'.Product::COUNTRY],
        ]);
        $country = isset($fields['country']) ? strtolower($fields['country']) : null;

        $product = Product::firstWhere('barcode', $fields['barcode']);
        if (! $product) {
            $product = Product::create([...Arr::except($fields, 'country'), 'source' => 'user', 'countries' => $country ? [$country] : null]);

            return response()->json(['product' => $product->toApp()], 201);
        }

        if ($product->hasIngredients($fields['ingredients'])) {
            $product->increment('confirmations');
        } elseif (! in_array($product->source, Product::READ_ON_LABEL, true) && count($product->ingredients) < count($fields['ingredients'])) {
            $product->fill(['ingredients' => $fields['ingredients'], 'ingredient_ids' => null, 'source' => 'user', 'source_url' => null, 'confirmations' => 1]);
        }
        $product->foundIn($country)->save();

        return response()->json(['product' => $product->toApp()]);
    }

    // The country the app is used in, by its ISO code in lower case, when it says: false when that isn't one.
    private function country(Request $request): string|false|null
    {
        $country = $request->query('country');

        return match (true) {
            $country === null => null,
            is_string($country) && preg_match(Product::COUNTRY, $country) === 1 => strtolower($country),
            default => false,
        };
    }
}
