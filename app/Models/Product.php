<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A product the app can look up by its barcode: what it is, and what is in it. */
class Product extends Model
{
    // What shelves carry, from EAN-8 to GTIN-14.
    public const BARCODE = '/^\d{8,14}$/';

    // A country, by its ISO 3166-1 code: ee, or EE.
    public const COUNTRY = '/^[a-z]{2}$/i';

    // The sources of a list read on the product itself: by Claude on a photo (hooli), or by the app on the phone (user).
    // Another read confirms it, and nothing else replaces it.
    public const READ_ON_LABEL = ['hooli', 'user'];

    protected $fillable = [
        'barcode', 'name', 'brand', 'kind', 'category', 'types', 'countries', 'nutrition', 'processing', 'ingredients',
        'ingredient_ids', 'additives', 'image_url', 'source', 'source_url', 'confirmations',
    ];

    protected $attributes = ['confirmations' => 1];

    protected function casts(): array
    {
        return [
            'types' => 'array',
            'countries' => 'array',
            'nutrition' => 'array',
            'processing' => 'integer',
            'ingredients' => 'array',
            'ingredient_ids' => 'array',
            'additives' => 'array',
            'confirmations' => 'integer',
        ];
    }

    // The same ingredients in the same order, whatever the case, spacing or punctuation: Aqua (Water) is aqua water.
    public function hasIngredients(array $names): bool
    {
        $key = fn (array $names) => array_map(fn (string $name) => preg_replace('/[^\pL\pN]+/u', '', mb_strtolower($name)), $names);

        return $key($this->ingredients) === $key($names);
    }

    /** Adds the country it was found in to those it is sold in, not yet saved. */
    public function foundIn(?string $country): static
    {
        if ($country && ! in_array($country, $this->countries ?? [], true)) {
            $this->countries = [...$this->countries ?? [], $country];
        }

        return $this;
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
            'types' => $this->types,
            'countries' => $this->countries,
            'ingredients' => $this->ingredients,
            'ingredient_ids' => $this->ingredient_ids,
            'additives' => $this->additives,
            'nutrition' => $this->nutrition,
            'processing' => $this->processing,
            'image_url' => $this->image_url,
            'source' => $this->source,
            'source_url' => $this->source_url,
            'confirmations' => $this->confirmations,
            'updated_at' => $this->updated_at?->toAtomString(),
        ];
    }
}
