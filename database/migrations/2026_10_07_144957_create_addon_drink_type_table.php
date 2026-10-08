<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addon_drink_type', function (Blueprint $table) {
            $table->id();
            $table->foreignId('addon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('drink_type_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['addon_id', 'drink_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addon_drink_type');
    }
};