<?php

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');

// A product as the API keeps it, from a label read unless the fields say otherwise.
function storeProduct(array $fields = []): Product
{
    return Product::create($fields + [
        'barcode' => '4740000123456',
        'name' => 'Night cream with retinol',
        'brand' => 'Saar & Sammal',
        'kind' => 'cosmetic',
        'category' => 'face-care',
        'ingredients' => ['Aqua', 'Glycerin', 'Retinyl Palmitate'],
        'source' => 'hooli',
    ]);
}
