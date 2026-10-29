<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\OpenFacts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Products for the app: one by its barcode, and the better swaps for one. */
class ProductController extends Controller
{
    public function show(string $barcode): JsonResponse
    {
        if (! preg_match(Product::BARCODE, $barcode)) {
            return response()->json(['message' => 'invalid_barcode'], 422);
        }

        // Ours first. One we don't have is looked up in the open databases, and kept when they know it.
        $product = Product::firstWhere('barcode', $barcode);
        if (! $product) {
            $product = OpenFacts::find($barcode);
            $product?->save();
        }

        return $product
            ? response()->json(['product' => $product->toApp()])
            : response()->json(['message' => 'not_found'], 404);
    }

    // Products of the same category, the most confirmed first. The phone scores them against the profile only it
    // knows, and offers the better ones.
    public function index(Request $request): JsonResponse
    {
        if (! in_array($request->query('category'), config('hooli.categories'), true)) {
            return response()->json(['message' => 'invalid_category'], 422);
        }
        $exclude = $request->query('exclude');

        $products = Product::where('category', $request->query('category'))
            ->whereJsonLength('ingredients', '>', 0)
            ->when(is_string($exclude), fn ($query) => $query->where('barcode', '!=', $exclude))
            ->orderByDesc('confirmations')
            ->latest('updated_at')
            ->limit(40)
            ->get();

        return response()->json(['products' => $products->map->toApp()]);
    }
}
