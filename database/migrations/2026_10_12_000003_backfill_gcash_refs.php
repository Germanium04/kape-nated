<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Fills existing GCash orders that predate the gcash_ref column with
// random realistic 13-digit refs so demo receipts feel complete.
// Cash orders are left as NULL (no ref row shown - correct).
return new class extends Migration
{
    public function up(): void
    {
        $orders = DB::table('orders')
            ->where('payment_method', 'gcash')
            ->whereNull('gcash_ref')
            ->pluck('id');

        foreach ($orders as $id) {
            DB::table('orders')->where('id', $id)->update([
                // Real GCash refs are 13 digits; keep a 13xx prefix for realism.
                'gcash_ref'  => '13' . (string) random_int(10000000000, 99999999999),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // No-op: cannot distinguish backfilled refs from real ones,
        // so rolling back to NULL would destroy genuine data.
    }
};
