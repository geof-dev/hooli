<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Products, never people: no one's scans or health data are kept, only what is in the product.
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('barcode', 14)->unique();
            $table->string('name');
            $table->string('brand')->nullable();
            $table->string('kind', 10);
            $table->string('category', 20);
            // In the order of the label, by their INCI or English names.
            $table->json('ingredients');
            $table->string('image_url', 500)->nullable();
            // hooli for a label read in the app, or the open database it was found in (openbeautyfacts, ...).
            $table->string('source', 20);
            // How many label reads found the same ingredients.
            $table->unsignedInteger('confirmations')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
