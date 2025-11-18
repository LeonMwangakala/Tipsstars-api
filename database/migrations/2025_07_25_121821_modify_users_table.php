<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone_number')->unique()->after('name');
            $table->enum('role', ['customer', 'tipster', 'admin'])->default('customer')->after('phone_number');
            $table->dropColumn('email');
            $table->dropColumn('email_verified_at');
            // Make password nullable since we're using OTP
            DB::statement('ALTER TABLE users ALTER COLUMN password DROP NOT NULL');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->unique()->after('name');
            $table->timestamp('email_verified_at')->nullable()->after('email');
            $table->dropColumn('phone_number');
            $table->dropColumn('role');
        });
    }
};
