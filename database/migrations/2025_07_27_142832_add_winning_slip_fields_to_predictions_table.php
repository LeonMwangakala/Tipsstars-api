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
            $table->string('winning_slip_url')->nullable()->after('image_url');
            $table->timestamp('result_updated_at')->nullable()->after('result_notes');
            $table->string('result_updated_by')->nullable()->after('result_updated_at'); // tipster_id who updated the result
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('predictions', function (Blueprint $table) {
            $table->dropColumn(['winning_slip_url', 'result_updated_at', 'result_updated_by']);
        });
    }
};
