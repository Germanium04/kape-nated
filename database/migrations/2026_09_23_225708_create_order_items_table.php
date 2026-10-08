<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete(); //[cite: 23]
            $table->foreignId('menu_item_id')->constrained(); //[cite: 23]
            $table->unsignedInteger('quantity'); //[cite: 23]
            $table->decimal('unit_price', 8, 2); // snapshot of menu_items.price[cite: 23]
            $table->string('size')->nullable(); // e.g., 'grande', 'venti', 'small', 'giant'
            $table->enum('temperature', ['hot', 'cold'])->nullable(); //[cite: 23]
            $table->timestamps(); //[cite: 23]
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
