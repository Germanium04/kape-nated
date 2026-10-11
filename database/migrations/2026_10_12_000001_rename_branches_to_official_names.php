<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Renames legacy demo branch names to the five official branches.
// Runs in place so existing orders / branch_inventory foreign keys are preserved.
return new class extends Migration
{
    public function up(): void
    {
        $map = [
            'Poblacion Main'  => 'UM Matina Branch',
            'Mabini Branch'   => 'Tibungco Branch',
            'Uptown Terminal' => 'Ubalde Branch',
            'Riverside Stall' => 'Carmen Branch',
            'Airport Kiosk'   => 'Tagum Branch',
        ];

        foreach ($map as $old => $new) {
            $oldRow = DB::table('branches')->where('name', $old)->first();
            if (! $oldRow) {
                continue;
            }

            $newRow = DB::table('branches')->where('name', $new)->first();

            if ($newRow) {
                // Both names exist (e.g. seeder already ran): move children to the
                // canonical row, then drop the legacy duplicate.
                DB::table('orders')->where('branch_id', $oldRow->id)->update(['branch_id' => $newRow->id]);
                DB::table('branch_inventory')->where('branch_id', $oldRow->id)->delete();
                DB::table('branches')->where('id', $oldRow->id)->delete();
            } else {
                DB::table('branches')->where('id', $oldRow->id)->update([
                    'name'       => $new,
                    'updated_at' => now(),
                ]);
            }
        }

        // Guarantee all five official branches exist.
        foreach (['UM Matina Branch', 'Tibungco Branch', 'Ubalde Branch', 'Carmen Branch', 'Tagum Branch'] as $name) {
            if (! DB::table('branches')->where('name', $name)->exists()) {
                DB::table('branches')->insert([
                    'name'       => $name,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        $map = [
            'UM Matina Branch' => 'Poblacion Main',
            'Tibungco Branch'  => 'Mabini Branch',
            'Ubalde Branch'    => 'Uptown Terminal',
            'Carmen Branch'    => 'Riverside Stall',
            'Tagum Branch'     => 'Airport Kiosk',
        ];

        foreach ($map as $new => $old) {
            DB::table('branches')->where('name', $new)->update([
                'name'       => $old,
                'updated_at' => now(),
            ]);
        }
    }
};
