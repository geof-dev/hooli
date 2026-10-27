<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscribers', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('locale', 2);
            // Health data, given by choice: kept only to decide what to build first, deleted with the address.
            $table->string('situation', 20)->nullable();
            // The campaign that brought them, if any.
            $table->string('utm_source', 80)->nullable();
            $table->string('utm_campaign', 80)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscribers');
    }
};
