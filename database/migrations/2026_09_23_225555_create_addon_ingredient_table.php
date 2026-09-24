<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// This is DemoData::addonRecipes() as a real table.

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addon_ingredient', function (Blueprint $table) {
            $table->id();
            $table->foreignId('addon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 10, 2);
            $table->timestamps();

            $table->unique(['addon_id', 'ingredient_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addon_ingredient');
    }
};
