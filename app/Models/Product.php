<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A product the app can look up by its barcode: what it is, and what is in it. */
class Product extends Model
{
    // What shelves carry, from EAN-8 to GTIN-14.
    public const BARCODE = '/^\d{8,14}$/';

    protected $fillable = ['barcode', 'name', 'brand', 'kind', 'category', 'ingredients', 'image_url', 'source', 'confirmations'];

    protected $attributes = ['confirmations' => 1];

    protected function casts(): array
    {
        return ['ingredients' => 'array', 'confirmations' => 'integer'];
    }

    /** The product as the app reads it. */
    public function toApp(): array
    {
        return [
            'barcode' => $this->barcode,
            'name' => $this->name,
            'brand' => $this->brand,
            'kind' => $this->kind,
            'category' => $this->category,
            'ingredients' => $this->ingredients,
            'image_url' => $this->image_url,
            'source' => $this->source,
            'confirmations' => $this->confirmations,
            'updated_at' => $this->updated_at?->toAtomString(),
        ];
    }
}
