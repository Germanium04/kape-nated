<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Support\DemoData;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    
    public function run(): void
    {
        // Branches must exist before any user can point at one.
        foreach (DemoData::branches() as $name) {
            Branch::firstOrCreate(['name' => $name]);
        }

        $firstBranch = Branch::where('name', DemoData::branches()[0])->first();

        User::updateOrCreate(
            ['contact' => 'admin'],
            [
                'name' => 'Admin User',
                'username' => 'Admin',
                'password' => Hash::make('1234'),
                'role' => 'admin',
                'branch_id' => null,
            ]
        );

        User::updateOrCreate(
            ['contact' => 'staff'],
            [
                'name' => 'Staff User',
                'username' => 'Staff',
                'password' => Hash::make('1234'),
                'role' => 'staff',
                'branch_id' => $firstBranch->id,
            ]
        );
    }
}