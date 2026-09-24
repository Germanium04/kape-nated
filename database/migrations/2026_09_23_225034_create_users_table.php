<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Replaces the default 0001_01_01_000000_create_users_table.php migration
// that ships with a fresh Laravel install. Delete that stock file (and its
// password_reset_tokens / sessions companions if you don't need them yet)
// so you don't end up with two users tables fighting each other.

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('username')->unique();
            $table->string('contact')->unique(); 
            $table->string('password');
            $table->enum('role', ['admin', 'staff'])->default('staff');
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
