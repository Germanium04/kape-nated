<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingredients', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique(); 
            $table->string('name');
            $table->enum('unit_type', ['volume', 'weight', 'count'])->default('volume'); // <-- Volume (ml), Weight (g), Count (pc)
            $table->string('unit', 10); // base unit: ml, g, pc
            $table->string('purchase_unit', 20)->nullable()->default('1L Bottle'); // Gallon, 1Kg Bag, Pack, etc.
            $table->decimal('conversion_factor', 10, 2)->default(1.00); 
            $table->decimal('stock', 10, 2)->default(0);
            $table->decimal('reorder_level', 10, 2)->default(0);
            $table->decimal('cost', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingredients');
    }
};