<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_no')->unique(); // e.g. "0148", shown on the receipt
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // staff who rang it up
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->enum('payment_method', ['cash', 'gcash']);
            $table->enum('status', ['completed', 'refunded', 'voided'])->default('completed');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
