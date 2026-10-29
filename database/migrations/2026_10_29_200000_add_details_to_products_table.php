<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What the open databases know of a product besides its list, for the phone to score it without AI.
        Schema::table('products', function (Blueprint $table) {
            // Their categories of it, from the most general to the most specific (en:breads, en:white-breads).
            $table->json('types')->nullable()->after('category');
            // Where it is sold or was scanned, by ISO 3166-1 codes in lower case (ee).
            $table->json('countries')->nullable()->after('types');
            // Per 100 g or ml: energy_kcal, carbohydrates, sugars, fibre, fat, saturated_fat, proteins and salt.
            $table->json('nutrition')->nullable()->after('countries');
            // Its NOVA group, from 1 (unprocessed) to 4 (ultra-processed).
            $table->unsignedTinyInteger('processing')->nullable()->after('nutrition');
            // Their ids of the ingredients (en:wheat-flour), one for each, in the same order: null for one they don't know.
            $table->json('ingredient_ids')->nullable()->after('ingredients');
            // Its additives, by their ids (en:e300).
            $table->json('additives')->nullable()->after('ingredient_ids');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['types', 'countries', 'nutrition', 'processing', 'ingredient_ids', 'additives']);
        });
    }
};
