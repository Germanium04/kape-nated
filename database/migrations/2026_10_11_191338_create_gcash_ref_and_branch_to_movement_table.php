<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The order page requires a GCash reference number but nothing stored it.
        Schema::table('orders', function (Blueprint $table) {
            $table->string('gcash_ref', 50)->nullable()->after('payment_method');
            $table->index(['branch_id', 'created_at']); // dashboard / receipts filter on these
        });

        // Lets "Used Today" and stock history be per branch instead of chain-wide.
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->index(['branch_id', 'created_at']);
        });

        // Backfill existing rows where we can tell the branch.
        DB::statement('UPDATE stock_movements sm JOIN orders o ON o.id = sm.order_id SET sm.branch_id = o.branch_id WHERE sm.branch_id IS NULL');
        DB::statement('UPDATE stock_movements sm JOIN users u ON u.id = sm.user_id SET sm.branch_id = u.branch_id WHERE sm.branch_id IS NULL');
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropIndex(['branch_id', 'created_at']);
            $table->dropColumn('branch_id');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['branch_id', 'created_at']);
            $table->dropColumn('gcash_ref');
        });
    }
};