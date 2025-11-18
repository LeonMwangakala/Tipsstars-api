<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('predictions', function (Blueprint $table) {
            $table->foreignId('booker_id')
                ->nullable()
                ->after('tipster_id')
                ->constrained('bookers')
                ->nullOnDelete();
            $table->string('betting_slip_url')->nullable()->after('booker_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('predictions', function (Blueprint $table) {
            if (Schema::hasColumn('predictions', 'betting_slip_url')) {
                $table->dropColumn('betting_slip_url');
            }
            if (Schema::hasColumn('predictions', 'booker_id')) {
                $table->dropConstrainedForeignId('booker_id');
            }
        });
    }
};

